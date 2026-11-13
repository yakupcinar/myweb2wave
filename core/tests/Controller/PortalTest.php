<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\KayitliStripeIstemcisi;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * M6b (A19): Stripe müşteri portalı — ayar kodda (etiketle bulunur, kurulur /
 * güncellenir), uygulama sunucusu kişi için oturum adresi ister. Stripe'a
 * giden değerler kayıttan ölçülür.
 */
final class PortalTest extends WebTestCase
{
    private const ANAHTAR = 'test-anahtar-keepchat';

    private KernelBrowser $client;
    private Connection $db;
    private KayitliStripeIstemcisi $stripe;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE baglantilar, kisiler, subscriptions CASCADE');
        $kisi = $this->db->fetchOne("INSERT INTO kisiler (email) VALUES ('ayse@example.com') RETURNING id");
        $this->db->insert('baglantilar', ['app' => 'keepchat', 'harici_kullanici' => 'kc-1', 'kisi_id' => $kisi]);
        $this->stripe = new KayitliStripeIstemcisi();
        ApiRequestor::setHttpClient($this->stripe);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(new CurlClient());
        parent::tearDown();
    }

    /** @param list<array{string, array<string, string>}> $ayarlar id + metadata */
    private function ayarlar(array $ayarlar): void
    {
        $this->stripe->yanitlar['get /v1/billing_portal/configurations'] = ['object' => 'list', 'has_more' => false, 'url' => '/v1/billing_portal/configurations',
            'data' => array_map(static fn (array $a): array => ['id' => $a[0], 'object' => 'billing_portal.configuration', 'active' => true, 'metadata' => $a[1]], $ayarlar)];
    }

    private function abonelik(string $sub, string $musteri, string $durum, string $guncelleme, string $app = 'keepchat'): void
    {
        $this->db->insert('subscriptions', ['stripe_subscription_id' => $sub, 'stripe_customer_id' => $musteri, 'status' => $durum, 'email' => 'Ayse@Example.com', 'app' => $app, 'updated_at' => $guncelleme]);
    }

    private function portal(string $kullanici = 'kc-1', string $anahtar = self::ANAHTAR): void
    {
        $this->client->request('POST', "/apps/keepchat/kullanicilar/{$kullanici}/portal", server: ['HTTP_AUTHORIZATION' => 'Bearer '.$anahtar]);
    }

    /** @return list<array<mixed>> yolu eşleşen isteklerin parametreleri */
    private function istekler(string $yontem, string $yol): array
    {
        return array_values(array_map(static fn (array $i): array => $i['params'], array_filter(
            $this->stripe->istekler,
            static fn (array $i): bool => $i['method'] === $yontem && str_ends_with(explode('?', $i['url'])[0], $yol),
        )));
    }

    /**
     * Erişim veren abonelik önce (iptal edilmiş daha yeni olsa da); bizim
     * etiketli ayar (etiketsiz ayar atlanır); dönüş uygulamanın huni adresi.
     */
    public function testUygulamaKisiIcinPortalAdresiAlir(): void
    {
        $this->abonelik('sub_eski', 'cus_iptal', 'canceled', '2026-11-09 10:00Z');
        $this->abonelik('sub_aktif', 'cus_aktif', 'active', '2026-10-01 10:00Z');
        $this->ayarlar([['bpc_baska', []], ['bpc_bizim', ['kurulum' => 'myweb2wave-m6b']]]);
        $this->stripe->yanitlar['post /v1/billing_portal/sessions'] = ['id' => 'bps_1', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/test_1'];

        $this->portal();

        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/kullanicilar/{kullanici}/portal', $this->client->getResponse());
        self::assertSame(['adres' => 'https://billing.stripe.com/p/session/test_1'], json_decode((string) $this->client->getResponse()->getContent(), true));
        $oturum = $this->istekler('post', '/v1/billing_portal/sessions')[0] ?? [];
        self::assertSame(['cus_aktif', 'bpc_bizim', 'https://huni.test/keepchat/'], [$oturum['customer'] ?? null, $oturum['configuration'] ?? null, $oturum['return_url'] ?? null]);
    }

    public function testYetkiKisiAbonelikVeAyar(): void
    {
        $this->portal(anahtar: 'yanlis');
        self::assertResponseStatusCodeSame(401);
        $this->portal('kimse');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'unknown_user'], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->abonelik('sub_baska', 'cus_baska', 'active', '2026-11-01 10:00Z', 'baska');
        $this->portal();
        self::assertSame(['error' => 'no_subscription'], json_decode((string) $this->client->getResponse()->getContent(), true));
        self::assertSame([], $this->stripe->istekler, 'abonelik yokken Stripe\'a gidilmez');

        $this->abonelik('sub_1', 'cus_1', 'active', '2026-11-01 10:00Z');
        $this->ayarlar([['bpc_baska', []]]);
        $this->portal();
        self::assertResponseStatusCodeSame(503);
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/kullanicilar/{kullanici}/portal', $this->client->getResponse());
        self::assertSame(['error' => 'portal_not_configured'], json_decode((string) $this->client->getResponse()->getContent(), true));
        self::assertSame([], $this->istekler('post', '/v1/billing_portal/sessions'));
    }

    /** Ayar kodla aynı: iptal dönem sonunda (A19), kart + fatura açık, plan değiştirme / müşteri düzenleme kapalı; varsa güncellenir. */
    public function testAyarKurulurVeGuncellenir(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $komut = new CommandTester((new Application($kernel))->find('app:stripe-portal:kur'));
        $this->ayarlar([['bpc_baska', []]]);
        $this->stripe->yanitlar['post /v1/billing_portal/configurations'] = ['id' => 'bpc_yeni', 'object' => 'billing_portal.configuration'];

        self::assertSame(Command::SUCCESS, $komut->execute([]));
        self::assertStringContainsString('Kuruldu: bpc_yeni', $komut->getDisplay());
        $ayar = $this->istekler('post', '/v1/billing_portal/configurations')[0] ?? [];
        self::assertSame([
            'subscription_cancel' => ['enabled' => 'true', 'mode' => 'at_period_end', 'proration_behavior' => 'none'],
            'payment_method_update' => ['enabled' => 'true'],
            'invoice_history' => ['enabled' => 'true'],
            'subscription_update' => ['enabled' => 'false'],
            'customer_update' => ['enabled' => 'false'],
        ], $ayar['features'] ?? null);
        self::assertSame(['kurulum' => 'myweb2wave-m6b'], $ayar['metadata'] ?? null);

        $this->stripe->istekler = [];
        $this->ayarlar([['bpc_bizim', ['kurulum' => 'myweb2wave-m6b']]]);
        unset($this->stripe->yanitlar['post /v1/billing_portal/configurations']);
        self::assertSame(Command::SUCCESS, $komut->execute([]));
        self::assertStringContainsString('Güncellendi: bpc_bizim', $komut->getDisplay());
        self::assertCount(1, $this->istekler('post', '/v1/billing_portal/configurations/bpc_bizim'));
    }
}
