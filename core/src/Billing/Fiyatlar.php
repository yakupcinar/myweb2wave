<?php

declare(strict_types=1);

namespace App\Billing;

use Psr\Log\LoggerInterface;
use Stripe\Exception\ExceptionInterface;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        /** @var array<string, array<string, string>> uygulama → plan → Stripe fiyatı */
        #[Autowire('%uygulamalar%')]
        private array $uygulamalar,
    ) {
    }

    /** @return array<string, array{tutar: int|float, paraBirimi: string, aralik: string}> plan → fiyat */
    public function uygulama(string $app): array
    {
        $fiyatlar = [];
        foreach ($this->uygulamalar[$app] ?? [] as $plan => $fiyatKimligi) {
            try {
                $fiyat = $this->fiyatOnbellegi->get('fiyat_'.hash('sha256', $fiyatKimligi), function (ItemInterface $oge) use ($fiyatKimligi): ?array {
                    $oge->expiresAfter(self::ONBELLEK_SN);

                    return self::oku($this->stripe->prices->retrieve($fiyatKimligi)->toArray());
                });
            } catch (ExceptionInterface $e) {
                $this->logger->warning('Stripe fiyatı okunamadı', ['plan' => $plan, 'exception' => $e]);
                continue;
            }
            if (null !== $fiyat) {
                $fiyatlar[(string) $plan] = $fiyat;
            }
        }

        return $fiyatlar;
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
