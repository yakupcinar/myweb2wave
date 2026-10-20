<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\RevenueCat\Aktarim;
use App\Tests\Support\SahteRevenueCat;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mime\Email;

/** Hak kaynağı RevenueCat (A9): Stripe aboneliği bağlı kişiye aktarılır. */
final class RevenueCatTest extends WebTestCase
{
    private const ANAHTAR = 'test-anahtar-keepchat';

    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE revenuecat_gidenler, baglantilar, kisiler, dogrulama_kodlari, subscriptions, processed_webhook_events');
        SahteRevenueCat::sifirla();
    }

    public function testBagliKisininAboneligiOlaydaRevenueCateGider(): void
    {
        $kisi = $this->bagliKisi('ayse@example.com', '42');

        $this->odemeOlaylari('Ayse@Example.com');

        // Gerçek sıra: abonelik olayında e-posta henüz yok → kuyruğa girmez;
        // e-postayı getiren ödeme olayıyla tek gönderim.
        self::assertSame(1, SahteRevenueCat::sayi());
        $istek = SahteRevenueCat::istek(0);
        self::assertSame('POST', $istek['method']);
        self::assertSame('https://revenuecat.test/v1/receipts', $istek['url']);
        self::assertContains('Authorization: Bearer strp_test_revenuecat', $istek['headers']);
        self::assertContains('X-Platform: stripe', $istek['headers']);
        self::assertSame(['app_user_id' => $kisi, 'fetch_token' => 'sub_1'], SahteRevenueCat::govde(0));
        self::assertSame(0, $this->bekleyen());
    }

    public function testDurumDegisikligiYenidenGonderilir(): void
    {
        // RevenueCat iptali ancak yeniden gönderimle hemen görür (belgesi).
        $this->bagliKisi('ayse@example.com', '42');
        $this->odemeOlaylari('ayse@example.com');

        $this->webhook('evt_iptal', 'customer.subscription.deleted', 200, [
            'id' => 'sub_1', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => 'canceled', 'metadata' => ['app' => 'keepchat'],
        ]);

        self::assertSame(2, SahteRevenueCat::sayi());
        self::assertSame('sub_1', SahteRevenueCat::govde(1)['fetch_token'] ?? null);
    }

    public function testBaglanmamisEpostaninAboneligiGitmez(): void
    {
        $this->bagliKisi('baska@example.com', '7');

        $this->odemeOlaylari('ayse@example.com');

        self::assertSame(0, SahteRevenueCat::sayi());
        self::assertSame(0, $this->sayim('SELECT count(*) FROM revenuecat_gidenler'));
    }

    public function testBaglamaSonrasiOncekiAbonelikGider(): void
    {
        $this->odemeOlaylari('ayse@example.com');
        self::assertSame(0, SahteRevenueCat::sayi(), 'henüz kimse bağlı değil');

        $kisi = $this->bagliKisi('ayse@example.com', '42');

        self::assertSame(1, SahteRevenueCat::sayi());
        self::assertSame(['app_user_id' => $kisi, 'fetch_token' => 'sub_1'], SahteRevenueCat::govde(0));
    }

    public function testRevenueCatHataVerirseStripeKaydiDurmazSatirBekler(): void
    {
        $this->bagliKisi('ayse@example.com', '42');
        SahteRevenueCat::$durum = 500;

        $this->odemeOlaylari('ayse@example.com');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('active', $this->db->fetchOne("SELECT status FROM subscriptions WHERE stripe_subscription_id = 'sub_1'"));
        self::assertSame(1, $this->bekleyen());
        self::assertStringContainsString('HTTP 500', $this->metin('SELECT son_hata FROM revenuecat_gidenler LIMIT 1'));

        SahteRevenueCat::$durum = 200;
        $t = new CommandTester((new Application(self::$kernel ?? self::bootKernel()))->find('app:revenuecat:gonder'));
        self::assertSame(0, $t->execute([]));
        self::assertStringContainsString('gönderilen=1 başarısız=0', $t->getDisplay());
        self::assertSame(0, $this->bekleyen());
    }

    public function testAgHatasiDaStripeKaydiniDurdurmaz(): void
    {
        $this->bagliKisi('ayse@example.com', '42');
        SahteRevenueCat::$agHatasi = 'baglanti kurulamadi';

        $this->odemeOlaylari('ayse@example.com');

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->bekleyen());
        self::assertStringContainsString('baglanti kurulamadi', $this->metin('SELECT son_hata FROM revenuecat_gidenler LIMIT 1'));
    }

    public function testAzamiDenemeyiAsanSatirDenenmez(): void
    {
        $kisi = $this->bagliKisi('ayse@example.com', '42');
        $this->db->executeStatement("INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, email, app) VALUES ('sub_9', 'cus_9', 'active', 'ayse@example.com', 'keepchat')");
        $this->db->insert('revenuecat_gidenler', ['app' => 'keepchat', 'stripe_subscription_id' => 'sub_9', 'kisi_id' => $kisi, 'deneme' => Aktarim::AZAMI_DENEME]);
        SahteRevenueCat::sifirla();

        self::assertSame(['gonderilen' => 0, 'basarisiz' => 0], $this->aktarim()->gonder());
        self::assertSame(0, SahteRevenueCat::sayi());
    }

    public function testAnahtarYoksaHicbirSeyKuyrugaGirmez(): void
    {
        $this->bagliKisi('ayse@example.com', '42');
        $this->db->executeStatement("INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, status, email, app) VALUES ('sub_9', 'cus_9', 'active', 'ayse@example.com', 'keepchat')");
        $kapali = new Aktarim($this->db, new MockHttpClient(), new NullLogger(), ['keepchat' => ''], 'https://revenuecat.test');

        $kapali->aboneligiKuyrugaAl($this->db, 'keepchat', 'sub_9');

        self::assertSame(0, $this->sayim('SELECT count(*) FROM revenuecat_gidenler'));
    }

    public function testGidenSatirKisisizOlamaz(): void
    {
        // Tuzak §4: kuyruk sorgusu JOIN ile kişiyi arıyor; kısıt bunu ayrıca korumalı.
        $this->db->executeStatement("INSERT INTO subscriptions (stripe_subscription_id, stripe_customer_id, app) VALUES ('sub_k', 'cus_k', 'keepchat')");
        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->db->insert('revenuecat_gidenler', ['app' => 'keepchat', 'stripe_subscription_id' => 'sub_k', 'kisi_id' => '00000000-0000-4000-8000-000000000000']);
    }

    /** E-posta koduyla bağlama — gerçek uçlardan. */
    private function bagliKisi(string $email, string $kullanici): string
    {
        $this->istek('/apps/keepchat/baglama/kod', ['email' => $email]);
        $mesaj = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mesaj);
        preg_match('/\b(\d{6})\b/', (string) $mesaj->getTextBody(), $m);
        $this->istek('/apps/keepchat/baglama/dogrula', ['email' => $email, 'kod' => $m[1] ?? '', 'kullanici' => $kullanici]);
        self::assertResponseStatusCodeSame(200);
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($j);
        self::assertIsString($j['kisi'] ?? null);

        return $j['kisi'];
    }

    /** Stripe'ın gerçek sırası: abonelik önce, ödeme oturumu (e-posta) sonra. */
    private function odemeOlaylari(string $email): void
    {
        $this->webhook('evt_sub', 'customer.subscription.created', 100, [
            'id' => 'sub_1', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => 'active', 'metadata' => ['app' => 'keepchat'],
        ]);
        $this->webhook('evt_cs', 'checkout.session.completed', 101, [
            'id' => 'cs_1', 'object' => 'checkout.session', 'mode' => 'subscription', 'subscription' => 'sub_1', 'customer' => 'cus_1',
            'customer_details' => ['email' => $email], 'metadata' => ['app' => 'keepchat'],
        ]);
    }

    /** @param array<string, mixed> $nesne */
    private function webhook(string $id, string $tur, int $zaman, array $nesne): void
    {
        $govde = (string) json_encode(['id' => $id, 'object' => 'event', 'type' => $tur, 'created' => $zaman, 'data' => ['object' => $nesne]]);
        $t = time();
        $this->client->request('POST', '/webhooks/stripe', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$govde, 'whsec_test_gizli'),
        ], $govde);
        self::assertResponseStatusCodeSame(200);
    }

    /** @param array<string, mixed> $govde */
    private function istek(string $yol, array $govde): void
    {
        $this->client->request('POST', $yol, [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::ANAHTAR, 'CONTENT_TYPE' => 'application/json'], (string) json_encode($govde));
    }

    private function bekleyen(): int
    {
        return $this->sayim('SELECT count(*) FROM revenuecat_gidenler WHERE gonderildi_at IS NULL');
    }

    private function sayim(string $sql): int
    {
        $n = $this->db->fetchOne($sql);

        return is_numeric($n) ? (int) $n : -1;
    }

    private function metin(string $sql): string
    {
        $m = $this->db->fetchOne($sql);

        return \is_string($m) ? $m : '';
    }

    private function aktarim(): Aktarim
    {
        $a = static::getContainer()->get(Aktarim::class);
        self::assertInstanceOf(Aktarim::class, $a);

        return $a;
    }
}
