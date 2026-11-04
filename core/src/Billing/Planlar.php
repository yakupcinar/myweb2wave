<?php

declare(strict_types=1);

namespace App\Billing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ExceptionInterface;
use Stripe\StripeClient;

/**
 * M1a: fiyat kataloğu — uygulamanın planları (anahtar → Stripe ürünü +
 * güncel fiyatı). Plan panelden açılır, Stripe'ta ürün ve fiyat buradan
 * oluşturulur. Tutar veritabanında YOK: gösterilen fiyat yine Stripe fiyat
 * nesnesinden okunur (Fiyatlar, L10) — iki kaynak ayrışamaz.
 *
 * Stripe fiyatı değiştirilemez: yeni tutar = aynı üründe yeni fiyat; eski
 * fiyat satıştan kalkar (active=false), mevcut abonelikler onda sürer.
 * Anahtar değişmez ve plan silinmez — yayındaki huniler anahtarla bağlı.
 */
final readonly class Planlar
{
    public const ANAHTAR = '/^[a-z][a-z0-9_]{0,31}$/';
    public const ARALIKLAR = ['week', 'month', 'year'];
    /** M1c'ye kadar paywall tek para birimiyle satar; seçim panelde bu listeden. */
    public const PARA_BIRIMLERI = ['EUR', 'USD', 'GBP', 'TRY'];
    /** M1b: planın giriş teklifi; giris = deneme günleri + tek seferlik ücret (kullanıcı kararı 2026-11-04). */
    public const TEKLIF_TURLERI = ['deneme', 'giris', 'indirim'];
    public const AZAMI_TEKLIF_GUNU = 90;

    public function __construct(
        private Connection $db,
        private StripeClient $stripe,
        private LoggerInterface $logger,
    ) {
    }

    /** @return array<string, string> plan anahtarı → güncel Stripe fiyatı */
    public function fiyatKimlikleri(string $app): array
    {
        /** @var array<string, string> $eslesme */
        $eslesme = $this->db->fetchAllKeyValue(
            'SELECT anahtar, stripe_fiyat FROM planlar WHERE app = :app ORDER BY created_at, anahtar',
            ['app' => $app],
        );

        return $eslesme;
    }

    /**
     * M1b: planların teklifleri (teklifsiz plan listede yok).
     *
     * @return array<string, array{tur: string, gun: int|null, yuzde: int|null, stripe: string|null}>
     */
    public function teklifler(string $app): array
    {
        /** @var list<array{anahtar: string, teklif_turu: string, teklif_gun: int|null, teklif_yuzde: int|null, stripe_teklif: string|null}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            'SELECT anahtar, teklif_turu, teklif_gun, teklif_yuzde, stripe_teklif FROM planlar WHERE app = :app AND teklif_turu IS NOT NULL',
            ['app' => $app],
        );
        $teklifler = [];
        foreach ($satirlar as $s) {
            $teklifler[$s['anahtar']] = ['tur' => $s['teklif_turu'], 'gun' => $s['teklif_gun'], 'yuzde' => $s['teklif_yuzde'], 'stripe' => $s['stripe_teklif']];
        }

        return $teklifler;
    }

    /** @return list<array{anahtar: string, ad: string, stripeFiyat: string}> */
    public function liste(string $app): array
    {
        /** @var list<array{anahtar: string, ad: string, stripe_fiyat: string}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            'SELECT anahtar, ad, stripe_fiyat FROM planlar WHERE app = :app ORDER BY created_at, anahtar',
            ['app' => $app],
        );

        return array_map(static fn (array $s): array => ['anahtar' => $s['anahtar'], 'ad' => $s['ad'], 'stripeFiyat' => $s['stripe_fiyat']], $satirlar);
    }

    /**
     * Yeni plan: Stripe'ta ürün + yinelenen fiyat, sonra katalog satırı.
     *
     * @return list<string> hatalar (boşsa oluşturuldu)
     */
    public function olustur(string $app, mixed $anahtar, mixed $ad, mixed $tutar, mixed $paraBirimi, mixed $aralik): array
    {
        $hatalar = [
            ...self::anahtarHatasi($anahtar),
            ...self::adHatasi($ad),
            ...self::tutarHatasi($tutar),
        ];
        if (!\is_string($paraBirimi) || !\in_array($paraBirimi, self::PARA_BIRIMLERI, true)) {
            $hatalar[] = 'Para birimi şunlardan biri olmalı: '.implode(', ', self::PARA_BIRIMLERI).'.';
        }
        if (!\is_string($aralik) || !\in_array($aralik, self::ARALIKLAR, true)) {
            $hatalar[] = 'Aralık week, month ya da year olmalı.';
        }
        // Tür denetimleri yukarıdaki hata denetimlerinin tekrarı — statik analiz için (boş liste dönmez).
        if ([] !== $hatalar || !\is_string($anahtar) || !\is_string($ad) || !(\is_int($tutar) || \is_float($tutar)) || !\is_string($paraBirimi) || !\is_string($aralik)) {
            return $hatalar;
        }
        // Önce bakılır: Stripe'ta boşuna ürün açılmasın. Asıl bekçi birincil
        // anahtar (yarışta iki istek de buradan geçebilir).
        if (isset($this->fiyatKimlikleri($app)[$anahtar])) {
            return ["'{$anahtar}' planı zaten var."];
        }

        try {
            $urun = $this->stripe->products->create(['name' => trim($ad), 'metadata' => ['app' => $app, 'plan' => $anahtar]]);
            $fiyat = $this->stripe->prices->create([
                'product' => $urun->id,
                'currency' => strtolower($paraBirimi),
                'unit_amount' => ParaBirimi::kucukBirim($tutar, $paraBirimi),
                'recurring' => ['interval' => $aralik],
                'metadata' => ['app' => $app, 'plan' => $anahtar],
            ]);
        } catch (ExceptionInterface $e) {
            $this->logger->error('Stripe planı oluşturulamadı', ['plan' => $anahtar, 'exception' => $e]);

            return ['Stripe şu an yanıt vermiyor; plan oluşturulmadı.'];
        }

        try {
            $this->db->insert('planlar', ['app' => $app, 'anahtar' => $anahtar, 'ad' => trim($ad), 'stripe_urun' => $urun->id, 'stripe_fiyat' => $fiyat->id]);
        } catch (UniqueConstraintViolationException) {
            $this->logger->warning('Plan yarışı: Stripe ürünü sahipsiz kaldı', ['plan' => $anahtar, 'urun' => $urun->id]);

            return ["'{$anahtar}' planı zaten var."];
        }

        return [];
    }

    /**
     * Yeni tutar: aynı üründe, aynı para birimi ve aralıkla yeni fiyat; eski
     * fiyat satıştan kalkar. Mevcut aboneler eski fiyatta kalır (Stripe).
     *
     * @return list<string>|null null = böyle plan yok
     */
    public function fiyatDegistir(string $app, string $anahtar, mixed $tutar): ?array
    {
        /** @var array{stripe_urun: string, stripe_fiyat: string}|false $satir */
        $satir = $this->db->fetchAssociative('SELECT stripe_urun, stripe_fiyat FROM planlar WHERE app = :app AND anahtar = :anahtar', ['app' => $app, 'anahtar' => $anahtar]);
        if (false === $satir) {
            return null;
        }
        $hatalar = self::tutarHatasi($tutar);
        if ([] !== $hatalar || !(\is_int($tutar) || \is_float($tutar))) {
            return $hatalar;
        }
        try {
            $eski = $this->stripe->prices->retrieve($satir['stripe_fiyat']);
            $aralik = $eski->recurring?->interval;
            if (!\is_string($aralik)) {
                return ['Mevcut Stripe fiyatı yinelenen değil; değiştirilemez.'];
            }
            $yeni = $this->stripe->prices->create([
                'product' => $satir['stripe_urun'],
                'currency' => $eski->currency,
                'unit_amount' => ParaBirimi::kucukBirim($tutar, $eski->currency),
                'recurring' => ['interval' => $aralik],
                'metadata' => ['app' => $app, 'plan' => $anahtar],
            ]);
        } catch (ExceptionInterface $e) {
            $this->logger->error('Stripe fiyatı değiştirilemedi', ['plan' => $anahtar, 'exception' => $e]);

            return ['Stripe şu an yanıt vermiyor; fiyat değişmedi.'];
        }
        $this->db->executeStatement(
            'UPDATE planlar SET stripe_fiyat = :fiyat, updated_at = now() WHERE app = :app AND anahtar = :anahtar',
            ['fiyat' => $yeni->id, 'app' => $app, 'anahtar' => $anahtar],
        );
        try {
            $this->stripe->prices->update($satir['stripe_fiyat'], ['active' => false]);
        } catch (ExceptionInterface $e) {
            // Katalog zaten yeni fiyatta; eski fiyat Stripe'ta satışta görünür ama huni onu açmaz.
            $this->logger->warning('Eski Stripe fiyatı kapatılamadı', ['fiyat' => $satir['stripe_fiyat'], 'exception' => $e]);
        }

        return [];
    }

    /**
     * M1b: planın teklifini koyar, değiştirir ya da kaldırır ($tur null).
     * Stripe nesnesi (giriş ücreti = aynı üründe tek seferlik fiyat; indirim
     * = tek dönemlik yüzde kuponu) önce açılır, sonra satır yazılır; eskisi
     * en son kapanır — kapanamazsa yalnız günlüğe (katalog onu artık açmaz).
     * Mevcut aboneler etkilenmez.
     *
     * @return list<string>|null null = böyle plan yok
     */
    public function teklifAyarla(string $app, string $anahtar, mixed $tur, mixed $gun, mixed $tutar, mixed $yuzde): ?array
    {
        /** @var array{stripe_urun: string, stripe_fiyat: string, teklif_turu: string|null, stripe_teklif: string|null}|false $satir */
        $satir = $this->db->fetchAssociative('SELECT stripe_urun, stripe_fiyat, teklif_turu, stripe_teklif FROM planlar WHERE app = :app AND anahtar = :anahtar', ['app' => $app, 'anahtar' => $anahtar]);
        if (false === $satir) {
            return null;
        }
        $hatalar = self::teklifHatalari($tur, $gun, $tutar, $yuzde);
        if ([] !== $hatalar) {
            return $hatalar;
        }
        $stripeTeklif = null;
        try {
            if ('giris' === $tur && (\is_int($tutar) || \is_float($tutar))) {
                $ana = $this->stripe->prices->retrieve($satir['stripe_fiyat']);
                $stripeTeklif = $this->stripe->prices->create([
                    'product' => $satir['stripe_urun'],
                    'currency' => $ana->currency,
                    'unit_amount' => ParaBirimi::kucukBirim($tutar, $ana->currency),
                    'nickname' => 'Giriş ücreti',
                    'metadata' => ['app' => $app, 'plan' => $anahtar, 'teklif' => 'giris'],
                ])->id;
            } elseif ('indirim' === $tur && \is_int($yuzde)) {
                $stripeTeklif = $this->stripe->coupons->create([
                    'percent_off' => $yuzde,
                    'duration' => 'once',
                    'name' => "İlk dönem %{$yuzde}",
                    'metadata' => ['app' => $app, 'plan' => $anahtar, 'teklif' => 'indirim'],
                ])->id;
            }
        } catch (ExceptionInterface $e) {
            $this->logger->error('Stripe teklifi oluşturulamadı', ['plan' => $anahtar, 'exception' => $e]);

            return ['Stripe şu an yanıt vermiyor; teklif değişmedi.'];
        }
        $this->db->executeStatement(
            'UPDATE planlar SET teklif_turu = :tur, teklif_gun = :gun, teklif_yuzde = :yuzde, stripe_teklif = :stripe, updated_at = now()
             WHERE app = :app AND anahtar = :anahtar',
            [
                'tur' => $tur,
                'gun' => \in_array($tur, ['deneme', 'giris'], true) ? $gun : null,
                'yuzde' => 'indirim' === $tur ? $yuzde : null,
                'stripe' => $stripeTeklif,
                'app' => $app,
                'anahtar' => $anahtar,
            ],
        );
        try {
            if ('giris' === $satir['teklif_turu'] && null !== $satir['stripe_teklif']) {
                $this->stripe->prices->update($satir['stripe_teklif'], ['active' => false]);
            } elseif ('indirim' === $satir['teklif_turu'] && null !== $satir['stripe_teklif']) {
                // Silinen kupon onu almış aboneliği etkilemez (Stripe).
                $this->stripe->coupons->delete($satir['stripe_teklif']);
            }
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Eski Stripe teklifi kapatılamadı', ['teklif' => $satir['stripe_teklif'], 'exception' => $e]);
        }

        return [];
    }

    /**
     * Stripe'ta önceden açılmış fiyatı kataloğa alır (ör. M1a'dan önceki
     * KeepChat aylık planı; konsol app:plan-ice-aktar).
     *
     * @return list<string>
     */
    public function iceAktar(string $app, mixed $anahtar, mixed $ad, string $fiyatKimligi): array
    {
        $hatalar = [...self::anahtarHatasi($anahtar), ...self::adHatasi($ad)];
        if ([] !== $hatalar || !\is_string($anahtar) || !\is_string($ad)) {
            return $hatalar;
        }
        try {
            $fiyat = $this->stripe->prices->retrieve($fiyatKimligi);
        } catch (ExceptionInterface $e) {
            return ['Stripe fiyatı okunamadı: '.$e->getMessage()];
        }
        if (null === Fiyatlar::oku($fiyat->toArray()) || !\is_string($fiyat->product)) {
            return ['Fiyat tek aralıklı yinelenen bir Stripe fiyatı olmalı.'];
        }
        try {
            $this->db->insert('planlar', ['app' => $app, 'anahtar' => $anahtar, 'ad' => trim($ad), 'stripe_urun' => $fiyat->product, 'stripe_fiyat' => $fiyat->id]);
        } catch (UniqueConstraintViolationException) {
            return ["'{$anahtar}' planı zaten var."];
        }

        return [];
    }

    /** @return list<string> */
    private static function teklifHatalari(mixed $tur, mixed $gun, mixed $tutar, mixed $yuzde): array
    {
        if (null === $tur) {
            return null === $gun && null === $tutar && null === $yuzde ? [] : ['Teklifsiz planda gün, tutar ya da yüzde olmaz.'];
        }
        if (!\is_string($tur) || !\in_array($tur, self::TEKLIF_TURLERI, true)) {
            return ['Teklif türü deneme, giris ya da indirim olmalı.'];
        }
        // Türün almadığı alan sessizce atılmasın: yönetici yanlış türü seçmiş olabilir.
        $fazla = array_keys(array_filter(['gün' => $gun, 'tutar' => $tutar, 'yüzde' => $yuzde], static fn (mixed $d): bool => null !== $d));
        $alanlar = ['deneme' => ['gün'], 'giris' => ['gün', 'tutar'], 'indirim' => ['yüzde']][$tur];
        if ([] !== $fark = array_diff($fazla, $alanlar)) {
            return ["{$tur} teklifi yalnız ".implode(' ve ', $alanlar).' alır ('.implode(', ', $fark).' fazla).'];
        }
        $hatalar = [];
        if ('indirim' !== $tur && (!\is_int($gun) || $gun < 1 || $gun > self::AZAMI_TEKLIF_GUNU)) {
            $hatalar[] = 'Gün 1–'.self::AZAMI_TEKLIF_GUNU.' arası tam sayı olmalı.';
        }
        if ('giris' === $tur) {
            $hatalar = [...$hatalar, ...self::tutarHatasi($tutar)];
        }
        if ('indirim' === $tur && (!\is_int($yuzde) || $yuzde < 1 || $yuzde > 99)) {
            $hatalar[] = 'İndirim yüzdesi 1–99 arası tam sayı olmalı.';
        }

        return $hatalar;
    }

    /** @return list<string> */
    private static function anahtarHatasi(mixed $anahtar): array
    {
        return \is_string($anahtar) && 1 === preg_match(self::ANAHTAR, $anahtar)
            ? []
            : ['Anahtar küçük harfle başlamalı; yalnız a–z, 0–9 ve _ (en çok 32), ör. yearly_pro.'];
    }

    /** @return list<string> */
    private static function adHatasi(mixed $ad): array
    {
        return \is_string($ad) && '' !== trim($ad) && mb_strlen(trim($ad)) <= 100 ? [] : ['Ad 1–100 karakter olmalı.'];
    }

    /** @return list<string> */
    private static function tutarHatasi(mixed $tutar): array
    {
        // Stripe üst sınırı 99.999.999 küçük birim; 0 ücretsiz plan olurdu (ücretsiz deneme teklif türü, M1b).
        if (!(\is_int($tutar) || \is_float($tutar)) || $tutar <= 0 || $tutar >= 1_000_000) {
            return ['Tutar 0\'dan büyük ve 1.000.000\'dan küçük olmalı (ör. 9.99).'];
        }

        // 9.999 sessizce 10,00'a yuvarlanmasın (panel alanı ondalığı sınırlamıyor).
        return abs($tutar * 100 - round($tutar * 100)) < 1e-6 ? [] : ['Tutar en çok 2 ondalık haneli olmalı (ör. 9.99).'];
    }
}
