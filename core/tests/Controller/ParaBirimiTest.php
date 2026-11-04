<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * M1c: sabit tutarlı çok para birimi. Kararlar: ek para birimleri planın
 * Stripe fiyatında (`currency_options`), değişince yeni fiyat (mevcut
 * aboneler eskisinde); ziyaretçinin ülkesi (test: `ulke`) planda olan para
 * birimini seçer, yoksa ana birim; paywall ve ödeme aynı seçimi kullanır,
 * ödemede `currency` zorlanır ve Adaptive Pricing kapalı; giriş ücreti tek
 * para birimli. stripe-mock'un ana fiyatı 2000 USD/ay.
 */
final class ParaBirimiTest extends WebTestCase
{
    private const FIYAT = 'price_test_keepchat_aylik';

    private KernelBrowser $client;
    private Connection $db;
    private KayitliStripeIstemcisi $stripe;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->sifirla();
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari');
        $onbellek = static::getContainer()->get('fiyat.onbellegi');
        self::assertInstanceOf(CacheInterface::class, $onbellek);
        self::assertTrue(method_exists($onbellek, 'clear') && $onbellek->clear());
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        $this->sifirla();
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    /**
     * Ek para birimleri: aynı üründe, aynı ana tutar/aralıkla YENİ fiyat +
     * `currency_options` (349.99 TRY → 34999, 8.99 EUR → 899; elle); eski
     * fiyat satıştan kalkar, katalog yenisinde.
     */
    public function testEkParaBirimleriYeniFiyatAcar(): void
    {
        $this->girisYap();
        $this->koy(['TRY' => 349.99, 'EUR' => 8.99]);

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/planlar/{anahtar}/para-birimleri', $this->client->getResponse());
        $p = $this->istekBul('post', '/v1/prices')['params'];
        self::assertSame(['prod_test_keepchat', 'usd', 2000, ['interval' => 'month']], [$p['product'], $p['currency'], $p['unit_amount'], $p['recurring']]);
        self::assertSame(['try' => ['unit_amount' => 34999], 'eur' => ['unit_amount' => 899]], $p['currency_options']);
        self::assertSame(['active' => 'false'], $this->istekBul('post', '/v1/prices/'.self::FIYAT)['params']);
        self::assertNotSame(self::FIYAT, $this->db->fetchOne("SELECT stripe_fiyat FROM planlar WHERE anahtar = 'monthly'"));
    }

    /** Boş eşleme ek para birimlerini kaldırır: yeni fiyatta currency_options yok. */
    public function testBosEslemeKaldirir(): void
    {
        $this->girisYap();
        $this->koy([]);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayNotHasKey('currency_options', $this->istekBul('post', '/v1/prices')['params']);
    }

    /** @return iterable<string, array{mixed, non-empty-string}> */
    public static function gecersizler(): iterable
    {
        yield 'desteklenmeyen' => [['JPY' => 1000], 'Para birimi şunlardan'];
        yield 'sıfır' => [['TRY' => 0], 'TRY: Tutar'];
        yield '3 ondalık' => [['EUR' => 8.999], '2 ondalık'];
        yield 'eşleme değil' => ['TRY', 'eşlemesi olmalı'];
    }

    #[DataProvider('gecersizler')]
    public function testGecersizStripeaGitmez(mixed $tutarlar, string $hata): void
    {
        $this->girisYap();
        $this->koy($tutarlar);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString($hata, $this->hatalar());
        self::assertSame([], $this->stripe->istekler);
    }

    /** Ana para birimi ek olarak verilemez (tutarı "Fiyatı değiştir"le); yeni fiyat açılmaz. */
    public function testAnaParaBirimiEkOlamaz(): void
    {
        $this->girisYap();
        $this->koy(['USD' => 9.99]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('USD planın ana para birimi', $this->hatalar());
        self::assertSame([], $this->yazmalar());
    }

    /** Giriş ücreti tek para birimli: iki yönden de reddedilir. */
    public function testGirisTeklifiTekParaBirimli(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        $this->db->executeStatement("UPDATE planlar SET teklif_turu = 'giris', teklif_gun = 3, stripe_teklif = 'price_giris' WHERE anahtar = 'monthly'");
        $this->koy(['TRY' => 349.99], $jeton);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('tek para birimiyle', $this->hatalar());
        $this->sifirla();

        $this->stripe->yanitlar['/v1/prices/'.self::FIYAT] = self::fiyat(['try' => ['unit_amount' => 34999]]);
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/planlar/monthly/teklif', server: ['HTTP_X_CSRF_TOKEN' => $jeton], content: '{"tur":"giris","gun":3,"tutar":0.99}');
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('tek para birimiyle', $this->hatalar());
        self::assertSame([], $this->yazmalar());
    }

    /** Ana tutar değişince ek para birimleri yeni fiyata aynen geçer. */
    public function testFiyatDegisimiEkleriTasir(): void
    {
        $this->stripe->yanitlar['/v1/prices/'.self::FIYAT] = self::fiyat(['try' => ['unit_amount' => 34999]]);
        $this->girisYap();
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/planlar/monthly/fiyat', server: ['HTTP_X_CSRF_TOKEN' => $this->jeton()], content: '{"tutar":12.5}');

        self::assertResponseStatusCodeSame(200);
        $p = $this->istekBul('post', '/v1/prices')['params'];
        self::assertSame([1250, ['try' => ['unit_amount' => 34999]]], [$p['unit_amount'], $p['currency_options']]);
    }

    /**
     * Gösterim: ülkenin para birimi planda varsa onunla (TR → TRY 349.99),
     * yoksa ana birim (DE: EUR planda yok; JP: desteklenmiyor). Panel tüm
     * ekleri görür. Yanıt ortak önbelleğe alınmaz.
     */
    public function testHuniFiyatiUlkeyeGore(): void
    {
        $this->stripe->yanitlar['/v1/prices/'.self::FIYAT] = self::fiyat(['try' => ['unit_amount' => 34999]]);
        $this->huniYayinla();

        foreach (['TR' => [349.99, 'TRY'], 'DE' => [20, 'USD'], 'JP' => [20, 'USD'], '' => [20, 'USD']] as $ulke => [$tutar, $para]) {
            $this->client->request('GET', '/huni/keepchat'.('' === $ulke ? '' : "?ulke={$ulke}"));
            self::assertResponseStatusCodeSame(200);
            Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
            /** @var array{fiyatlar: array{monthly: array<string, mixed>}} $j */
            $j = $this->json();
            self::assertSame(['tutar' => $tutar, 'paraBirimi' => $para, 'aralik' => 'month'], $j['fiyatlar']['monthly'], "ülke {$ulke}");
            self::assertResponseHeaderSame('Cache-Control', 'max-age=60, private');
        }

        $this->girisYap();
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/planlar');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/planlar', $this->client->getResponse());
        /** @var array{planlar: list<array{fiyat: array<string, mixed>}>} $liste */
        $liste = $this->json();
        self::assertSame(['TRY' => 349.99], $liste['planlar'][0]['fiyat']['ekler']);
    }

    /**
     * Ödeme: paywall'daki seçimin aynısı zorlanır (TR → try), Adaptive
     * Pricing her oturumda kapalı; ülkenin para birimi desteklenmiyorsa
     * para birimi verilmez ve fiyat okunmaz (Stripe ana birimi açar).
     */
    public function testOdemeParaBiriminiZorlarAdaptiveKapali(): void
    {
        $this->stripe->yanitlar['/v1/prices/'.self::FIYAT] = self::fiyat(['try' => ['unit_amount' => 34999]]);

        $tr = $this->odeme(['ulke' => 'TR']);
        self::assertSame('try', $tr['currency']);
        self::assertSame(['enabled' => 'false'], $tr['adaptive_pricing']);

        $this->stripe->istekler = [];
        $de = $this->odeme(['ulke' => 'DE']);
        self::assertSame('usd', $de['currency'], 'EUR planda yok → ana birim zorlanır');

        $this->stripe->istekler = [];
        $jp = $this->odeme(['ulke' => 'JP']);
        self::assertArrayNotHasKey('currency', $jp);
        self::assertSame(['enabled' => 'false'], $jp['adaptive_pricing']);
        // Yalnız oturum isteği; özellik doğrudan okunsa PHPStan az önceki [] atamasından boş sanıyor.
        self::assertSame(['post'], array_column($this->kayit(), 'method'), 'fiyat okunmadı');
    }

    private function sifirla(): void
    {
        $this->db->executeStatement("UPDATE planlar SET stripe_fiyat = '".self::FIYAT."', teklif_turu = NULL, teklif_gun = NULL, teklif_yuzde = NULL, stripe_teklif = NULL WHERE app = 'keepchat' AND anahtar = 'monthly'");
    }

    /**
     * stripe-mock'un sabit fiyatı + ek para birimleri (mock currency_options döndürmez).
     *
     * @param array<string, array{unit_amount: int}> $ekler
     *
     * @return array<string, mixed>
     */
    private static function fiyat(array $ekler): array
    {
        return ['id' => self::FIYAT, 'object' => 'price', 'product' => 'prod_test_keepchat', 'currency' => 'usd', 'unit_amount' => 2000, 'recurring' => ['interval' => 'month', 'interval_count' => 1], 'currency_options' => ['usd' => ['unit_amount' => 2000]] + $ekler];
    }

    private function huniYayinla(): void
    {
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null, 'fitness'));
        self::assertIsInt($huniler->yayinla('keepchat', 'genel'));
    }

    /**
     * @param array<string, string> $ek
     *
     * @return array<string, mixed>
     */
    private function odeme(array $ek): array
    {
        $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly'] + $ek);
        self::assertResponseStatusCodeSame(201);
        /** @var array<string, mixed> $p */
        $p = $this->istekBul('post', '/v1/checkout/sessions')['params'];

        return $p;
    }

    private function koy(mixed $tutarlar, ?string $jeton = null): void
    {
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/planlar/monthly/para-birimleri', server: ['HTTP_X_CSRF_TOKEN' => $jeton ?? $this->jeton()], content: (string) json_encode(['tutarlar' => $tutarlar]));
    }

    private function hatalar(): string
    {
        $h = $this->json()['hatalar'] ?? null;
        self::assertIsArray($h);

        return implode(' ', array_map(static fn (mixed $x): string => \is_string($x) ? $x : '', $h));
    }

    /** @return list<array{method: string, url: string, params: array<mixed>}> */
    private function kayit(): array
    {
        return $this->stripe->istekler;
    }

    /** @return list<array{method: string, url: string, params: array<mixed>}> */
    private function yazmalar(): array
    {
        return array_values(array_filter($this->stripe->istekler, static fn (array $i): bool => 'get' !== $i['method']));
    }

    /** @return array{method: string, url: string, params: array<mixed>} */
    private function istekBul(string $yontem, string $yol): array
    {
        foreach ($this->stripe->istekler as $i) {
            if ($yontem === $i['method'] && str_ends_with(explode('?', $i['url'])[0], $yol)) {
                return $i;
            }
        }
        self::fail("Stripe'a {$yontem} {$yol} gitmedi");
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
        /** @var array<string, mixed>|null $j */
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($j);

        return $j;
    }
}
