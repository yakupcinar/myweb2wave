<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * Ç46: ödeme sonrası tek kullanımlık giriş bağlantısı. Ödeme kimliği (cs_)
 * giriş anahtarı değildir; jeton rastgele, tek kullanımlık, süreli; başarı
 * sayfası yalnız ödemeden sonraki pencerede ve sınırlı sayıda alır; aynı
 * bağlantı ödeme e-postasına bir kez gider.
 */
final class GirisBaglantisiTest extends WebTestCase
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
        $this->db->executeStatement('TRUNCATE giris_jetonlari, revenuecat_gidenler, baglantilar, kisiler, subscriptions, processed_webhook_events, odeme_oturumlari');
    }

    // --- başarı sayfası ---

    public function testOdemedenSonraSayfaTekKullanimlikBaglantiAlir(): void
    {
        $this->odemeTamamlandi('cs_1', 'Ayse@Example.com');
        $this->sayfaIster('cs_1');

        self::assertResponseStatusCodeSame(201);
        Sozlesme::yanitiDogrula('POST', '/huni/giris-baglantisi', $this->client->getResponse());
        $adres = $this->govde()['uygulama_adresi'] ?? null;
        self::assertIsString($adres);
        // 32 bayt base64url = 43 karakter; cs_ kimliği bağlantıda YOK.
        self::assertMatchesRegularExpression('#^keepchat://giris\?jeton=[A-Za-z0-9_-]{43}$#', $adres);
        self::assertStringNotContainsString('cs_1', $adres);
        // Jeton düz saklanmaz.
        self::assertSame(0, $this->say('SELECT count(*) FROM giris_jetonlari WHERE ozet = ?', [$this->jeton($adres)]));
        self::assertSame(1, $this->say("SELECT count(*) FROM giris_jetonlari WHERE ozet = ? AND email = 'ayse@example.com'", [hash('sha256', $this->jeton($adres))]));
    }

    public function testWebhookGelmedenSayfaBeklemedeKalir(): void
    {
        $this->sayfaIster('cs_yok');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'not_ready'], $this->govde());
        Sozlesme::yanitiDogrula('POST', '/huni/giris-baglantisi', $this->client->getResponse());
    }

    public function testPencereKapanincaSayfaBaglantiAlamaz(): void
    {
        // Sızan cs_ (tarayıcı geçmişi, piksel) bir saat sonra jeton basamaz.
        $this->odemeTamamlandi('cs_1', 'a@example.com');
        $this->db->executeStatement("UPDATE subscriptions SET odeme_tamamlandi_at = now() - interval '61 minutes'");
        $this->sayfaIster('cs_1');

        self::assertResponseStatusCodeSame(410);
        Sozlesme::yanitiDogrula('POST', '/huni/giris-baglantisi', $this->client->getResponse());
        self::assertSame(1, $this->say('SELECT count(*) FROM giris_jetonlari')); // yalnız e-postanınki
    }

    public function testOturumBasinaUctenFazlaBaglantiYok(): void
    {
        // Ödeme e-postası da bir jeton üretir; sayfaya 3 - 1 = 2 kalır.
        $this->odemeTamamlandi('cs_1', 'a@example.com');
        for ($i = 0; $i < 2; ++$i) {
            $this->sayfaIster('cs_1');
            self::assertResponseStatusCodeSame(201);
        }
        $this->sayfaIster('cs_1');

        self::assertResponseStatusCodeSame(429);
        Sozlesme::yanitiDogrula('POST', '/huni/giris-baglantisi', $this->client->getResponse());
    }

    public function testCsKimligiOlmayanOturumReddedilir(): void
    {
        $this->sayfaIster('sub_1');

        self::assertResponseStatusCodeSame(422);
    }

    // --- uygulamanın sunucusu ---

    public function testJetonKullanicinOdemeEpostasininKisisineBaglar(): void
    {
        $this->odemeTamamlandi('cs_1', 'Ayse@Example.com');
        $this->kullan($this->sayfadanJeton('cs_1'), 'kc-42');

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/giris/kullan', $this->client->getResponse());
        $kisi = $this->govde()['kisi'] ?? null;
        self::assertIsString($kisi);
        self::assertSame('ayse@example.com', $this->db->fetchOne('SELECT email FROM kisiler WHERE id = ?', [$kisi]));
        self::assertSame($kisi, $this->db->fetchOne("SELECT kisi_id FROM baglantilar WHERE app = 'keepchat' AND harici_kullanici = 'kc-42'"));
        // A9: e-posta kodu yoluyla aynı — abonelik RevenueCat kuyruğuna bu kişiyle.
        self::assertSame(1, $this->say("SELECT count(*) FROM revenuecat_gidenler WHERE stripe_subscription_id = 'sub_cs_1' AND kisi_id = ?", [$kisi]));
    }

    public function testJetonIkinciKezKullanilamaz(): void
    {
        $this->odemeTamamlandi('cs_1', 'a@example.com');
        $jeton = $this->sayfadanJeton('cs_1');
        $this->kullan($jeton, 'u1');
        self::assertResponseStatusCodeSame(200);
        $this->kullan($jeton, 'saldirgan');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid_token'], $this->govde());
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/giris/kullan', $this->client->getResponse());
        self::assertSame(0, $this->say("SELECT count(*) FROM baglantilar WHERE harici_kullanici = 'saldirgan'"));
    }

    public function testSuresiDolmusJetonReddedilir(): void
    {
        $this->odemeTamamlandi('cs_1', 'a@example.com');
        $jeton = $this->sayfadanJeton('cs_1');
        $this->db->executeStatement("UPDATE giris_jetonlari SET son_kullanma = now() - interval '1 second'");
        $this->kullan($jeton, 'u1');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'expired_token'], $this->govde());
        self::assertSame(0, $this->say('SELECT count(*) FROM baglantilar'));
    }

    public function testBilinmeyenJetonReddedilir(): void
    {
        $this->kullan(str_repeat('A', 43), 'u1');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid_token'], $this->govde());
    }

    public function testBaskaUygulamaninJetonuGecmez(): void
    {
        $this->db->insert('giris_jetonlari', [
            'app' => 'baska', 'email' => 'a@example.com', 'stripe_checkout_session_id' => 'cs_b',
            'ozet' => hash('sha256', 'baska-jeton'), 'son_kullanma' => '2999-01-01T00:00:00Z',
        ]);
        $this->kullan('baska-jeton', 'u1');

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->say('SELECT count(*) FROM baglantilar'));
    }

    public function testAnahtarsiz401(): void
    {
        $this->kullan('x', 'u1', 'yanlis');

        self::assertResponseStatusCodeSame(401);
    }

    // --- e-posta ---

    public function testOdemeEpostasindakiBaglantiCalisir(): void
    {
        $this->odemeTamamlandi('cs_1', 'Ayse@Example.com');

        self::assertEmailCount(1);
        $mesaj = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mesaj);
        self::assertSame('ayse@example.com', mb_strtolower($mesaj->getTo()[0]->getAddress()));
        preg_match('#keepchat://giris\?jeton=([A-Za-z0-9_-]{43})#', (string) $mesaj->getTextBody(), $m);
        self::assertArrayHasKey(1, $m, 'e-postada giriş bağlantısı yok');

        $this->kullan($m[1], 'u1');
        self::assertResponseStatusCodeSame(200);
    }

    public function testGirisEpostasiBirKezGider(): void
    {
        $this->odemeTamamlandi('cs_1', 'a@example.com');
        self::assertEmailCount(1);
        // Sonraki webhook'lar (abonelik durumu) yeni e-posta göndermez.
        $this->webhook('evt_sub', 'customer.subscription.updated', [
            'id' => 'sub_cs_1', 'object' => 'subscription', 'customer' => 'cus_1', 'status' => 'active',
            'metadata' => ['app' => 'keepchat'],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailCount(0);
        self::assertSame(1, $this->say('SELECT count(*) FROM giris_jetonlari'));
    }

    // --- yardımcılar ---

    private function odemeTamamlandi(string $oturum, string $email): void
    {
        $this->webhook('evt_'.$oturum, 'checkout.session.completed', [
            'id' => $oturum, 'object' => 'checkout.session', 'mode' => 'subscription',
            'subscription' => 'sub_'.$oturum, 'customer' => 'cus_1',
            'customer_details' => ['email' => $email],
            'metadata' => ['app' => 'keepchat'],
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    /** @param array<string, mixed> $nesne */
    private function webhook(string $id, string $tur, array $nesne): void
    {
        // Stripe'ın belgelenmiş imza şeması, koddan bağımsız (tuzak §3).
        $govde = (string) json_encode(['id' => $id, 'object' => 'event', 'type' => $tur, 'created' => time(), 'data' => ['object' => $nesne]]);
        $t = time();
        $this->client->request('POST', '/webhooks/stripe', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$govde, 'whsec_test_gizli'),
        ], $govde);
    }

    private function sayfaIster(string $oturum): void
    {
        $this->client->jsonRequest('POST', '/huni/giris-baglantisi', ['app' => 'keepchat', 'oturum' => $oturum]);
    }

    private function sayfadanJeton(string $oturum): string
    {
        $this->sayfaIster($oturum);
        self::assertResponseStatusCodeSame(201);
        $adres = $this->govde()['uygulama_adresi'] ?? null;
        self::assertIsString($adres);

        return $this->jeton($adres);
    }

    private function jeton(string $adres): string
    {
        parse_str((string) parse_url($adres, \PHP_URL_QUERY), $q);
        self::assertIsString($q['jeton'] ?? null);

        return $q['jeton'];
    }

    private function kullan(string $jeton, string $kullanici, string $anahtar = self::ANAHTAR): void
    {
        $this->client->request('POST', '/apps/keepchat/giris/kullan', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$anahtar, 'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['jeton' => $jeton, 'kullanici' => $kullanici]));
    }

    /** @return array<mixed> */
    private function govde(): array
    {
        $g = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($g) ? $g : [];
    }

    /** @param list<mixed> $p */
    private function say(string $sql, array $p = []): int
    {
        $n = $this->db->fetchOne($sql, $p);

        return is_numeric($n) ? (int) $n : -1;
    }
}
