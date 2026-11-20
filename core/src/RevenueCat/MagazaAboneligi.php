<?php

declare(strict_types=1);

namespace App\RevenueCat;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * G2 (Ç159): kişinin uygulama mağazasındaki (App Store, Play…) aboneliği ne
 * zaman bitiyor ve yenilenecek mi — RevenueCat REST v2'den okunur. Geçiş
 * akışında web aboneliği bu tarihte başlar (çift ödeme yok). RevenueCat'te
 * müşteri kimliği = kişi UUID'i (uygulama `Purchases.logIn(kisi)` yapar).
 * Web faturaları (Stripe…) sayılmaz: geçirilecek olan mağaza aboneliği.
 */
final readonly class MagazaAboneligi
{
    /** Web faturası olan RevenueCat mağazaları — mağaza aboneliği sayılmaz. */
    public const WEB_MAGAZALARI = ['stripe', 'rc_billing', 'paddle', 'paypal'];
    /** Bu yenileme durumlarında dönem sonunda yeniden ücret çekilir. */
    private const YENILENIR = ['will_renew', 'will_change_product', 'requires_price_increase_consent', 'has_already_renewed'];

    public function __construct(
        private HttpClientInterface $http,
        private LoggerInterface $logger,
        /** @var array<string, array{proje: string, anahtar: string}> uygulama → RevenueCat proje kimliği + v2 gizli anahtarı */
        #[Autowire('%revenuecat_v2%')]
        private array $ayarlar,
        #[Autowire('%env(REVENUECAT_API_BASE)%')]
        private string $apiTabani,
    ) {
    }

    public function acik(string $app): bool
    {
        $a = $this->ayarlar[$app] ?? null;

        return null !== $a && '' !== $a['proje'] && '' !== $a['anahtar'];
    }

    /**
     * @return array{bitis: \DateTimeImmutable, yenilenecek: bool}|'kapali'|'ulasilamadi'|null
     *                                                                                         null: erişim veren mağaza aboneliği yok
     */
    public function oku(string $app, string $kisi): array|string|null
    {
        if (!$this->acik($app)) {
            return 'kapali';
        }
        $url = rtrim($this->apiTabani, '/').'/v2/projects/'.rawurlencode($this->ayarlar[$app]['proje'])
            .'/customers/'.rawurlencode($kisi).'/subscriptions?limit=100';
        try {
            $yanit = $this->http->request('GET', $url, [
                'headers' => ['Authorization' => 'Bearer '.$this->ayarlar[$app]['anahtar']],
                'timeout' => 10,
            ]);
            $kod = $yanit->getStatusCode();
            if (404 === $kod) {
                return null;
            }
            if (200 !== $kod) {
                $this->logger->error('RevenueCat mağaza aboneliği okunamadı', ['app' => $app, 'durum' => $kod]);

                return 'ulasilamadi';
            }
            $govde = $yanit->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('RevenueCat mağaza aboneliği okunamadı', ['app' => $app, 'exception' => $e]);

            return 'ulasilamadi';
        }

        $sonuc = null;
        foreach (\is_array($govde['items'] ?? null) ? $govde['items'] : [] as $a) {
            if (!\is_array($a) || true !== ($a['gives_access'] ?? null) || \in_array($a['store'] ?? null, self::WEB_MAGAZALARI, true)) {
                continue;
            }
            // ends_at yalnız "has_already_renewed"da current_period_ends_at'ten ileri (belge); hangisi sonraysa o.
            $ms = max(\is_int($a['current_period_ends_at'] ?? null) ? $a['current_period_ends_at'] : 0, \is_int($a['ends_at'] ?? null) ? $a['ends_at'] : 0);
            if (0 === $ms || (null !== $sonuc && $ms <= $sonuc['ms'])) {
                continue;
            }
            $sonuc = ['ms' => $ms, 'yenilenecek' => \in_array($a['auto_renewal_status'] ?? null, self::YENILENIR, true)];
        }

        return null === $sonuc ? null : [
            'bitis' => (new \DateTimeImmutable('@'.intdiv($sonuc['ms'], 1000)))->setTimezone(new \DateTimeZone('UTC')),
            'yenilenecek' => $sonuc['yenilenecek'],
        ];
    }
}
