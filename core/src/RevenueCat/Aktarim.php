<?php

declare(strict_types=1);

namespace App\RevenueCat;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Stripe aboneliğini RevenueCat'e aktarır (A9): hak kaynağı RevenueCat.
 *
 * - kuyruk: abonelik kaydıyla AYNI işlemde giden kutusuna yazılır — yalnız
 *   abonelik e-postası bu uygulamada bir KİŞİYE bağlıysa (RevenueCat'te
 *   kullanıcı kimliği = kişi UUID'i) ve uygulamanın anahtarı varsa.
 * - gönder: işlemden sonra; RevenueCat yanıt vermezse satır bekler,
 *   `app:revenuecat:gonder` yeniden dener. Kesinti Stripe kaydını durdurmaz.
 */
final readonly class Aktarim
{
    /** Bir satır en çok bu kadar denenir; sonra elle bakılmalı (panel/günlük). */
    public const AZAMI_DENEME = 10;

    public function __construct(
        private Connection $db,
        private HttpClientInterface $http,
        private LoggerInterface $logger,
        /** @var array<string, string> uygulama → RevenueCat Stripe uygulaması açık anahtarı */
        #[Autowire('%revenuecat_anahtarlari%')]
        private array $anahtarlar,
        #[Autowire('%env(REVENUECAT_API_BASE)%')]
        private string $apiTabani,
    ) {
    }

    /** Bu abonelik bağlı bir kişininse kuyruğa al. Çağıranın işlemi içinde. */
    public function aboneligiKuyrugaAl(Connection $db, string $app, string $abonelik): void
    {
        if ('' === ($this->anahtarlar[$app] ?? '')) {
            return;
        }
        $db->executeStatement(
            'INSERT INTO revenuecat_gidenler (app, stripe_subscription_id, kisi_id)
             SELECT s.app, s.stripe_subscription_id, b.kisi_id
             FROM subscriptions s
             JOIN kisiler k ON k.email = lower(s.email)
             JOIN baglantilar b ON b.kisi_id = k.id AND b.app = s.app
             WHERE s.stripe_subscription_id = :sub AND s.app = :app
             LIMIT 1',
            ['sub' => $abonelik, 'app' => $app],
        );
    }

    /** Bağlama sonrası: bu kişinin bu uygulamadaki bütün abonelikleri. Çağıranın işlemi içinde. */
    public function kisiyiKuyrugaAl(Connection $db, string $app, string $kisi): void
    {
        if ('' === ($this->anahtarlar[$app] ?? '')) {
            return;
        }
        $db->executeStatement(
            'INSERT INTO revenuecat_gidenler (app, stripe_subscription_id, kisi_id)
             SELECT s.app, s.stripe_subscription_id, k.id
             FROM subscriptions s JOIN kisiler k ON k.email = lower(s.email)
             WHERE k.id = :kisi AND s.app = :app',
            ['kisi' => $kisi, 'app' => $app],
        );
    }

    /**
     * Bekleyenleri gönderir. Asla atmaz: hata satıra ve günlüğe yazılır.
     *
     * @return array{gonderilen: int, basarisiz: int}
     */
    public function gonder(int $azami = 50): array
    {
        $sonuc = ['gonderilen' => 0, 'basarisiz' => 0];
        $satirlar = $this->db->fetchAllAssociative(
            'SELECT id, app, stripe_subscription_id, kisi_id FROM revenuecat_gidenler
             WHERE gonderildi_at IS NULL AND deneme < :azami ORDER BY id LIMIT :limit',
            ['azami' => self::AZAMI_DENEME, 'limit' => $azami],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
        foreach ($satirlar as $s) {
            $app = \is_string($s['app']) ? $s['app'] : '';
            $hata = $this->tekGonder($this->anahtarlar[$app] ?? '', (string) (\is_string($s['stripe_subscription_id']) ? $s['stripe_subscription_id'] : ''), \is_string($s['kisi_id']) ? $s['kisi_id'] : '');
            if (null === $hata) {
                $this->db->executeStatement('UPDATE revenuecat_gidenler SET gonderildi_at = now(), son_hata = NULL WHERE id = :id', ['id' => $s['id']]);
                ++$sonuc['gonderilen'];
            } else {
                $this->db->executeStatement('UPDATE revenuecat_gidenler SET deneme = deneme + 1, son_hata = :h WHERE id = :id', ['id' => $s['id'], 'h' => mb_substr($hata, 0, 500)]);
                $this->logger->warning('RevenueCat aktarımı başarısız', ['id' => $s['id'], 'hata' => $hata]);
                ++$sonuc['basarisiz'];
            }
        }

        return $sonuc;
    }

    /** @return string|null hata; null başarılı */
    private function tekGonder(string $anahtar, string $abonelik, string $kisi): ?string
    {
        if ('' === $anahtar) {
            return 'uygulamanın RevenueCat anahtarı yok';
        }
        try {
            $yanit = $this->http->request('POST', rtrim($this->apiTabani, '/').'/v1/receipts', [
                'headers' => ['Authorization' => 'Bearer '.$anahtar, 'X-Platform' => 'stripe'],
                'json' => ['app_user_id' => $kisi, 'fetch_token' => $abonelik],
                'timeout' => 10,
            ]);
            $kod = $yanit->getStatusCode();

            return $kod >= 200 && $kod < 300 ? null : "HTTP {$kod}: ".mb_substr($yanit->getContent(false), 0, 300);
        } catch (ExceptionInterface $e) {
            return $e->getMessage();
        }
    }
}
