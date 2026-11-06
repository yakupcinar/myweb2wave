<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * M3c (A16): ek ürün kataloğu ve ödeme sonrası tek tık satış. Kararlar:
 * ürün tek seferlik Stripe fiyatı; satış yalnız ödenmiş, bu uygulamanın, son
 * 1 saatteki oturumla, oturumun para biriminde, aboneliğin kayıtlı kartıyla,
 * oturum + ürün başına bir kez; 3D Secure'dan dönen istek satışı kapatır.
 * Stripe yanıtları kayıttan (stripe-mock oturum durumunu bilmez).
 */
final class EkUrunTest extends WebTestCase
{
    private const OTURUM = 'cs_test_ek_satis';
    private const FIYAT = 'price_ek_kocluk';

    private KernelBrowser $client;
    private Connection $db;
    private KayitliStripeIstemcisi $stripe;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE ek_urunler, ek_satislar, huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari');
        $onbellek = static::getContainer()->get('fiyat.onbellegi');
        self::assertInstanceOf(CacheInterface::class, $onbellek);
        self::assertTrue(method_exists($onbellek, 'clear') && $onbellek->clear());
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    /** Panelden: Stripe'ta ürün + TEK SEFERLİK fiyat (19.99 → 1999, elle), katalog satırı; liste sözleşmeye uyar. */
    public function testPaneldenEkUrunTekSeferlikFiyatla(): void
    {
        $this->girisYap();
        $this->client->request('POST', '/panel/api/uygulamalar/keepchat/ek-urunler', server: ['HTTP_X_CSRF_TOKEN' => $this->jeton()], content: (string) json_encode(['anahtar' => 'kocluk', 'ad' => 'Koçluk paketi', 'tutar' => 19.99, 'paraBirimi' => 'EUR']));

        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/ek-urunler', $this->client->getResponse());
        $p = $this->istekBul('post', '/v1/prices')['params'];
        self::assertSame(['eur', 1999], [$p['currency'], $p['unit_amount']]);
        self::assertArrayNotHasKey('recurring', $p);
        self::assertSame('Koçluk paketi', $this->db->fetchOne("SELECT ad FROM ek_urunler WHERE anahtar = 'kocluk'"));

        $this->stripe->istekler = [];
        $this->client->request('POST', '/panel/api/uygulamalar/keepchat/ek-urunler', server: ['HTTP_X_CSRF_TOKEN' => $this->jeton()], content: (string) json_encode(['anahtar' => 'Kötü', 'ad' => '', 'tutar' => 0, 'paraBirimi' => 'JPY']));
        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->yazmalar());
    }

    /** Huni yanıtı ödeme sonrası upsell ürününün adını ve Stripe tutarını taşır; tutar okunamazsa (yinelenen) alan yok. */
    public function testHuniYanitindaUpsellFiyati(): void
    {
        $this->urun();
        $this->yayinla();
        $this->client->request('GET', '/huni/keepchat');
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        self::assertSame(['kocluk' => ['ad' => 'Koçluk', 'tutar' => 19.99, 'paraBirimi' => 'EUR']], $this->json()['ekUrunler'] ?? null);

        $onbellek = static::getContainer()->get('fiyat.onbellegi');
        self::assertInstanceOf(CacheInterface::class, $onbellek);
        self::assertTrue(method_exists($onbellek, 'clear') && $onbellek->clear());
        $this->stripe->yanitlar['get /v1/prices/'.self::FIYAT]['recurring'] = ['interval' => 'month'];
        $this->client->request('GET', '/huni/keepchat');
        self::assertArrayNotHasKey('ekUrunler', $this->json());
    }

    /**
     * Ödendi: aboneliğin kartıyla, oturumun para biriminde, müşteri sayfada
     * (yönlendirmesiz); ikinci tık yeni çekim yapmaz.
     */
    public function testSatisVeIkinciTikYeniCekimYapmaz(): void
    {
        $this->urun();
        $this->oturumYaniti([]);
        $this->stripe->yanitlar['post /v1/payment_intents'] = ['id' => 'pi_1', 'object' => 'payment_intent', 'status' => 'succeeded'];

        $this->sat();
        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('POST', '/huni/ek-satis', $this->client->getResponse());
        self::assertSame(['durum' => 'odendi'], $this->json());
        $pi = $this->istekBul('post', '/v1/payment_intents')['params'];
        self::assertSame([1999, 'eur', 'cus_x', 'pm_x', 'true'], [$pi['amount'], $pi['currency'], $pi['customer'], $pi['payment_method'], $pi['confirm']]);
        self::assertSame(['enabled' => 'true', 'allow_redirects' => 'never'], $pi['automatic_payment_methods']);
        self::assertArrayNotHasKey('off_session', $pi);
        self::assertSame('odendi', $this->db->fetchOne("SELECT durum FROM ek_satislar WHERE oturum = '".self::OTURUM."'"));

        $this->stripe->istekler = [];
        $this->sat();
        self::assertSame(['durum' => 'odendi'], $this->json());
        self::assertSame([], array_filter($this->stripe->istekler, static fn (array $i): bool => str_ends_with($i['url'], '/v1/payment_intents')));
    }

    /** 3D Secure: önce doğrulama sırrı + anahtar; doğrulamadan sonra aynı istek PaymentIntent'i okuyup satışı kapatır. */
    public function testUcBoyutluDogrulamaSonraKapanir(): void
    {
        $this->urun();
        $this->oturumYaniti([]);
        $this->stripe->yanitlar['post /v1/payment_intents'] = ['id' => 'pi_3ds', 'object' => 'payment_intent', 'status' => 'requires_action', 'client_secret' => 'pi_3ds_secret'];

        $this->sat();
        self::assertSame(['durum' => 'dogrulama', 'clientSecret' => 'pi_3ds_secret', 'anahtar' => 'pk_test_ornek'], $this->json());
        Sozlesme::yanitiDogrula('POST', '/huni/ek-satis', $this->client->getResponse());

        $this->stripe->yanitlar['get /v1/payment_intents/pi_3ds'] = ['id' => 'pi_3ds', 'object' => 'payment_intent', 'status' => 'succeeded'];
        $this->sat();
        self::assertSame(['durum' => 'odendi'], $this->json());
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function uygunsuzOturumlar(): iterable
    {
        yield 'eski' => [['created' => time() - 3700], 'session_not_eligible'];
        yield 'başka uygulama' => [['metadata' => ['app' => 'baska']], 'session_not_eligible'];
        yield 'ödenmemiş' => [['payment_status' => 'unpaid'], 'session_not_eligible'];
        yield 'açık' => [['status' => 'open'], 'session_not_eligible'];
        yield 'para birimi' => [['currency' => 'usd'], 'currency_mismatch'];
    }

    /** @param array<string, mixed> $degis */
    #[\PHPUnit\Framework\Attributes\DataProvider('uygunsuzOturumlar')]
    public function testUygunsuzOturumdaCekimYok(array $degis, string $hata): void
    {
        $this->urun();
        $this->oturumYaniti($degis);
        $this->sat();

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => $hata], $this->json());
        self::assertSame([], array_filter($this->stripe->istekler, static fn (array $i): bool => str_ends_with($i['url'], '/v1/payment_intents')));
    }

    public function testKartReddiVeGecersizIstek(): void
    {
        $this->urun();
        $this->oturumYaniti([]);
        $this->stripe->yanitlar['post /v1/payment_intents'] = ['__durum' => 402, 'error' => ['type' => 'card_error', 'code' => 'card_declined', 'message' => 'Kart reddedildi']];
        $this->sat();
        self::assertSame(['error' => 'card_declined'], $this->json());
        self::assertSame('basarisiz', $this->db->fetchOne("SELECT durum FROM ek_satislar WHERE oturum = '".self::OTURUM."'"));

        foreach ([
            [['app' => 'keepchat', 'oturum' => 'pi_x', 'urun' => 'kocluk'], 'invalid_request'],
            [['app' => 'keepchat', 'oturum' => self::OTURUM, 'urun' => 'kocluk', 'tutar' => 1], 'invalid_request'],
            [['app' => 'keepchat', 'oturum' => self::OTURUM, 'urun' => 'yok'], 'unknown_product'],
        ] as [$govde, $hata]) {
            $this->client->jsonRequest('POST', '/huni/ek-satis', $govde);
            self::assertResponseStatusCodeSame(422);
            self::assertSame(['error' => $hata], $this->json());
        }
    }

    /** Tuzak §36: yeni yazan uç CORS listesinde — huninin kökeni izinli. */
    public function testCorsHuniKokeneIzin(): void
    {
        $this->client->request('OPTIONS', '/huni/ek-satis', server: ['HTTP_ORIGIN' => 'https://huni.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST']);
        self::assertSame('https://huni.test', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
        // Köken GET desenine (/huni/<ad>) de uyar — asıl ölçüt POST'a izin.
        self::assertStringContainsString('POST', (string) $this->client->getResponse()->headers->get('Access-Control-Allow-Methods'));
    }

    private function urun(): void
    {
        $this->db->insert('ek_urunler', ['app' => 'keepchat', 'anahtar' => 'kocluk', 'ad' => 'Koçluk', 'stripe_urun' => 'prod_k', 'stripe_fiyat' => self::FIYAT]);
        $this->stripe->yanitlar['get /v1/prices/'.self::FIYAT] = ['id' => self::FIYAT, 'object' => 'price', 'unit_amount' => 1999, 'currency' => 'eur', 'recurring' => null];
    }

    /** @param array<string, mixed> $degis */
    private function oturumYaniti(array $degis): void
    {
        $this->stripe->yanitlar['get /v1/checkout/sessions/'.self::OTURUM] = $degis + [
            'id' => self::OTURUM, 'object' => 'checkout.session', 'status' => 'complete', 'payment_status' => 'paid',
            'metadata' => ['app' => 'keepchat'], 'created' => time() - 60, 'customer' => 'cus_x', 'currency' => 'eur',
            'subscription' => ['id' => 'sub_x', 'object' => 'subscription', 'default_payment_method' => 'pm_x'],
        ];
    }

    private function yayinla(): void
    {
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertSame([], $huniler->yeniHuni('keepchat', 'genel', null, 'fitness'));
        $t = $huniler->taslak('keepchat', 'genel');
        self::assertIsArray($t);
        $t['sonrasi'] = ['baslik' => 'Hoş geldin', 'bloklar' => [], 'upsell' => ['urun' => 'kocluk', 'baslik' => 'Koçluk ekle', 'dugme' => 'Ekle']];
        self::assertSame([], $huniler->taslakKaydet('keepchat', 'genel', $t));
        self::assertIsInt($huniler->yayinla('keepchat', 'genel'));
    }

    private function sat(): void
    {
        $this->client->jsonRequest('POST', '/huni/ek-satis', ['app' => 'keepchat', 'oturum' => self::OTURUM, 'urun' => 'kocluk']);
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
