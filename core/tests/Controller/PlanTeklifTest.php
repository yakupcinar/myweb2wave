<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Panel\YoneticiSaglayici;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\DataProvider;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * M1b: planın teklifi. Kararlar: teklif katalogda, plana bağlı (en çok bir);
 * ödeme oturumu teklifi istemciden değil katalogdan kurar; ücretli giriş =
 * deneme günleri + tek seferlik ücret (kullanıcı 2026-11-04, Subscription
 * Schedule reddedildi); deneme kartlı; indirim tek dönemlik yüzde kuponu;
 * teklifi gösterilemeyen planın fiyatı da gösterilmez. Değerler istek
 * kaydından (stripe-mock değerleri bilmez).
 */
final class PlanTeklifTest extends WebTestCase
{
    private const GIRIS_FIYATI = 'price_giris_test';

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
        $onbellek = static::getContainer()->get('fiyat.onbellegi');
        self::assertInstanceOf(CacheInterface::class, $onbellek);
        self::assertTrue(method_exists($onbellek, 'clear') && $onbellek->clear());
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        // Ortak 'monthly' satırı başka testlerin ödemesine teklifle sızmasın.
        $this->sifirla();
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    /** Deneme Stripe'ta nesne açmaz; satır + listede teklif (sözleşme). */
    public function testDenemeKatalogaYazilirListedeGorunur(): void
    {
        $this->girisYap();
        $this->teklif(['tur' => 'deneme', 'gun' => 7]);

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/planlar/{anahtar}/teklif', $this->client->getResponse());
        self::assertSame([], $this->yazmalar());
        self::assertSame(['deneme', 7, null, null], $this->satir());
        /** @var array{planlar: list<array{fiyat: array<string, mixed>}>} $liste */
        $liste = $this->json();
        self::assertSame(['tur' => 'deneme', 'gun' => 7], $liste['planlar'][0]['fiyat']['teklif']);
    }

    /**
     * Giriş ücreti: planın ürününde, planın para biriminde TEK SEFERLİK
     * fiyat (recurring yok); 0.99 → 99 (elle). stripe-mock'un ana fiyatı usd.
     */
    public function testGirisTekSeferlikFiyatAcar(): void
    {
        $this->girisYap();
        $this->teklif(['tur' => 'giris', 'gun' => 3, 'tutar' => 0.99]);

        self::assertResponseStatusCodeSame(200);
        $fiyat = $this->istekBul('post', '/v1/prices')['params'];
        self::assertSame('prod_test_keepchat', $fiyat['product']);
        self::assertSame(['usd', 99], [$fiyat['currency'], $fiyat['unit_amount']]);
        self::assertArrayNotHasKey('recurring', $fiyat);
        [$tur, $gun, $yuzde, $stripe] = $this->satir();
        self::assertSame(['giris', 3, null], [$tur, $gun, $yuzde]);
        self::assertIsString($stripe);
        self::assertStringStartsWith('price_', $stripe);
    }

    public function testIndirimTekDonemlikYuzdeKuponuAcar(): void
    {
        $this->girisYap();
        $this->teklif(['tur' => 'indirim', 'yuzde' => 50]);

        self::assertResponseStatusCodeSame(200);
        $kupon = $this->istekBul('post', '/v1/coupons')['params'];
        // stripe-php formda sayıyı olduğu gibi gönderir.
        self::assertSame([50, 'once'], [$kupon['percent_off'], $kupon['duration']]);
        [$tur, $gun, $yuzde, $stripe] = $this->satir();
        self::assertSame(['indirim', null, 50], [$tur, $gun, $yuzde]);
        self::assertIsString($stripe);
    }

    /** Teklif değişince eski Stripe nesnesi kapanır: giriş fiyatı active=false, kupon silinir. */
    public function testDegisinceEskisiKapanirKaldirinca(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        $this->teklif(['tur' => 'giris', 'gun' => 3, 'tutar' => 1], $jeton);
        $giris = $this->satir()[3];
        self::assertIsString($giris);
        $this->teklif(['tur' => 'indirim', 'yuzde' => 20], $jeton);
        self::assertSame(['active' => 'false'], $this->istekBul('post', "/v1/prices/{$giris}")['params']);
        $kupon = $this->satir()[3];
        self::assertIsString($kupon);

        $this->teklif(['tur' => null], $jeton);
        self::assertResponseStatusCodeSame(200);
        $this->istekBul('delete', "/v1/coupons/{$kupon}");
        self::assertSame([null, null, null, null], $this->satir());
    }

    /** @return iterable<string, array{array<string, mixed>, non-empty-string}> */
    public static function gecersizler(): iterable
    {
        yield 'tür' => [['tur' => 'hediye'], 'Teklif türü'];
        yield 'gün 0' => [['tur' => 'deneme', 'gun' => 0], 'Gün 1–90'];
        yield 'gün 91' => [['tur' => 'deneme', 'gun' => 91], 'Gün 1–90'];
        yield 'gün metin' => [['tur' => 'deneme', 'gun' => '7'], 'Gün 1–90'];
        yield 'girişte tutar yok' => [['tur' => 'giris', 'gun' => 3], 'Tutar'];
        yield 'giriş 3 ondalık' => [['tur' => 'giris', 'gun' => 3, 'tutar' => 0.999], '2 ondalık'];
        yield 'yüzde 100' => [['tur' => 'indirim', 'yuzde' => 100], 'yüzdesi 1–99'];
        yield 'denemede yüzde' => [['tur' => 'deneme', 'gun' => 7, 'yuzde' => 10], 'yüzde fazla'];
        yield 'indirimde gün' => [['tur' => 'indirim', 'yuzde' => 10, 'gun' => 7], 'gün fazla'];
        yield 'teklifsizde gün' => [['tur' => null, 'gun' => 7], 'Teklifsiz'];
    }

    /** @param array<string, mixed> $govde */
    #[DataProvider('gecersizler')]
    public function testGecersizTeklifStripeaGitmez(array $govde, string $hata): void
    {
        $this->girisYap();
        $this->teklif($govde);

        self::assertResponseStatusCodeSame(422);
        $hatalar = $this->json()['hatalar'];
        self::assertIsArray($hatalar);
        self::assertStringContainsString($hata, implode(' ', array_map(static fn (mixed $h): string => \is_string($h) ? $h : '', $hatalar)));
        self::assertSame([], $this->stripe->istekler);
        self::assertSame([null, null, null, null], $this->satir());
    }

    public function testBilinmeyenPlan404CsrfVeStripeKapali(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/planlar/yok/teklif', server: ['HTTP_X_CSRF_TOKEN' => $jeton], content: '{"tur":null}');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/planlar/monthly/teklif', server: ['HTTP_X_CSRF_TOKEN' => 'yanlis'], content: '{"tur":"deneme","gun":7}');
        self::assertResponseStatusCodeSame(403);

        $this->stripe->firlat = new ApiConnectionException('ağ yok');
        $this->teklif(['tur' => 'indirim', 'yuzde' => 10], $jeton);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Stripe şu an yanıt vermiyor; teklif değişmedi.'], $this->json()['hatalar']);
        self::assertSame([null, null, null, null], $this->satir());
    }

    /** Uygulamadaki denetim kısıtı görünmez kılar (tuzak §4): yarım teklif DB'de doğrudan. */
    public function testVeritabaniYarimTeklifiReddeder(): void
    {
        foreach ([
            "teklif_turu = 'indirim', teklif_yuzde = 10" => 'planlar_teklif_tutarli',
            "teklif_turu = 'giris', teklif_gun = 3" => 'planlar_teklif_tutarli',
            "teklif_turu = 'deneme', teklif_gun = 3, teklif_yuzde = 5" => 'planlar_teklif_tutarli',
            "teklif_turu = 'deneme', teklif_gun = 91" => 'planlar_teklif_gun_check',
            "teklif_turu = 'hediye', teklif_gun = 3" => 'planlar_teklif_tutarli',
        ] as $set => $kisit) {
            try {
                $this->db->executeStatement("UPDATE planlar SET {$set} WHERE anahtar = 'monthly'");
                self::fail("yazıldı: {$set}");
            } catch (DriverException $e) {
                self::assertStringContainsString($kisit, $e->getMessage(), $set);
            }
        }
    }

    /** Ödeme: deneme kartlı, ücretsiz gün aboneliğe; tek kalem, kupon yok. */
    public function testOdemeDeneme(): void
    {
        $this->yaz("teklif_turu = 'deneme', teklif_gun = 7");
        $o = $this->odeme();

        self::assertSame('always', $o['payment_method_collection']);
        self::assertSame(['metadata' => ['app' => 'keepchat'], 'trial_period_days' => 7], $o['subscription_data']);
        self::assertSame([['price' => 'price_test_keepchat_aylik', 'quantity' => 1]], $o['line_items']);
        self::assertArrayNotHasKey('discounts', $o);
    }

    /** Ödeme: ücretli giriş = deneme günleri + ilk faturada tek seferlik kalem. */
    public function testOdemeGiris(): void
    {
        $this->yaz("teklif_turu = 'giris', teklif_gun = 3, stripe_teklif = '".self::GIRIS_FIYATI."'");
        $o = $this->odeme();

        self::assertSame(['metadata' => ['app' => 'keepchat'], 'trial_period_days' => 3], $o['subscription_data']);
        self::assertSame([['price' => 'price_test_keepchat_aylik', 'quantity' => 1], ['price' => self::GIRIS_FIYATI, 'quantity' => 1]], $o['line_items']);
        self::assertArrayNotHasKey('discounts', $o);
    }

    /** Ödeme: indirim kuponu, deneme yok. */
    public function testOdemeIndirim(): void
    {
        $this->yaz("teklif_turu = 'indirim', teklif_yuzde = 50, stripe_teklif = 'kupon_x'");
        $o = $this->odeme();

        self::assertSame([['coupon' => 'kupon_x']], $o['discounts']);
        self::assertSame(['metadata' => ['app' => 'keepchat']], $o['subscription_data']);
        self::assertIsArray($o['line_items']);
        self::assertCount(1, $o['line_items']);
    }

    /**
     * Gösterim: giriş ücreti Stripe'tan (tek seferlik, planın para biriminde).
     * Yinelenen ya da başka para birimindeyse teklif gösterilemez → planın
     * fiyatı da yok (yanıltmaktansa hiç).
     */
    public function testGirisUcretiGosterimiVeGosterilemezseFiyatYok(): void
    {
        $this->yaz("teklif_turu = 'giris', teklif_gun = 3, stripe_teklif = '".self::GIRIS_FIYATI."'");
        $this->girisYap();

        $this->stripe->yanitlar['/v1/prices/'.self::GIRIS_FIYATI] = ['id' => self::GIRIS_FIYATI, 'object' => 'price', 'unit_amount' => 99, 'currency' => 'usd', 'recurring' => null];
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/planlar');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/planlar', $this->client->getResponse());
        /** @var array{planlar: list<array{fiyat: array<string, mixed>|null}>} $liste */
        $liste = $this->json();
        self::assertSame(['tutar' => 20, 'paraBirimi' => 'USD', 'aralik' => 'month', 'teklif' => ['tur' => 'giris', 'gun' => 3, 'tutar' => 0.99]], $liste['planlar'][0]['fiyat']);

        foreach (['yinelenen' => ['currency' => 'usd', 'recurring' => ['interval' => 'month', 'interval_count' => 1]], 'para birimi' => ['currency' => 'eur', 'recurring' => null]] as $neden => $alanlar) {
            $onbellek = static::getContainer()->get('fiyat.onbellegi');
            self::assertInstanceOf(CacheInterface::class, $onbellek);
            self::assertTrue(method_exists($onbellek, 'clear') && $onbellek->clear());
            $this->stripe->yanitlar['/v1/prices/'.self::GIRIS_FIYATI] = ['id' => self::GIRIS_FIYATI, 'object' => 'price', 'unit_amount' => 99] + $alanlar;
            $this->client->request('GET', '/panel/api/uygulamalar/keepchat/planlar');
            /** @var array{planlar: list<array{fiyat: mixed}>} $liste */
            $liste = $this->json();
            self::assertNull($liste['planlar'][0]['fiyat'], $neden);
        }
    }

    private function sifirla(): void
    {
        $this->db->executeStatement("UPDATE planlar SET teklif_turu = NULL, teklif_gun = NULL, teklif_yuzde = NULL, stripe_teklif = NULL WHERE app = 'keepchat' AND anahtar = 'monthly'");
    }

    private function yaz(string $set): void
    {
        $this->db->executeStatement("UPDATE planlar SET {$set} WHERE app = 'keepchat' AND anahtar = 'monthly'");
    }

    /** @return array<string, mixed> ödeme oturumu isteğinin parametreleri */
    private function odeme(): array
    {
        $this->client->jsonRequest('POST', '/checkout/sessions', ['app' => 'keepchat', 'plan' => 'monthly']);
        self::assertResponseStatusCodeSame(201);
        /** @var array<string, mixed> $p */
        $p = $this->istekBul('post', '/v1/checkout/sessions')['params'];

        return $p;
    }

    /** @return array{mixed, mixed, mixed, mixed} tür, gün, yüzde, stripe */
    private function satir(): array
    {
        $s = $this->db->fetchNumeric("SELECT teklif_turu, teklif_gun, teklif_yuzde, stripe_teklif FROM planlar WHERE app = 'keepchat' AND anahtar = 'monthly'");
        self::assertIsArray($s);

        return [$s[0], $s[1], $s[2], $s[3]];
    }

    /** @return list<array{method: string, url: string, params: array<mixed>}> */
    private function yazmalar(): array
    {
        return array_values(array_filter($this->stripe->istekler, static fn (array $i): bool => 'get' !== $i['method']));
    }

    /** @param array<string, mixed> $govde */
    private function teklif(array $govde, ?string $jeton = null): void
    {
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/planlar/monthly/teklif', server: ['HTTP_X_CSRF_TOKEN' => $jeton ?? $this->jeton()], content: (string) json_encode($govde));
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
