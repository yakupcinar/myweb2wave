<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * M1a: fiyat kataloğu. Plan panelden açılır → Stripe'ta ürün + fiyat; tutar
 * veritabanında değil Stripe'ta (L10). Fiyat değişimi yeni Stripe fiyatı,
 * eskisi satıştan kalkar. Ödeme ve huni doğrulaması kataloğu okur.
 * Değerler stripe-mock'un bilmediği şeyler olduğu için istek kaydından
 * ölçülür (KayitliStripeIstemcisi); 'monthly' satırı tests/bootstrap.php'de.
 */
final class PanelPlanTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;
    private KayitliStripeIstemcisi $stripe;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement("DELETE FROM planlar WHERE anahtar <> 'monthly'");
        // Fiyat değişimi testi satırı değiştirir; düşerse sonraki koşuya taşınmasın.
        $this->db->executeStatement("UPDATE planlar SET stripe_fiyat = 'price_test_keepchat_aylik' WHERE anahtar = 'monthly'");
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari');
        $onbellek = static::getContainer()->get('fiyat.onbellegi');
        self::assertInstanceOf(CacheInterface::class, $onbellek);
        self::assertTrue(method_exists($onbellek, 'clear') && $onbellek->clear());
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        // Planlar test başına boşaltılmaz (bootstrap); eklenenler başka testin fiyat listesine sızmasın.
        $this->db->executeStatement("DELETE FROM planlar WHERE anahtar <> 'monthly'");
        $this->db->executeStatement("UPDATE planlar SET stripe_fiyat = 'price_test_keepchat_aylik' WHERE anahtar = 'monthly'");
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    public function testGirisYapmadanKapali(): void
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/planlar');
        self::assertResponseRedirects('/panel/giris');
        $this->client->request('POST', '/panel/api/uygulamalar/keepchat/planlar', content: '{}');
        self::assertResponseRedirects('/panel/giris');
        self::assertSame([], $this->stripe->istekler);
    }

    /**
     * Yeni plan: Stripe'ta ürün (adı + app/plan etiketi) ve o ürüne bağlı
     * yinelenen fiyat; 9.99 EUR → 999 küçük birim (elle: 9.99 × 100).
     * Katalog satırı Stripe'ın döndürdüğü kimlikleri taşır; listede fiyat
     * Stripe'tan okunur (stripe-mock sabiti 2000 USD/ay → 20).
     */
    public function testYeniPlanStripetaUrunVeFiyatAcarKatalogaYazar(): void
    {
        $this->girisYap();
        $this->istek('POST', '/panel/api/uygulamalar/keepchat/planlar', $this->jeton(), ['anahtar' => 'yearly', 'ad' => 'Yıllık', 'tutar' => 9.99, 'paraBirimi' => 'EUR', 'aralik' => 'year']);

        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/planlar', $this->client->getResponse());
        $urun = $this->istekBul('post', '/v1/products');
        self::assertSame(['name' => 'Yıllık', 'metadata' => ['app' => 'keepchat', 'plan' => 'yearly']], $urun['params']);
        $fiyat = $this->istekBul('post', '/v1/prices');
        /** @var array{product: string, currency: string, unit_amount: int, recurring: array{interval: string}} $p */
        $p = $fiyat['params'];
        self::assertSame(['eur', 999, ['interval' => 'year']], [$p['currency'], $p['unit_amount'], $p['recurring']]);

        $satir = $this->db->fetchAssociative("SELECT ad, stripe_urun, stripe_fiyat FROM planlar WHERE app = 'keepchat' AND anahtar = 'yearly'");
        self::assertIsArray($satir);
        self::assertSame('Yıllık', $satir['ad']);
        self::assertSame($p['product'], $satir['stripe_urun'], 'fiyat, açılan ürüne bağlı');
        self::assertIsString($satir['stripe_fiyat']);
        self::assertStringStartsWith('price_', $satir['stripe_fiyat']);

        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/planlar');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/planlar', $this->client->getResponse());
        /** @var array{planlar: list<array{anahtar: string, fiyat: array<string, mixed>|null}>} $liste */
        $liste = $this->json();
        self::assertSame(['monthly', 'yearly'], array_column($liste['planlar'], 'anahtar'));
        self::assertSame(['tutar' => 20, 'paraBirimi' => 'USD', 'aralik' => 'month', 'ad' => 'Yıllık'], $liste['planlar'][1]['fiyat']);
    }

    /** Var olan anahtar: Stripe'a hiç gidilmez (sahipsiz ürün açılmaz). */
    public function testVarOlanAnahtarStripeaGitmez(): void
    {
        $this->girisYap();
        $this->istek('POST', '/panel/api/uygulamalar/keepchat/planlar', $this->jeton(), ['anahtar' => 'monthly', 'ad' => 'Yine', 'tutar' => 5, 'paraBirimi' => 'EUR', 'aralik' => 'month']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(["'monthly' planı zaten var."], $this->json()['hatalar']);
        self::assertSame([], $this->stripe->istekler);
    }

    /** Uygulamadaki kontrol kısıtı görünmez kılar (tuzak §4): kısıtlar doğrudan. */
    public function testVeritabaniKisitlari(): void
    {
        $satir = ['app' => 'keepchat', 'anahtar' => 'monthly', 'ad' => 'X', 'stripe_urun' => 'prod_x', 'stripe_fiyat' => 'price_x'];
        try {
            $this->db->insert('planlar', $satir);
            self::fail('aynı uygulamada aynı anahtar ikinci kez yazıldı');
        } catch (UniqueConstraintViolationException) {
        }
        $this->db->insert('planlar', [...$satir, 'app' => 'baska']);
        try {
            $this->db->insert('planlar', [...$satir, 'anahtar' => 'Yearly-Pro']);
            self::fail('biçimsiz anahtar yazıldı');
        } catch (DriverException $e) {
            self::assertStringContainsString('planlar_anahtar_check', $e->getMessage());
        } finally {
            $this->db->executeStatement("DELETE FROM planlar WHERE app = 'baska'");
        }
    }

    /** @return iterable<string, array{array<string, mixed>, non-empty-string}> */
    public static function gecersizler(): iterable
    {
        $tam = ['anahtar' => 'weekly', 'ad' => 'Haftalık', 'tutar' => 4.99, 'paraBirimi' => 'EUR', 'aralik' => 'week'];
        yield 'anahtar büyük harf' => [[...$tam, 'anahtar' => 'Weekly'], 'Anahtar küçük harfle'];
        yield 'ad boş' => [[...$tam, 'ad' => '  '], 'Ad 1–100'];
        yield 'tutar 0' => [[...$tam, 'tutar' => 0], 'Tutar 0'];
        yield 'tutar metin' => [[...$tam, 'tutar' => '4.99'], 'Tutar 0'];
        yield 'üç ondalık' => [[...$tam, 'tutar' => 4.999], 'Tutar en çok 2'];
        yield 'para birimi' => [[...$tam, 'paraBirimi' => 'JPY'], 'Para birimi'];
        yield 'aralık' => [[...$tam, 'aralik' => 'day'], 'Aralık'];
    }

    /**
     * @param array<string, mixed> $govde
     * @param non-empty-string     $hata
     */
    #[DataProvider('gecersizler')]
    public function testGecersizAlanStripeaGitmez(array $govde, string $hata): void
    {
        $this->girisYap();
        $this->istek('POST', '/panel/api/uygulamalar/keepchat/planlar', $this->jeton(), $govde);

        self::assertResponseStatusCodeSame(422);
        /** @var list<string> $hatalar */
        $hatalar = $this->json()['hatalar'];
        self::assertCount(1, $hatalar);
        self::assertStringStartsWith($hata, $hatalar[0]);
        self::assertSame([], $this->stripe->istekler);
    }

    public function testCsrfVeUygulama(): void
    {
        $this->girisYap();
        $govde = ['anahtar' => 'weekly', 'ad' => 'H', 'tutar' => 1, 'paraBirimi' => 'EUR', 'aralik' => 'week'];
        $this->istek('POST', '/panel/api/uygulamalar/keepchat/planlar', '', $govde);
        self::assertResponseStatusCodeSame(403);
        $this->istek('PUT', '/panel/api/uygulamalar/keepchat/planlar/monthly/fiyat', '', ['tutar' => 1]);
        self::assertResponseStatusCodeSame(403);
        $jeton = $this->jeton();
        $this->istek('POST', '/panel/api/uygulamalar/baska/planlar', $jeton, $govde);
        self::assertResponseStatusCodeSame(404);
        $this->istek('PUT', '/panel/api/uygulamalar/keepchat/planlar/olmayan/fiyat', $jeton, ['tutar' => 1]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->stripe->istekler);
    }

    /** Stripe'a ulaşılamazsa plan yazılmaz; mesaj iç hatayı taşımaz. */
    public function testStripeKapaliysaPlanYazilmaz(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        $this->stripe->firlat = new ApiConnectionException('ağ yok');
        $this->istek('POST', '/panel/api/uygulamalar/keepchat/planlar', $jeton, ['anahtar' => 'weekly', 'ad' => 'H', 'tutar' => 1, 'paraBirimi' => 'EUR', 'aralik' => 'week']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Stripe şu an yanıt vermiyor; plan oluşturulmadı.'], $this->json()['hatalar']);
        self::assertSame(0, $this->db->fetchOne("SELECT count(*) FROM planlar WHERE anahtar = 'weekly'"));
    }

    /**
     * Fiyat değişimi: Stripe fiyatı değiştirilemez → aynı üründe, eski
     * fiyatın para birimi ve aralığıyla yeni fiyat; katalog yeni kimliğe,
     * eski fiyat active=false (mevcut aboneler onda kalır).
     */
    public function testFiyatDegisimiYeniFiyatAcarEskisiniKapatir(): void
    {
        $this->girisYap();
        $this->istek('PUT', '/panel/api/uygulamalar/keepchat/planlar/monthly/fiyat', $this->jeton(), ['tutar' => 12.5]);

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/planlar/{anahtar}/fiyat', $this->client->getResponse());
        $yeni = $this->istekBul('post', '/v1/prices');
        /** @var array{product: string, currency: string, unit_amount: int, recurring: array{interval: string}} $p */
        $p = $yeni['params'];
        // stripe-mock'un okunan "eski" fiyatı usd/month; ürün katalogdaki.
        self::assertSame(['prod_test_keepchat', 'usd', 1250, ['interval' => 'month']], [$p['product'], $p['currency'], $p['unit_amount'], $p['recurring']]);
        $kapat = $this->istekBul('post', '/v1/prices/price_test_keepchat_aylik');
        // stripe-php formda boolean'ı metne çevirir; Stripe'a giden budur.
        self::assertSame(['active' => 'false'], $kapat['params']);
        $kimlik = $this->db->fetchOne("SELECT stripe_fiyat FROM planlar WHERE app = 'keepchat' AND anahtar = 'monthly'");
        self::assertNotSame('price_test_keepchat_aylik', $kimlik);
    }

    /** Ödeme ve huni doğrulaması kataloğu okur: yeni planla ödeme açılır, katalogda olmayan plan reddedilir. */
    public function testOdemeVeHuniKataloguOkur(): void
    {
        $this->db->insert('planlar', ['app' => 'keepchat', 'anahtar' => 'weekly', 'ad' => 'H', 'stripe_urun' => 'prod_h', 'stripe_fiyat' => 'price_haftalik']);
        $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'weekly']);
        self::assertResponseStatusCodeSame(201);
        /** @var array{line_items: list<array{price: string}>} $oturum */
        $oturum = $this->istekBul('post', '/v1/checkout/sessions')['params'];
        self::assertSame('price_haftalik', $oturum['line_items'][0]['price']);
        $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'yearly']);
        self::assertResponseStatusCodeSame(422);

        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $tanim = fn (string $plan): array => ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [], 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => $plan],
        ]];
        self::assertSame([], $huniler->hatalar('keepchat', $tanim('weekly')));
        self::assertNotSame([], $huniler->hatalar('keepchat', $tanim('yearly')));

        // Düzenleyicinin plan listesi de katalogdan (Puck açılır listesi).
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null));
        $this->girisYap();
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/huniler/genel/duzenleyici');
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['monthly', 'weekly'], $this->json()['planlar']);
    }

    /** Eski ortam değişkenindeki fiyat konsoldan kataloğa: ürün kimliği fiyattan okunur. */
    public function testIceAktarFiyatinUrununuYazar(): void
    {
        $komut = (new Application($this->client->getKernel()))->find('app:plan-ice-aktar');
        $test = new CommandTester($komut);
        self::assertSame(0, $test->execute(['app' => 'keepchat', 'anahtar' => 'eski', 'fiyat' => 'price_eski', '--ad' => 'Eski aylık']));
        $satir = $this->db->fetchAssociative("SELECT ad, stripe_urun, stripe_fiyat FROM planlar WHERE anahtar = 'eski'");
        self::assertIsArray($satir);
        self::assertSame('Eski aylık', $satir['ad']);
        self::assertIsString($satir['stripe_urun']);
        self::assertStringStartsWith('prod_', $satir['stripe_urun']);
        self::assertSame(1, $test->execute(['app' => 'keepchat', 'anahtar' => 'eski', 'fiyat' => 'price_eski']), 'ikinci kez yazılmaz');
        self::assertSame(2, $test->execute(['app' => 'baska', 'anahtar' => 'eski', 'fiyat' => 'price_eski']));
    }

    /** @return array{method: string, url: string, params: array<mixed>} */
    private function istekBul(string $yontem, string $yol): array
    {
        foreach ($this->stripe->istekler as $i) {
            if ($yontem === $i['method'] && str_ends_with($i['url'], $yol)) {
                return $i;
            }
        }
        self::fail("Stripe'a {$yontem} {$yol} gitmedi");
    }

    /** @param array<string, mixed> $govde */
    private function istek(string $yontem, string $adres, string $jeton, array $govde): void
    {
        $this->client->request($yontem, $adres, server: ['HTTP_X_CSRF_TOKEN' => $jeton], content: (string) json_encode($govde));
    }

    private function jeton(): string
    {
        $this->client->request('GET', '/panel/anahtarlar');
        self::assertResponseIsSuccessful();

        return (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        /** @var array<string, mixed> $v */
        $v = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $v;
    }
}
