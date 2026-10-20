<?php

declare(strict_types=1);

namespace App\Tests\Controller;

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
        $this->db->executeStatement('TRUNCATE subscriptions, processed_webhook_events, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari');
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
            'metadata' => ['app' => 'keepchat'],
        ]));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            [['stripe_checkout_session_id' => 'cs_1', 'stripe_subscription_id' => 'sub_1', 'odendi' => true],
                ['stripe_checkout_session_id' => 'cs_2', 'stripe_subscription_id' => null, 'odendi' => false]],
            $this->db->fetchAllAssociative('SELECT stripe_checkout_session_id, stripe_subscription_id, tamamlandi_at IS NOT NULL AS odendi FROM odeme_oturumlari ORDER BY 1'),
        );
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
