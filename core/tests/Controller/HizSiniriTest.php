<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\Sozlesme;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Herkese açık uçlarda IP başına hız sınırı (App\Guvenlik\HizSiniri). Sınırlar
 * config/packages/rate_limiter.yaml'da; burada her grubun sınırı, aşan isteğin
 * 429 + Retry-After aldığı, başka IP'nin etkilenmediği ve muaf uçlar ölçülür.
 *
 * Süitin geri kalanında sayaçlar bellek içi ve her istekte sıfırlanır
 * (services_resetter ArrayAdapter'ı boşaltır — kernel yeniden başlamasa bile).
 * Burada sayaç havuzu bu teste ait bir dosya havuzuyla değiştirilir ve kernel
 * yeniden başlamaz; sayaçlar istekler arasında birikir. İstekler bilerek geçersiz
 * (bilinmeyen uygulama, anahtarsız): sınır controller'dan önce sayar, Stripe'a
 * ya da postaya gidilmez.
 */
final class HizSiniriTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        static::getContainer()->set('hiz.sayaclari', new FilesystemAdapter(uniqid('hiz', true), 0, sys_get_temp_dir()));
    }

    /** Her istek Stripe'ta oturum açar: IP başına 10 dakikada 10. */
    public function testOdemeOturumu10DakikadaOn(): void
    {
        $this->sinirOlc('POST', '/checkout/sessions', 10, ['app' => 'yok', 'plan' => 'monthly']);
    }

    /**
     * E-posta gönderen iki uç aynı kovayı paylaşır: 10 dakikada 5. Saldırgan
     * birinden ötekine geçerek sınırı ikiye katlayamaz.
     */
    public function testEpostaGonderenUclarOrtakKovadaBes(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            self::assertNotSame(429, $this->istek('POST', '/huni/portal-baglantisi', '203.0.113.30', ['app' => 'yok', 'email' => 'a@example.com']), "portal {$i}");
        }
        for ($i = 0; $i < 2; ++$i) {
            self::assertNotSame(429, $this->istek('POST', '/apps/keepchat/baglama/kod', '203.0.113.30', ['email' => 'a@example.com']), "kod {$i}");
        }

        self::assertSame(429, $this->istek('POST', '/apps/keepchat/baglama/kod', '203.0.113.30', ['email' => 'a@example.com']));
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/baglama/kod', $this->client->getResponse());
        self::assertSame(429, $this->istek('POST', '/huni/portal-baglantisi', '203.0.113.30', ['app' => 'yok', 'email' => 'a@example.com']));
        Sozlesme::yanitiDogrula('POST', '/huni/portal-baglantisi', $this->client->getResponse());
    }

    /** Jeton ve kod tahmini: giriş, doğrulama ve portal jetonu ortak kovada, 10 dakikada 30. */
    public function testKimlikUclariOrtakKovadaOtuz(): void
    {
        $uclar = ['/apps/keepchat/giris/kullan', '/apps/keepchat/baglama/dogrula', '/huni/giris-baglantisi'];
        for ($i = 0; $i < 30; ++$i) {
            self::assertNotSame(429, $this->istek('POST', $uclar[$i % 3], '203.0.113.40', ['jeton' => 'x']), "istek {$i}");
        }

        self::assertSame(429, $this->istek('GET', '/portal/olmayan-jeton', '203.0.113.40'));
        self::assertSame(429, $this->istek('POST', '/apps/keepchat/giris/kullan', '203.0.113.40', ['jeton' => 'x']));
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/giris/kullan', $this->client->getResponse());
    }

    /** Kayıtlı karttan ödeme alır: 10 dakikada 10. */
    public function testEkSatis10DakikadaOn(): void
    {
        $this->sinirOlc('POST', '/huni/ek-satis', 10, ['oturum' => 'cs_test_yok', 'urun' => 'yok']);
    }

    /** Diğer her şey, var olmayan adresler dahil: dakikada 300. */
    public function testGenelSinirDakikadaUcYuz(): void
    {
        for ($i = 0; $i < 300; ++$i) {
            self::assertNotSame(429, $this->istek('GET', '/huni/olmayan', '203.0.113.50'), "istek {$i}");
        }

        self::assertSame(429, $this->istek('GET', '/huni/olmayan', '203.0.113.50'));
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        // Yönlendirmeden önce sayar: var olmayan adres de sınırda (tarama).
        self::assertSame(429, $this->istek('GET', '/olmayan-adres', '203.0.113.50'));
        self::assertNotSame(429, $this->istek('GET', '/huni/olmayan', '203.0.113.51'));
    }

    /** İzleme, imzalı webhook'lar ve CORS ön isteği sayılmaz. */
    public function testMuafUclarSayilmaz(): void
    {
        for ($i = 0; $i < 301; ++$i) {
            self::assertSame(200, $this->istek('GET', '/health', '203.0.113.60'), "health {$i}");
        }
        for ($i = 0; $i < 301; ++$i) {
            self::assertNotSame(429, $this->istek('POST', '/webhooks/stripe', '203.0.113.60'), "webhook {$i}");
        }
        for ($i = 0; $i < 301; ++$i) {
            // Tarayıcının ön isteği (köken + yöntem): CORS katmanı sınırdan önce yanıtlar.
            $this->client->request('OPTIONS', '/checkout/sessions', [], [], [
                'REMOTE_ADDR' => '172.18.0.5',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.60',
                'HTTP_ORIGIN' => 'https://huni.test',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            ]);
            self::assertSame(200, $this->client->getResponse()->getStatusCode(), "options {$i}");
        }
    }

    /**
     * @param array<string, mixed> $govde
     */
    private function sinirOlc(string $yontem, string $yol, int $sinir, array $govde): void
    {
        for ($i = 0; $i < $sinir; ++$i) {
            self::assertNotSame(429, $this->istek($yontem, $yol, '203.0.113.20', $govde), "istek {$i}");
        }

        self::assertSame(429, $this->istek($yontem, $yol, '203.0.113.20', $govde));
        $yanit = $this->client->getResponse();
        self::assertGreaterThanOrEqual(1, (int) $yanit->headers->get('Retry-After'));
        Sozlesme::yanitiDogrula($yontem, $yol, $yanit);
        self::assertNotSame(429, $this->istek($yontem, $yol, '203.0.113.21', $govde), 'başka IP etkilenmez');
    }

    /**
     * Üretimdeki gibi vekil arkasından: gerçek IP X-Forwarded-For'da (Ç34).
     *
     * @param array<string, mixed>|null $govde
     */
    private function istek(string $yontem, string $yol, string $ip, ?array $govde = null): int
    {
        $this->client->request($yontem, $yol, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_FOR' => $ip,
        ], null === $govde ? null : (string) json_encode($govde));

        return $this->client->getResponse()->getStatusCode();
    }
}
