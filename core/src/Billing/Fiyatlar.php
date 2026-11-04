<?php

declare(strict_types=1);

namespace App\Billing;

use Psr\Log\LoggerInterface;
use Stripe\Exception\ExceptionInterface;
use Stripe\StripeClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Ç19/Ç21: paywall fiyatının tek kaynağı Stripe fiyat nesnesi. Huni yanıtı
 * bunu taşır; elle yazılan "9,99 €" metni Stripe'tan sessizce ayrışmaz.
 * Bir saat önbellek (her huni açılışında Stripe'a gitmesin); Stripe'a
 * ulaşılamazsa fiyat yok, huni yine açılır — hata önbelleğe yazılmaz.
 */
final readonly class Fiyatlar
{
    private const ONBELLEK_SN = 3600;

    public function __construct(
        private StripeClient $stripe,
        private CacheInterface $fiyatOnbellegi,
        private LoggerInterface $logger,
        private Planlar $planlar,
    ) {
    }

    /**
     * M1b: teklifli planın fiyatı teklifiyle birlikte; teklif gösterilemiyorsa
     * (giriş ücreti okunamadı) fiyat da YOK — "9,99 € / ay" yazıp ödemede
     * deneme + giriş ücreti açmak yanıltır, hiç yazmamak yanıltmaz.
     *
     * M1c: `$paraBirimi` (ziyaretçinin ülkesinden) planda ek para birimi
     * olarak varsa fiyat onunla; yoksa planın ana para birimi. Ödeme oturumu
     * aynı seçimi zorlar — paywall ile ödeme sayfası aynı tutarı gösterir.
     *
     * @return array<string, array{tutar: int|float, paraBirimi: string, aralik: string, teklif?: array<string, int|float|string>}> plan → fiyat
     */
    public function uygulama(string $app, ?string $paraBirimi = null): array
    {
        $fiyatlar = [];
        foreach ($this->tum($app) as $plan => $f) {
            $ekler = $f['ekler'] ?? [];
            unset($f['ekler']);
            // Giriş ücreti ana para biriminde (çok para birimiyle birlikte açılamaz — Planlar).
            if (null !== $paraBirimi && isset($ekler[$paraBirimi]) && 'giris' !== ($f['teklif']['tur'] ?? null)) {
                $f = ['tutar' => $ekler[$paraBirimi], 'paraBirimi' => $paraBirimi] + $f;
            }
            $fiyatlar[$plan] = $f;
        }

        return $fiyatlar;
    }

    /**
     * Panel: ana fiyat + ek para birimleri (`ekler`, para birimi → tutar).
     *
     * @return array<string, array{tutar: int|float, paraBirimi: string, aralik: string, ekler?: array<string, int|float>, teklif?: array<string, int|float|string>}>
     */
    public function tum(string $app): array
    {
        $fiyatlar = [];
        $teklifler = $this->planlar->teklifler($app);
        foreach ($this->planlar->fiyatKimlikleri($app) as $plan => $fiyatKimligi) {
            try {
                // M1c: önbellek anahtarı 'fiyat2_' — eski biçim (eksiz) önbellekte kalmasın.
                $fiyat = $this->fiyatOnbellegi->get('fiyat2_'.hash('sha256', $fiyatKimligi), function (ItemInterface $oge) use ($fiyatKimligi): ?array {
                    $oge->expiresAfter(self::ONBELLEK_SN);
                    $p = $this->stripe->prices->retrieve($fiyatKimligi, ['expand' => ['currency_options']])->toArray();
                    $f = self::oku($p);

                    // Ek yoksa alan yok: boş PHP dizisi JSON'da liste olur, sözleşme nesne ister.
                    $ekler = self::ekler($p);

                    return null === $f ? null : $f + ([] === $ekler ? [] : ['ekler' => $ekler]);
                });
            } catch (ExceptionInterface $e) {
                $this->logger->warning('Stripe fiyatı okunamadı', ['plan' => $plan, 'exception' => $e]);
                continue;
            }
            if (null === $fiyat) {
                continue;
            }
            $t = $teklifler[(string) $plan] ?? null;
            if (null !== $t) {
                $teklif = $this->teklif($t, $fiyat['paraBirimi'], (string) $plan);
                if (null === $teklif) {
                    continue;
                }
                $fiyat['teklif'] = $teklif;
            }
            $fiyatlar[(string) $plan] = $fiyat;
        }

        return $fiyatlar;
    }

    /**
     * @param array{tur: string, gun: int|null, yuzde: int|null, stripe: string|null} $t
     *
     * @return array<string, int|float|string>|null
     */
    private function teklif(array $t, string $paraBirimi, string $plan): ?array
    {
        if ('deneme' === $t['tur'] && null !== $t['gun']) {
            return ['tur' => 'deneme', 'gun' => $t['gun']];
        }
        if ('indirim' === $t['tur'] && null !== $t['yuzde']) {
            return ['tur' => 'indirim', 'yuzde' => $t['yuzde']];
        }
        if ('giris' !== $t['tur'] || null === $t['gun'] || null === $t['stripe']) {
            return null;
        }
        $kimlik = $t['stripe'];
        try {
            $tutar = $this->fiyatOnbellegi->get('giris_'.hash('sha256', $kimlik), function (ItemInterface $oge) use ($kimlik, $paraBirimi): int|float|null {
                $oge->expiresAfter(self::ONBELLEK_SN);
                $p = $this->stripe->prices->retrieve($kimlik)->toArray();

                // Tek seferlik ve planla aynı para biriminde olmalı; değilse gösterilmez.
                return \is_int($p['unit_amount'] ?? null) && null === ($p['recurring'] ?? null) && \is_string($p['currency'] ?? null) && strtoupper($p['currency']) === $paraBirimi
                    ? ParaBirimi::anaBirim($p['unit_amount'], $paraBirimi)
                    : null;
            });
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Stripe giriş ücreti okunamadı', ['plan' => $plan, 'exception' => $e]);

            return null;
        }

        return null === $tutar ? null : ['tur' => 'giris', 'gun' => $t['gun'], 'tutar' => $tutar];
    }

    /**
     * Yalnız tek aralıklı yinelenen fiyat ("/ ay"); "3 ayda bir" gibi
     * aralıklar paywall'da gösterilmez (yanlış birim yazmaktansa hiç).
     *
     * @param array<mixed> $p
     *
     * @return array{tutar: int|float, paraBirimi: string, aralik: string}|null
     */
    public static function oku(array $p): ?array
    {
        $tekrar = $p['recurring'] ?? null;
        if (!\is_int($p['unit_amount'] ?? null) || !\is_string($p['currency'] ?? null) || !\is_array($tekrar)
            || 1 !== ($tekrar['interval_count'] ?? null) || !\in_array($tekrar['interval'] ?? null, ['day', 'week', 'month', 'year'], true)) {
            return null;
        }

        return [
            'tutar' => ParaBirimi::anaBirim($p['unit_amount'], $p['currency']),
            'paraBirimi' => strtoupper($p['currency']),
            'aralik' => $tekrar['interval'],
        ];
    }

    /**
     * M1c: `currency_options`'tan ana para birimi dışındakiler (Stripe ana
     * birimi de listeler), yalnız desteklenen para birimleri.
     *
     * @param array<mixed> $p
     *
     * @return array<string, int|float>
     */
    private static function ekler(array $p): array
    {
        $ekler = [];
        foreach (\is_array($p['currency_options'] ?? null) ? $p['currency_options'] : [] as $para => $s) {
            $buyuk = strtoupper((string) $para);
            if ($para !== ($p['currency'] ?? null) && \in_array($buyuk, Planlar::PARA_BIRIMLERI, true) && \is_array($s) && \is_int($s['unit_amount'] ?? null)) {
                $ekler[$buyuk] = ParaBirimi::anaBirim($s['unit_amount'], $buyuk);
            }
        }
        ksort($ekler);

        return $ekler;
    }
}
