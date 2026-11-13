<?php

declare(strict_types=1);

namespace App\Billing;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Stripe\Exception\CardException;
use Stripe\Exception\ExceptionInterface;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * M3c (A16): ek ürün kataloğu (tek seferlik Stripe fiyatı; tutar Stripe'ta,
 * L10) ve ödeme sonrası tek tık satış — kart tekrar girilmez, aboneliğin
 * kayıtlı kartı kullanılır.
 *
 * Satış güvenliği: oturum ödenmiş (deneme dahil) ve bu uygulamanın olmalı,
 * en çok PENCERE_SN önce açılmış olmalı; oturum + ürün başına bir satış
 * (tekil kısıt + Stripe idempotency anahtarı). Müşteri sayfada olduğu için
 * ödeme "on-session": banka doğrulama isterse istemci 3D Secure'u tamamlar,
 * sonra aynı istekle satış yeniden denetlenip kapanır (webhook gerekmez).
 */
final readonly class EkUrunler
{
    public const ANAHTAR = '/^[a-z][a-z0-9_]{0,31}$/';
    public const PENCERE_SN = 3600;
    private const ONBELLEK_SN = 3600;

    public function __construct(
        private Connection $db,
        private StripeClient $stripe,
        private LoggerInterface $logger,
        private CacheInterface $fiyatOnbellegi,
        #[Autowire('%env(STRIPE_PUBLISHABLE_KEY)%')]
        private string $yayimlanabilirAnahtar,
    ) {
    }

    /** @return list<array{anahtar: string, ad: string, stripeFiyat: string}> */
    public function liste(string $app): array
    {
        /** @var list<array{anahtar: string, ad: string, stripe_fiyat: string}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative('SELECT anahtar, ad, stripe_fiyat FROM ek_urunler WHERE app = :app ORDER BY created_at, anahtar', ['app' => $app]);

        return array_map(static fn (array $s): array => ['anahtar' => $s['anahtar'], 'ad' => $s['ad'], 'stripeFiyat' => $s['stripe_fiyat']], $satirlar);
    }

    /** @return list<string> */
    public function anahtarlar(string $app): array
    {
        return array_column($this->liste($app), 'anahtar');
    }

    /**
     * Yeni ek ürün: Stripe'ta ürün + TEK SEFERLİK fiyat, sonra katalog satırı.
     *
     * @return list<string> hatalar
     */
    public function olustur(string $app, mixed $anahtar, mixed $ad, mixed $tutar, mixed $paraBirimi): array
    {
        $hatalar = [];
        if (!\is_string($anahtar) || 1 !== preg_match(self::ANAHTAR, $anahtar)) {
            $hatalar[] = 'Anahtar küçük harfle başlamalı; yalnız a–z, 0–9 ve _ (en çok 32), ör. kocluk.';
        }
        if (!\is_string($ad) || '' === trim($ad) || mb_strlen(trim($ad)) > 100) {
            $hatalar[] = 'Ad 1–100 karakter olmalı.';
        }
        if (!(\is_int($tutar) || \is_float($tutar)) || $tutar <= 0 || $tutar >= 1_000_000 || abs($tutar * 100 - round($tutar * 100)) >= 1e-6) {
            $hatalar[] = 'Tutar 0\'dan büyük, 1.000.000\'dan küçük ve en çok 2 ondalık olmalı (ör. 19.99).';
        }
        if (!\is_string($paraBirimi) || !\in_array($paraBirimi, Planlar::PARA_BIRIMLERI, true)) {
            $hatalar[] = 'Para birimi şunlardan biri olmalı: '.implode(', ', Planlar::PARA_BIRIMLERI).'.';
        }
        if ([] !== $hatalar || !\is_string($anahtar) || !\is_string($ad) || !(\is_int($tutar) || \is_float($tutar)) || !\is_string($paraBirimi)) {
            return $hatalar;
        }
        if (\in_array($anahtar, $this->anahtarlar($app), true)) {
            return ["'{$anahtar}' ek ürünü zaten var."];
        }
        try {
            $urun = $this->stripe->products->create(['name' => trim($ad), 'metadata' => ['app' => $app, 'ek_urun' => $anahtar]]);
            $fiyat = $this->stripe->prices->create([
                'product' => $urun->id,
                'currency' => strtolower($paraBirimi),
                'unit_amount' => ParaBirimi::kucukBirim($tutar, $paraBirimi),
                'metadata' => ['app' => $app, 'ek_urun' => $anahtar],
            ]);
        } catch (ExceptionInterface $e) {
            $this->logger->error('Stripe ek ürünü oluşturulamadı', ['urun' => $anahtar, 'exception' => $e]);

            return ['Stripe şu an yanıt vermiyor; ek ürün oluşturulmadı.'];
        }
        try {
            $this->db->insert('ek_urunler', ['app' => $app, 'anahtar' => $anahtar, 'ad' => trim($ad), 'stripe_urun' => $urun->id, 'stripe_fiyat' => $fiyat->id]);
        } catch (UniqueConstraintViolationException) {
            return ["'{$anahtar}' ek ürünü zaten var."];
        }

        return [];
    }

    /**
     * Ürünün adı ve Stripe'taki tutarı (önbellekli); tek seferlik değilse ya
     * da okunamazsa null — gösterilmez.
     *
     * @return array{ad: string, tutar: int|float, paraBirimi: string}|null
     */
    public function fiyat(string $app, string $anahtar): ?array
    {
        /** @var array{ad: string, stripe_fiyat: string}|false $s */
        $s = $this->db->fetchAssociative('SELECT ad, stripe_fiyat FROM ek_urunler WHERE app = :app AND anahtar = :anahtar', ['app' => $app, 'anahtar' => $anahtar]);
        if (false === $s) {
            return null;
        }
        $kimlik = $s['stripe_fiyat'];
        try {
            $f = $this->fiyatOnbellegi->get('ek_'.hash('sha256', $kimlik), function (ItemInterface $oge) use ($kimlik): ?array {
                $oge->expiresAfter(self::ONBELLEK_SN);
                $p = $this->stripe->prices->retrieve($kimlik)->toArray();
                if (!\is_int($p['unit_amount'] ?? null) || !\is_string($p['currency'] ?? null) || null !== ($p['recurring'] ?? null)) {
                    return null;
                }

                return ['tutar' => ParaBirimi::anaBirim($p['unit_amount'], $p['currency']), 'paraBirimi' => strtoupper($p['currency'])];
            });
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Stripe ek ürün fiyatı okunamadı', ['urun' => $anahtar, 'exception' => $e]);

            return null;
        }

        return null === $f ? null : ['ad' => $s['ad'], 'tutar' => $f['tutar'], 'paraBirimi' => $f['paraBirimi']];
    }

    /**
     * Ödeme sonrası tek tık satış.
     *
     * @return array{durum: 'odendi'}|array{durum: 'dogrulama', clientSecret: string, anahtar: string}|array{hata: string}
     */
    public function sat(string $app, string $oturum, string $urun): array
    {
        $fiyat = $this->fiyat($app, $urun);
        /** @var string|false $fiyatKimligi */
        $fiyatKimligi = $this->db->fetchOne('SELECT stripe_fiyat FROM ek_urunler WHERE app = :app AND anahtar = :anahtar', ['app' => $app, 'anahtar' => $urun]);
        if (null === $fiyat || false === $fiyatKimligi) {
            return ['hata' => 'unknown_product'];
        }
        // Önceki deneme (iki tık, 3D Secure dönüşü): yeni çekim yok, durum Stripe'tan.
        /** @var array{durum: string, payment_intent: string|null}|false $var */
        $var = $this->db->fetchAssociative('SELECT durum, payment_intent FROM ek_satislar WHERE oturum = :oturum AND urun = :urun AND app = :app', ['oturum' => $oturum, 'urun' => $urun, 'app' => $app]);
        if (false !== $var) {
            return $this->mevcut($oturum, $urun, $var);
        }
        try {
            $o = $this->stripe->checkout->sessions->retrieve($oturum, ['expand' => ['subscription']]);
        } catch (ExceptionInterface) {
            return ['hata' => 'unknown_session'];
        }
        $kart = $o->subscription instanceof \Stripe\Subscription ? $o->subscription->default_payment_method : null;
        if ('complete' !== $o->status || !\in_array($o->payment_status, ['paid', 'no_payment_required'], true)
            || $app !== ($o->metadata['app'] ?? null) || $o->created < time() - self::PENCERE_SN
            || !\is_string($o->customer) || !\is_string($kart)) {
            return ['hata' => 'session_not_eligible'];
        }
        // Ziyaretçinin ödediği para biriminde (M1c); uyuşmazsa satılmaz.
        if (strtoupper((string) $o->currency) !== $fiyat['paraBirimi']) {
            return ['hata' => 'currency_mismatch'];
        }
        try {
            $this->db->insert('ek_satislar', ['app' => $app, 'oturum' => $oturum, 'urun' => $urun, 'durum' => 'baslatildi']);
        } catch (UniqueConstraintViolationException) {
            // Eşzamanlı ikinci tık: ilki çekiyor.
            return ['hata' => 'in_progress'];
        }
        try {
            $pi = $this->stripe->paymentIntents->create([
                'amount' => ParaBirimi::kucukBirim($fiyat['tutar'], $fiyat['paraBirimi']),
                'currency' => strtolower($fiyat['paraBirimi']),
                'customer' => $o->customer,
                'payment_method' => $kart,
                'confirm' => true,
                // Müşteri sayfada (on-session): yönlendirmeli yöntem yok, 3D Secure sayfada.
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                'description' => $fiyat['ad'],
                'metadata' => ['app' => $app, 'ek_urun' => $urun, 'oturum' => $oturum],
            ], ['idempotency_key' => 'ek-'.hash('sha256', $oturum.'|'.$urun)]);
        } catch (CardException $e) {
            $this->durum($oturum, $urun, 'basarisiz', null);

            return ['hata' => 'card_declined'];
        } catch (ExceptionInterface $e) {
            $this->logger->error('Ek ürün ödemesi açılamadı', ['urun' => $urun, 'exception' => $e]);
            $this->db->delete('ek_satislar', ['oturum' => $oturum, 'urun' => $urun, 'durum' => 'baslatildi']);

            return ['hata' => 'payment_provider_unavailable'];
        }

        return $this->sonuc($oturum, $urun, $pi);
    }

    /**
     * @param array{durum: string, payment_intent: string|null} $var
     *
     * @return array{durum: 'odendi'}|array{durum: 'dogrulama', clientSecret: string, anahtar: string}|array{hata: string}
     */
    private function mevcut(string $oturum, string $urun, array $var): array
    {
        if ('odendi' === $var['durum']) {
            return ['durum' => 'odendi'];
        }
        if ('dogrulama' !== $var['durum'] || null === $var['payment_intent']) {
            return ['hata' => 'baslatildi' === $var['durum'] ? 'in_progress' : 'card_declined'];
        }
        try {
            return $this->sonuc($oturum, $urun, $this->stripe->paymentIntents->retrieve($var['payment_intent']));
        } catch (ExceptionInterface) {
            return ['hata' => 'payment_provider_unavailable'];
        }
    }

    /** @return array{durum: 'odendi'}|array{durum: 'dogrulama', clientSecret: string, anahtar: string}|array{hata: string} */
    private function sonuc(string $oturum, string $urun, \Stripe\PaymentIntent $pi): array
    {
        if ('succeeded' === $pi->status) {
            $this->durum($oturum, $urun, 'odendi', $pi->id);

            return ['durum' => 'odendi'];
        }
        if ('requires_action' === $pi->status && \is_string($pi->client_secret)) {
            $this->durum($oturum, $urun, 'dogrulama', $pi->id);

            return ['durum' => 'dogrulama', 'clientSecret' => $pi->client_secret, 'anahtar' => $this->yayimlanabilirAnahtar];
        }
        $this->durum($oturum, $urun, 'basarisiz', $pi->id);

        return ['hata' => 'card_declined'];
    }

    private function durum(string $oturum, string $urun, string $durum, ?string $pi): void
    {
        $this->db->executeStatement(
            'UPDATE ek_satislar SET durum = :durum, payment_intent = coalesce(:pi, payment_intent), updated_at = now() WHERE oturum = :oturum AND urun = :urun',
            ['durum' => $durum, 'pi' => $pi, 'oturum' => $oturum, 'urun' => $urun],
        );
    }
}
