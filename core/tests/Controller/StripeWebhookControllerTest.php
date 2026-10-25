<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Atif\DonusumAktarimi;
use App\Tests\Support\SahteRevenueCat;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StripeWebhookControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        SahteRevenueCat::sifirla();
        $this->db->executeStatement('TRUNCATE reklam_donusumleri, revenuecat_gidenler, subscriptions, processed_webhook_events, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_surumleri, huni_kurallari, huniler');
    }

    public function testGecersizImza400VeHicbirSeyYazilmaz(): void
    {
        $this->gonder($this->abonelikOlayi('evt_1', 100, 'active'), 'whsec_yanlis');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(0, $this->say('processed_webhook_events'));
        self::assertSame(0, $this->say('subscriptions'));
    }

    public function testAbonelikOlayiDurumuKaydeder(): void
    {
        $this->gonder($this->abonelikOlayi('evt_1', 100, 'active'));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['status' => 'active', 'stripe_customer_id' => 'cus_1', 'app' => 'keepchat'], $this->abonelik());
    }

    public function testAyniOlayIkiKezGelirseBirKezIslenir(): void
    {
        // R3. İkinci kopyanın durumu bilerek farklı: işlenseydi görünürdü.
        $this->gonder($this->abonelikOlayi('evt_1', 100, 'active'));
        $this->gonder($this->abonelikOlayi('evt_1', 100, 'canceled'));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $this->say('processed_webhook_events'));
        self::assertSame('active', $this->abonelik()['status'] ?? null);
    }

    public function testEskiOlayYeniDurumuEzmez(): void
    {
        // Stripe olay sırasını garanti etmiyor (tuzak §13).
        $this->gonder($this->abonelikOlayi('evt_yeni', 200, 'past_due'));
        $this->gonder($this->abonelikOlayi('evt_eski', 100, 'active'));

        self::assertSame('past_due', $this->abonelik()['status'] ?? null);
    }

    public function testOdemeTamamlanincaEpostaAbonelikeBaglanir(): void
    {
        $this->gonder($this->olay('evt_cs', 'checkout.session.completed', 90, [
            'id' => 'cs_1', 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_1', 'customer' => 'cus_1',
            'customer_details' => ['email' => 'ayse@example.com'],
            'metadata' => ['app' => 'keepchat'],
        ]));
        $this->gonder($this->abonelikOlayi('evt_1', 100, 'active'));

        self::assertSame(
            ['email' => 'ayse@example.com', 'status' => 'active'],
            $this->db->fetchAssociative("SELECT email, status FROM subscriptions WHERE stripe_subscription_id = 'sub_1'"),
        );
    }

    public function testOdemeTamamlanincaHuniOturumuOdendiOlur(): void
    {
        // A6: reklamdan gelen ziyaretin oturumu; başka oturuma dokunulmaz.
        $ziyaret = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app) VALUES ('keepchat') RETURNING id");
        $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => 'cs_1', 'ziyaret_id' => $ziyaret]);
        $this->db->insert('odeme_oturumlari', ['stripe_checkout_session_id' => 'cs_2', 'ziyaret_id' => $ziyaret]);

        $this->gonder($this->olay('evt_cs', 'checkout.session.completed', 90, [
            'id' => 'cs_1', 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_1', 'customer' => 'cus_1',
            'customer_details' => ['email' => 'ayse@example.com'],
            'payment_status' => 'paid',
            'metadata' => ['app' => 'keepchat'],
        ]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [['stripe_checkout_session_id' => 'cs_1', 'stripe_subscription_id' => 'sub_1', 'odendi' => true],
                ['stripe_checkout_session_id' => 'cs_2', 'stripe_subscription_id' => null, 'odendi' => false]],
            $this->db->fetchAllAssociative('SELECT stripe_checkout_session_id, stripe_subscription_id, tamamlandi_at IS NOT NULL AS odendi FROM odeme_oturumlari ORDER BY 1'),
        );
    }

    public function testYalnizIzinliOdenmisZiyaretIcinIkiAgaAyniOlayKimligiGider(): void
    {
        $ziyaret = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app, fbclid, ttclid, izin_at) VALUES ('keepchat', 'tiklama-fb', 'tiklama-tt', now()) RETURNING id");
        $this->db->insert('odeme_oturumlari', [
            'stripe_checkout_session_id' => 'cs_h6b',
            'ziyaret_id' => $ziyaret,
            'ip_adresi' => '192.0.2.21',
            'user_agent' => 'Test tarayıcı',
        ]);

        $this->gonder($this->olay('evt_h6b', 'checkout.session.completed', 1_800_000_000, [
            'id' => 'cs_h6b', 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_h6b', 'customer' => 'cus_h6b', 'payment_status' => 'paid',
            'amount_total' => 1299, 'currency' => 'usd', 'metadata' => ['app' => 'keepchat'],
        ]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(2, SahteRevenueCat::sayi());
        self::assertStringStartsWith('https://graph.facebook.com/v99.0/123456789/events', SahteRevenueCat::istek(0)['url']);
        self::assertSame('https://business-api.tiktok.com/open_api/v1.3/event/track/', SahteRevenueCat::istek(1)['url']);
        $meta = SahteRevenueCat::govde(0);
        $tiktok = SahteRevenueCat::govde(1);
        self::assertIsArray($meta['data'] ?? null);
        self::assertIsArray($tiktok['data'] ?? null);
        $metaData = $meta['data'][0] ?? null;
        $tiktokData = $tiktok['data'][0] ?? null;
        self::assertIsArray($metaData);
        self::assertIsArray($tiktokData);
        self::assertIsArray($metaData['user_data'] ?? null);
        self::assertIsArray($tiktokData['user'] ?? null);
        self::assertIsArray($metaData['custom_data'] ?? null);
        self::assertIsArray($tiktokData['properties'] ?? null);
        self::assertSame('cs_h6b', $metaData['event_id'] ?? null);
        self::assertSame('cs_h6b', $tiktokData['event_id'] ?? null);
        self::assertSame('Purchase', $metaData['event_name'] ?? null);
        self::assertSame('CompletePayment', $tiktokData['event'] ?? null);
        self::assertSame('tiklama-tt', $tiktokData['user']['ttclid'] ?? null);
        self::assertSame('192.0.2.21', $metaData['user_data']['client_ip_address'] ?? null);
        self::assertSame(12.99, $metaData['custom_data']['value'] ?? null);
        self::assertSame(12.99, $tiktokData['properties']['value'] ?? null);
        self::assertCount(2, $this->db->fetchFirstColumn('SELECT id FROM reklam_donusumleri WHERE gonderildi_at IS NOT NULL AND payload IS NULL'));
    }

    public function testIzinsizZiyaretReklamAglarinaGonderilmez(): void
    {
        $ziyaret = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app) VALUES ('keepchat') RETURNING id");
        $this->db->insert('odeme_oturumlari', [
            'stripe_checkout_session_id' => 'cs_red',
            'ziyaret_id' => $ziyaret,
            'ip_adresi' => '192.0.2.22',
            'user_agent' => 'Test tarayıcı',
        ]);

        $this->gonder($this->olay('evt_red', 'checkout.session.completed', 1_800_000_000, [
            'id' => 'cs_red', 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_red', 'customer' => 'cus_red', 'payment_status' => 'paid',
            'amount_total' => 1299, 'currency' => 'usd', 'metadata' => ['app' => 'keepchat'],
        ]));

        self::assertResponseStatusCodeSame(200);
        self::assertCount(0, $this->db->fetchFirstColumn('SELECT id FROM reklam_donusumleri'));
        self::assertSame(0, SahteRevenueCat::sayi());
    }

    public function testOdenmemisOturumReklamAglarinaGitmezVeEslemeBilgisiSilinir(): void
    {
        $ziyaret = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app, izin_at) VALUES ('keepchat', now()) RETURNING id");
        $this->db->insert('odeme_oturumlari', [
            'stripe_checkout_session_id' => 'cs_unpaid',
            'ziyaret_id' => $ziyaret,
            'ip_adresi' => '192.0.2.23',
            'user_agent' => 'Test tarayıcı',
        ]);

        $this->gonder($this->olay('evt_unpaid', 'checkout.session.completed', 1_800_000_000, [
            'id' => 'cs_unpaid', 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_unpaid', 'customer' => 'cus_unpaid', 'payment_status' => 'unpaid',
            'amount_total' => 1299, 'currency' => 'usd', 'metadata' => ['app' => 'keepchat'],
        ]));

        self::assertResponseStatusCodeSame(200);
        self::assertCount(0, $this->db->fetchFirstColumn('SELECT id FROM reklam_donusumleri'));
        self::assertCount(0, SahteRevenueCat::$istekler);
        self::assertSame(
            ['ip_adresi' => null, 'user_agent' => null, 'tamamlandi' => false],
            $this->db->fetchAssociative("SELECT ip_adresi::text AS ip_adresi, user_agent, tamamlandi_at IS NOT NULL AS tamamlandi FROM odeme_oturumlari WHERE stripe_checkout_session_id = 'cs_unpaid'"),
        );
    }

    public function testSuresiDolanOturumunEslemeBilgisiHemenSilinir(): void
    {
        // Ç59: ödenmeden kapanan oturumda completed hiç gelmez; IP/UA expired'da gider.
        $ziyaret = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app, izin_at) VALUES ('keepchat', now()) RETURNING id");
        foreach (['cs_terk', 'cs_baska'] as $oturum) {
            $this->db->insert('odeme_oturumlari', [
                'stripe_checkout_session_id' => $oturum,
                'ziyaret_id' => $ziyaret,
                'ip_adresi' => '192.0.2.30',
                'user_agent' => 'Test tarayıcı',
            ]);
        }

        // metadata.app yok: temizlik uygulamadan bağımsız (A4 kapısına takılmamalı).
        $this->gonder($this->olay('evt_terk', 'checkout.session.expired', 1_800_000_000, [
            'id' => 'cs_terk', 'object' => 'checkout.session', 'status' => 'expired',
        ]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [['stripe_checkout_session_id' => 'cs_baska', 'ip' => '192.0.2.30', 'user_agent' => 'Test tarayıcı'],
                ['stripe_checkout_session_id' => 'cs_terk', 'ip' => null, 'user_agent' => null]],
            $this->db->fetchAllAssociative('SELECT stripe_checkout_session_id, host(ip_adresi) AS ip, user_agent FROM odeme_oturumlari ORDER BY 1'),
        );
        self::assertCount(0, $this->db->fetchFirstColumn('SELECT id FROM reklam_donusumleri'));
    }

    public function testCronKirkSekizSaattenEskiOturumunEslemeBilgisiniSiler(): void
    {
        // Ç59 yedeği: expired olayı kaçarsa (webhook kapalı, olay seçilmemiş) cron siler.
        $ziyaret = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app, izin_at) VALUES ('keepchat', now()) RETURNING id");
        foreach ([['cs_eski', '49 hours'], ['cs_sinirda', '47 hours'], ['cs_yeni', '1 hour']] as [$oturum, $once]) {
            $this->db->executeStatement(
                "INSERT INTO odeme_oturumlari (stripe_checkout_session_id, ziyaret_id, ip_adresi, user_agent, created_at)
                 VALUES (:o, :z, '192.0.2.31', 'Test tarayıcı', now() - CAST(:once AS interval))",
                ['o' => $oturum, 'z' => $ziyaret, 'once' => $once],
            );
        }

        $aktarim = static::getContainer()->get(DonusumAktarimi::class);
        self::assertInstanceOf(DonusumAktarimi::class, $aktarim);
        $aktarim->gonder();

        self::assertSame(
            ['cs_eski' => null, 'cs_sinirda' => 'Test tarayıcı', 'cs_yeni' => 'Test tarayıcı'],
            $this->db->fetchAllKeyValue('SELECT stripe_checkout_session_id, user_agent FROM odeme_oturumlari ORDER BY 1'),
        );
        self::assertSame(1, $this->say('odeme_oturumlari WHERE ip_adresi IS NULL'));
    }

    public function testSifirOndalikliParaBirimiYuzdeYuzeBolunmez(): void
    {
        $ziyaret = $this->db->fetchOne("INSERT INTO huni_ziyaretleri (app, izin_at) VALUES ('keepchat', now()) RETURNING id");
        $this->db->insert('odeme_oturumlari', [
            'stripe_checkout_session_id' => 'cs_jpy',
            'ziyaret_id' => $ziyaret,
            'ip_adresi' => '192.0.2.24',
            'user_agent' => 'Test tarayıcı',
        ]);

        $this->gonder($this->olay('evt_jpy', 'checkout.session.completed', 1_800_000_000, [
            'id' => 'cs_jpy', 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_jpy', 'customer' => 'cus_jpy', 'payment_status' => 'paid',
            'amount_total' => 1299, 'currency' => 'jpy', 'metadata' => ['app' => 'keepchat'],
        ]));

        self::assertResponseStatusCodeSame(200);
        $meta = SahteRevenueCat::govde(0);
        $tiktok = SahteRevenueCat::govde(1);
        self::assertIsArray($meta['data'] ?? null);
        self::assertIsArray($tiktok['data'] ?? null);
        $metaData = $meta['data'][0] ?? null;
        $tiktokData = $tiktok['data'][0] ?? null;
        self::assertIsArray($metaData);
        self::assertIsArray($tiktokData);
        self::assertIsArray($metaData['custom_data'] ?? null);
        self::assertIsArray($tiktokData['properties'] ?? null);
        self::assertSame(1299, $metaData['custom_data']['value'] ?? null);
        self::assertSame(1299, $tiktokData['properties']['value'] ?? null);
    }

    public function testOdemeOlayiDurumYazmaz(): void
    {
        // Kırma S8 ile doğdu: e-posta testi ödeme olayını ÖNCE gönderiyor,
        // çakışma yolu hiç koşmuyordu. Geç gelen ödeme olayı past_due'yu ezmemeli.
        $this->gonder($this->abonelikOlayi('evt_1', 200, 'past_due'));
        $this->gonder($this->olay('evt_cs', 'checkout.session.completed', 90, [
            'id' => 'cs_1', 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_1', 'customer' => 'cus_1',
            'customer_details' => ['email' => 'ayse@example.com'],
            'metadata' => ['app' => 'keepchat'],
        ]));

        self::assertSame(
            ['email' => 'ayse@example.com', 'status' => 'past_due'],
            $this->db->fetchAssociative("SELECT email, status FROM subscriptions WHERE stripe_subscription_id = 'sub_1'"),
        );
    }

    public function testUygulamasizAbonelikOlayiUygulanmaz(): void
    {
        // A4: Uygulaması bilinmeyen abonelik kaydedilmez (ör. CLI ile
        // tetiklenen olay). Olay yine "işlendi" sayılır, Stripe tekrar denemez.
        $this->gonder($this->abonelikOlayi('evt_1', 100, 'active', []));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $this->say('subscriptions'));
        self::assertSame(1, $this->say('processed_webhook_events'));
    }

    public function testUygulamasizOdemeOlayiUygulanmaz(): void
    {
        $this->gonder($this->olay('evt_cs', 'checkout.session.completed', 90, [
            'id' => 'cs_1', 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_1', 'customer' => 'cus_1',
            'customer_details' => ['email' => 'ayse@example.com'],
        ]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $this->say('subscriptions'));
    }

    public function testUygulamasizAbonelikKisitiUygulamasizDaDuser(): void
    {
        // A4 kapısı kısıt: kod bir gün app'siz INSERT yazarsa DB reddeder.
        $this->expectException(NotNullConstraintViolationException::class);
        $this->db->insert('subscriptions', ['stripe_subscription_id' => 'sub_k', 'stripe_customer_id' => 'cus_k']);
    }

    public function testOlayKimligiKisitiUygulamasizDaDuser(): void
    {
        // Tuzak §4: uygulamadaki tekrar kontrolü kısıtı görünmez kılar.
        $this->db->insert('processed_webhook_events', ['event_id' => 'evt_k', 'type' => 'x']);
        $this->expectException(UniqueConstraintViolationException::class);
        $this->db->insert('processed_webhook_events', ['event_id' => 'evt_k', 'type' => 'x']);
    }

    private function gonder(string $govde, string $sir = 'whsec_test_gizli'): void
    {
        // Stripe'ın belgelenmiş şeması, koddan bağımsız (tuzak §3).
        $t = time();
        $imza = hash_hmac('sha256', $t.'.'.$govde, $sir);
        $this->client->request('POST', '/webhooks/stripe', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1={$imza}",
        ], $govde);
    }

    /** @param array<string, string> $metadata */
    private function abonelikOlayi(string $id, int $zaman, string $durum, array $metadata = ['app' => 'keepchat']): string
    {
        return $this->olay($id, 'customer.subscription.updated', $zaman, [
            'id' => 'sub_1', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => $durum,
            'metadata' => $metadata,
        ]);
    }

    /** @param array<string, mixed> $nesne */
    private function olay(string $id, string $tur, int $zaman, array $nesne): string
    {
        return (string) json_encode([
            'id' => $id, 'object' => 'event', 'type' => $tur, 'created' => $zaman,
            'data' => ['object' => $nesne],
        ]);
    }

    private function say(string $tablo): int
    {
        $n = $this->db->fetchOne("SELECT count(*) FROM {$tablo}");

        return is_numeric($n) ? (int) $n : -1;
    }

    /** @return array<string, mixed>|false */
    private function abonelik(): array|false
    {
        return $this->db->fetchAssociative("SELECT status, stripe_customer_id, app FROM subscriptions WHERE stripe_subscription_id = 'sub_1'");
    }
}
