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
     * @return array<string, array{tutar: int|float, paraBirimi: string, aralik: string, teklif?: array<string, int|float|string>}> plan → fiyat
     */
    public function uygulama(string $app): array
    {
        $fiyatlar = [];
        $teklifler = $this->planlar->teklifler($app);
        foreach ($this->planlar->fiyatKimlikleri($app) as $plan => $fiyatKimligi) {
            try {
                $fiyat = $this->fiyatOnbellegi->get('fiyat_'.hash('sha256', $fiyatKimligi), function (ItemInterface $oge) use ($fiyatKimligi): ?array {
                    $oge->expiresAfter(self::ONBELLEK_SN);

                    return self::oku($this->stripe->prices->retrieve($fiyatKimligi)->toArray());
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
}
