<?php

declare(strict_types=1);

namespace App\Tests\Panel;

use App\Panel\YoneticiSaglayici;
use App\RevenueCat\Aktarim;
use App\RevenueCat\MagazaAboneligi;
use App\Tests\Support\Sozlesme;
use App\Uygulama\BaglantiDurumu;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * BR1: "Uygulama bağlantısı" listesi çalışan sistemden ölçülür. KeepChat'te
 * bağlama kodu yazılmış ama üretimde kapalıydı (Ç158) ve hiçbir yerde
 * görünmüyordu — "uygulamanın sunucusu bağlandı" adımı yalnız GEÇERLİ anahtarlı
 * istekle tamamlanır. Anahtarların kendisi yanıtta yok.
 */
final class BaglantiDurumuTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE uygulama_istekleri, revenuecat_gidenler, baglantilar, giris_jetonlari, kisiler, subscriptions CASCADE');
    }

    public function testBaslangictaOlculenDurum(): void
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/baglanti');
        self::assertResponseRedirects('/panel/giris');
        $this->girisYap();
        $this->client->request('GET', '/panel/api/uygulamalar/yok/baglanti');
        self::assertResponseStatusCodeSame(404);

        // .env.test: uygulama anahtarı, RevenueCat Stripe anahtarı ve v2 (G2) dolu; hiçbir şey olmamış.
        self::assertSame([
            'anahtar' => ['tamam' => true],
            'istek' => ['tamam' => false, 'son' => null],
            'baglama' => ['tamam' => false, 'sayi' => 0, 'son' => null],
            'girisBaglantisi' => ['tamam' => false, 'son' => null],
            'revenuecat' => ['tamam' => false, 'acik' => true, 'gonderilen' => 0, 'bekleyen' => 0],
            'magaza' => ['tamam' => true],
        ], $this->durumIste());
        $govde = (string) $this->client->getResponse()->getContent();
        foreach (['test-anahtar-keepchat', 'strp_test_revenuecat', 'sk_test_v2'] as $gizli) {
            self::assertStringNotContainsString($gizli, $govde);
        }
    }

    /** Yanlış anahtar sayılmaz; geçerli istek zamanı yazar; dakikada en çok bir yazma. */
    public function testUygulamaninSunucusuGecerliAnahtarlaGelince(): void
    {
        $this->girisYap();
        $this->uygulamaIstegi('yanlis-anahtar');
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['tamam' => false, 'son' => null], $this->durumIste()['istek']);

        $this->uygulamaIstegi('test-anahtar-keepchat');
        self::assertResponseStatusCodeSame(404); // bağlanmamış kullanıcı — istek yine de geldi
        self::assertTrue($this->durumIste()['istek']['tamam']);

        $this->db->executeStatement("UPDATE uygulama_istekleri SET son_istek_at = '2026-11-01 10:00:30+00'");
        $this->uygulamaIstegi('test-anahtar-keepchat');
        self::assertNotSame('2026-11-01T10:00:30+00:00', $this->durumIste()['istek']['son'], 'bir dakikadan eski: güncellenir');

        $this->db->executeStatement("UPDATE uygulama_istekleri SET son_istek_at = now() - interval '30 seconds'");
        $once = $this->db->fetchOne('SELECT son_istek_at FROM uygulama_istekleri');
        $this->uygulamaIstegi('test-anahtar-keepchat');
        self::assertSame($once, $this->db->fetchOne('SELECT son_istek_at FROM uygulama_istekleri'), 'bir dakika dolmadan yazılmaz');
    }

    public function testBaglamaGirisVeRevenueCatSayilari(): void
    {
        $this->girisYap();
        $kisi = $this->db->fetchOne("INSERT INTO kisiler (email) VALUES ('a@example.com') RETURNING id");
        $this->db->insert('baglantilar', ['app' => 'keepchat', 'harici_kullanici' => 'u1', 'kisi_id' => $kisi, 'created_at' => '2026-11-10 09:00:00+00']);
        $this->db->insert('baglantilar', ['app' => 'keepchat', 'harici_kullanici' => 'u2', 'kisi_id' => $kisi, 'created_at' => '2026-11-12 09:00:00+00']);
        $this->db->insert('baglantilar', ['app' => 'baska', 'harici_kullanici' => 'u3', 'kisi_id' => $kisi, 'created_at' => '2026-11-15 09:00:00+00']);
        $this->db->insert('giris_jetonlari', ['app' => 'keepchat', 'email' => 'a@example.com', 'stripe_checkout_session_id' => 'cs_1', 'ozet' => 'o1',
            'son_kullanma' => '2026-11-12 09:00:00+00', 'kullanildi_at' => '2026-11-11 09:00:00+00']);
        $this->db->insert('giris_jetonlari', ['app' => 'keepchat', 'email' => 'a@example.com', 'stripe_checkout_session_id' => 'cs_2', 'ozet' => 'o2',
            'son_kullanma' => '2026-11-12 09:00:00+00']);
        foreach (['sub_1', 'sub_2', 'sub_3'] as $s) {
            $this->db->insert('subscriptions', ['stripe_subscription_id' => $s, 'stripe_customer_id' => 'cus_1', 'status' => 'active', 'email' => 'a@example.com', 'app' => 'keepchat']);
        }
        $this->db->insert('revenuecat_gidenler', ['app' => 'keepchat', 'stripe_subscription_id' => 'sub_1', 'kisi_id' => $kisi, 'gonderildi_at' => '2026-11-12 09:00:00+00']);
        $this->db->insert('revenuecat_gidenler', ['app' => 'keepchat', 'stripe_subscription_id' => 'sub_2', 'kisi_id' => $kisi, 'gonderildi_at' => '2026-11-12 09:00:00+00']);
        $this->db->insert('revenuecat_gidenler', ['app' => 'keepchat', 'stripe_subscription_id' => 'sub_3', 'kisi_id' => $kisi, 'son_hata' => 'zaman aşımı']);

        $d = $this->durumIste();
        self::assertSame(['tamam' => true, 'sayi' => 2, 'son' => '2026-11-12T09:00:00+00:00'], $d['baglama']);
        self::assertSame(['tamam' => true, 'son' => '2026-11-11T09:00:00+00:00'], $d['girisBaglantisi']);
        self::assertSame(['tamam' => true, 'acik' => true, 'gonderilen' => 2, 'bekleyen' => 1], $d['revenuecat']);
    }

    /** Anahtarı boş uygulama (yeni eklenmiş, sunucuya girilmemiş) "yapılmadı" görünür. */
    public function testAnahtarBosken(): void
    {
        $aktarim = static::getContainer()->get(Aktarim::class);
        $magaza = static::getContainer()->get(MagazaAboneligi::class);
        self::assertInstanceOf(Aktarim::class, $aktarim);
        self::assertInstanceOf(MagazaAboneligi::class, $magaza);
        $durum = new BaglantiDurumu($this->db, ['keepchat' => ''], $aktarim, $magaza);
        self::assertSame(['tamam' => false], $durum->durum('keepchat')['anahtar']);
        self::assertSame(['tamam' => false], (new BaglantiDurumu($this->db, [], $aktarim, $magaza))->durum('keepchat')['anahtar']);
    }

    /** @return array<string, array<string, mixed>> */
    private function durumIste(): array
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/baglanti');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/baglanti', $this->client->getResponse());
        /** @var array<string, array<string, mixed>> $d */
        $d = json_decode((string) $this->client->getResponse()->getContent(), true);

        return $d;
    }

    private function uygulamaIstegi(string $anahtar): void
    {
        $this->client->request('GET', '/apps/keepchat/kullanicilar/u-yok/hak', [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$anahtar]);
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }
}
