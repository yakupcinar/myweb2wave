<?php

declare(strict_types=1);

namespace App\Guvenlik;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Herkese açık uçlarda IP başına hız sınırı — istek controller'a ulaşmadan
 * (yönlendirmeden önce: var olmayan adreslere tarama da sayılır). Sınırlar ve
 * gerekçeleri config/packages/rate_limiter.yaml'da.
 *
 * Pahalı uçların kendi sınırı + genel sınır birlikte işler. Muaf: /health
 * (izleme), /webhooks (Stripe/Postmark; imzalı), /panel (oturum; girişin kendi
 * sınırı var), /mcp (kendi sınırı var). Tarayıcının CORS ön isteği buraya hiç
 * gelmez: nelmio (öncelik 250) yanıtlar — öncelik 64 bunun için. IP vekil
 * arkasından doğru okunur (framework.yaml trusted_proxies, Ç34).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final readonly class HizSiniri
{
    private const MUAF = ['/health', '/webhooks/', '/panel', '/mcp'];

    public function __construct(
        private RateLimiterFactory $hizGenelLimiter,
        private RateLimiterFactory $hizOdemeLimiter,
        private RateLimiterFactory $hizEpostaLimiter,
        private RateLimiterFactory $hizKimlikLimiter,
        private RateLimiterFactory $hizEkSatisLimiter,
    ) {
    }

    public function __invoke(RequestEvent $olay): void
    {
        if (!$olay->isMainRequest()) {
            return;
        }
        $istek = $olay->getRequest();
        $yol = $istek->getPathInfo();
        foreach (self::MUAF as $onek) {
            if (str_starts_with($yol, $onek)) {
                return;
            }
        }

        $ip = (string) $istek->getClientIp();
        foreach ([$this->ozel($istek->getMethod(), $yol), $this->hizGenelLimiter] as $sinir) {
            if (null === $sinir) {
                continue;
            }
            $sonuc = $sinir->create($ip)->consume();
            if (!$sonuc->isAccepted()) {
                $olay->setResponse(new JsonResponse(['error' => 'too_many_requests'], 429, [
                    'Retry-After' => (string) max(1, $sonuc->getRetryAfter()->getTimestamp() - time()),
                ]));

                return;
            }
        }
    }

    private function ozel(string $yontem, string $yol): ?RateLimiterFactory
    {
        if (str_starts_with($yol, '/portal/')) {
            return $this->hizKimlikLimiter;
        }
        if ('POST' !== $yontem) {
            return null;
        }

        return match (true) {
            '/checkout/sessions' === $yol => $this->hizOdemeLimiter,
            '/huni/ek-satis' === $yol => $this->hizEkSatisLimiter,
            '/huni/portal-baglantisi' === $yol,
            1 === preg_match('#^/apps/[^/]+/baglama/kod$#', $yol) => $this->hizEpostaLimiter,
            '/huni/giris-baglantisi' === $yol,
            1 === preg_match('#^/apps/[^/]+/(giris/kullan|baglama/dogrula)$#', $yol) => $this->hizKimlikLimiter,
            default => null,
        };
    }
}
