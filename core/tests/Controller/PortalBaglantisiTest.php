<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * M6b2 (A19): web yolundan müşteri portalı. Hesap sızdırmaz (aynı yanıt),
 * e-posta başına saatte 3, jeton düz saklanmaz, onay sayfası GET'te jetonu
 * yakmaz (e-posta tarayıcıları), POST bir kez kullanır ve Stripe'a gönderir.
 */
final class PortalBaglantisiTest extends WebTestCase
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
        $this->db->executeStatement('TRUNCATE portal_jetonlari, subscriptions CASCADE');
        $this->db->insert('subscriptions', ['stripe_subscription_id' => 'sub_1', 'stripe_customer_id' => 'cus_1', 'status' => 'active', 'email' => 'Ayse@Example.com', 'app' => 'keepchat']);
        $this->stripe = new KayitliStripeIstemcisi();
        $this->stripe->yanitlar = [
            'get /v1/billing_portal/configurations' => ['object' => 'list', 'has_more' => false, 'url' => '/v1/billing_portal/configurations',
                'data' => [['id' => 'bpc_bizim', 'object' => 'billing_portal.configuration', 'active' => true, 'metadata' => ['kurulum' => 'myweb2wave-m6b']]]],
            'post /v1/billing_portal/sessions' => ['id' => 'bps_1', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/test_1'],
        ];
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    private function iste(string $eposta, string $app = 'keepchat'): void
    {
        $this->client->jsonRequest('POST', '/huni/portal-baglantisi', ['app' => $app, 'eposta' => $eposta]);
    }

    /** @return list<array<mixed>> portal oturumu isteklerinin parametreleri */
    private function oturumlar(): array
    {
        return array_values(array_map(static fn (array $i): array => $i['params'], array_filter(
            $this->stripe->istekler,
            static fn (array $i): bool => str_ends_with($i['url'], '/v1/billing_portal/sessions'),
        )));
    }

    /** @return list<Email> */
    private function epostalar(): array
    {
        return array_values(array_filter(self::getMailerMessages(), static fn (object $m): bool => $m instanceof Email));
    }

    public function testAboneyeTekKullanimlikBaglantiGider(): void
    {
        $this->iste('  AYSE@example.com ');

        self::assertResponseStatusCodeSame(202);
        Sozlesme::yanitiDogrula('POST', '/huni/portal-baglantisi', $this->client->getResponse());
        $epostalar = $this->epostalar();
        self::assertCount(1, $epostalar);
        self::assertSame('ayse@example.com', $epostalar[0]->getTo()[0]->getAddress());
        self::assertSame(1, preg_match('#(http://localhost/portal/([A-Za-z0-9_-]{43}))#', (string) $epostalar[0]->getTextBody(), $m));
        [, $adres, $jeton] = $m;
        // Jeton düz saklanmaz.
        self::assertSame([hash('sha256', $jeton)], $this->db->fetchFirstColumn('SELECT ozet FROM portal_jetonlari'));

        // GET onay sayfası: jeton yanmaz, Stripe'a gidilmez.
        $this->client->request('GET', $adres);
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('button', 'Portalı aç');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSame([], $this->stripe->istekler);
        self::assertNull($this->db->fetchOne('SELECT kullanildi_at FROM portal_jetonlari'));

        $this->client->request('POST', $adres);
        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('https://billing.stripe.com/p/session/test_1');
        $oturum = $this->oturumlar()[0] ?? [];
        self::assertSame(['cus_1', 'bpc_bizim'], [$oturum['customer'] ?? null, $oturum['configuration'] ?? null]);

        $this->client->request('POST', $adres);
        self::assertResponseStatusCodeSame(410);
        self::assertSelectorTextContains('[role=alert]', 'daha önce kullanıldı');
    }

    /** Abone olmayan adres ve başka uygulamanın abonesi: aynı yanıt, e-posta yok, jeton yok. */
    public function testHesapSizdirmaz(): void
    {
        $this->iste('kimse@example.com');
        self::assertResponseStatusCodeSame(202);
        $this->db->executeStatement("UPDATE subscriptions SET app = 'baska'");
        $this->iste('ayse@example.com');
        self::assertResponseStatusCodeSame(202);

        self::assertSame([], $this->epostalar());
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM portal_jetonlari'));
    }

    public function testSaatteUctenFazlaGitmez(): void
    {
        // getMailerMessages yalnız son isteğinkini verir: her istekte ayrı ölçülür.
        $giden = [];
        foreach (range(1, 4) as $_) {
            $this->iste('ayse@example.com');
            self::assertResponseStatusCodeSame(202);
            $giden[] = \count($this->epostalar());
        }

        self::assertSame([1, 1, 1, 0], $giden);
        self::assertSame(3, $this->db->fetchOne('SELECT count(*) FROM portal_jetonlari'));
    }

    public function testSuresiDolanVeGecersiz(): void
    {
        $this->iste('ayse@example.com');
        self::assertSame(1, preg_match('#/portal/([A-Za-z0-9_-]{43})#', (string) $this->epostalar()[0]->getTextBody(), $m));
        $this->db->executeStatement("UPDATE portal_jetonlari SET son_kullanma = now() - interval '1 second'");

        $this->client->request('POST', '/portal/'.$m[1]);
        self::assertResponseStatusCodeSame(410);
        self::assertSelectorTextContains('[role=alert]', 'süresi doldu');
        $this->client->request('POST', '/portal/'.str_repeat('a', 43));
        self::assertResponseStatusCodeSame(410);
        self::assertSame([], $this->oturumlar());
    }

    public function testGecersizIstek(): void
    {
        $this->iste('eposta-degil');
        self::assertResponseStatusCodeSame(422);
        Sozlesme::yanitiDogrula('POST', '/huni/portal-baglantisi', $this->client->getResponse());
        $this->iste('ayse@example.com', 'baska');
        self::assertResponseStatusCodeSame(422);
    }

    /** Tuzak §36: CORS listesinde olmayan uç tarayıcıda sessizce düşer. */
    public function testHuniKokeniCorsOnIstegi(): void
    {
        $this->client->request('OPTIONS', '/huni/portal-baglantisi', [], [], [
            'HTTP_ORIGIN' => 'https://huni.test', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST', 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        self::assertSame('https://huni.test', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
        // Yol huni tanımının (yalnız GET) desenine de uyar — köken tek başına yetmez, POST izinli mi (kırma W8).
        self::assertContains('POST', array_map('trim', explode(',', (string) $this->client->getResponse()->headers->get('Access-Control-Allow-Methods'))));
    }
}
