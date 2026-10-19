<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * A5: uygulamanın SUNUCUSU çekirdeğe uygulama anahtarıyla sorar. Kişi
 * e-posta koduyla doğrulanır; uygulamadaki kullanıcı kişiye bağlanır; hak
 * kişinin e-postasıyla o uygulamanın aboneliklerinden okunur.
 */
final class KimlikControllerTest extends WebTestCase
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
        $this->db->executeStatement('TRUNCATE baglantilar, kisiler, dogrulama_kodlari, subscriptions, processed_webhook_events');
    }

    // --- yetki ---

    public function testAnahtarsiz401(): void
    {
        $this->client->jsonRequest('POST', '/apps/keepchat/baglama/kod', ['email' => 'a@example.com']);
        self::assertResponseStatusCodeSame(401);
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/baglama/kod', $this->client->getResponse());
    }

    public function testYanlisAnahtar401(): void
    {
        $this->istek('POST', '/apps/keepchat/baglama/kod', ['email' => 'a@example.com'], 'yanlis');
        self::assertResponseStatusCodeSame(401);
    }

    public function testBirUygulamaninAnahtariBaskaUygulamadaGecmez(): void
    {
        $this->istek('GET', '/apps/baska/kullanicilar/u1/hak');
        self::assertResponseStatusCodeSame(401);
    }

    public function testBosJetonAnahtariOlmayanUygulamadaGecmez(): void
    {
        // Kırma I1 ile doğdu: anahtarı yapılandırılmamış uygulamada beklenen
        // '' olur; boş "Bearer " başlığı hash_equals('', '') ile geçerdi.
        $this->istek('GET', '/apps/baska/kullanicilar/u1/hak', null, '');
        self::assertResponseStatusCodeSame(401);
    }

    // --- kod isteği ---

    public function testKodIstegi202VeKodEpostaylaGider(): void
    {
        $this->istek('POST', '/apps/keepchat/baglama/kod', ['email' => 'Ayse@Example.com']);

        self::assertResponseStatusCodeSame(202);
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/baglama/kod', $this->client->getResponse());
        self::assertEmailCount(1);
        $kod = $this->sonKod();
        self::assertMatchesRegularExpression('/^\d{6}$/', $kod);
        // Kod düz saklanmaz.
        self::assertSame(0, $this->say("SELECT count(*) FROM dogrulama_kodlari WHERE kod_ozeti = '{$kod}'"));
        self::assertSame(1, $this->say("SELECT count(*) FROM dogrulama_kodlari WHERE email = 'ayse@example.com'"));
    }

    public function testAyniEpostayaOnDakikadaUcKoddanFazlasi429(): void
    {
        for ($i = 0; $i < 3; ++$i) {
            $this->istek('POST', '/apps/keepchat/baglama/kod', ['email' => 'a@example.com']);
            self::assertResponseStatusCodeSame(202);
        }
        $this->istek('POST', '/apps/keepchat/baglama/kod', ['email' => 'a@example.com']);

        self::assertResponseStatusCodeSame(429);
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/baglama/kod', $this->client->getResponse());
    }

    // --- doğrulama ---

    public function testDogruKodKullaniciyiKisiyeBaglar(): void
    {
        $kod = $this->kodIste('Ayse@Example.com');
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'Ayse@Example.com', 'kod' => $kod, 'kullanici' => 'kc-42']);

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/baglama/dogrula', $this->client->getResponse());
        $kisi = $this->govde()['kisi'] ?? null;
        self::assertIsString($kisi);
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $kisi);
        self::assertSame(
            ['email' => 'ayse@example.com'],
            $this->db->fetchAssociative('SELECT email FROM kisiler WHERE id = ?', [$kisi]),
        );
        self::assertSame(1, $this->say("SELECT count(*) FROM baglantilar WHERE app = 'keepchat' AND harici_kullanici = 'kc-42'"));
    }

    public function testAyniEpostaIkinciKullaniciAyniKisiye(): void
    {
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $this->kodIste('a@example.com'), 'kullanici' => 'u1']);
        $birinci = $this->govde()['kisi'] ?? null;
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $this->kodIste('a@example.com'), 'kullanici' => 'u2']);

        self::assertSame($birinci, $this->govde()['kisi'] ?? null);
        self::assertSame(1, $this->say('SELECT count(*) FROM kisiler'));
    }

    public function testYanlisKod422VeDenemeSayilir(): void
    {
        $kod = $this->kodIste('a@example.com');
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $this->baskaKod($kod), 'kullanici' => 'u1']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid_code'], $this->govde());
        Sozlesme::yanitiDogrula('POST', '/apps/{app}/baglama/dogrula', $this->client->getResponse());
        self::assertSame(0, $this->say('SELECT count(*) FROM baglantilar'));
    }

    public function testBesYanlisDenemedenSonraDogruKodDaReddedilir(): void
    {
        $kod = $this->kodIste('a@example.com');
        for ($i = 0; $i < 5; ++$i) {
            $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $this->baskaKod($kod), 'kullanici' => 'u1']);
        }
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $kod, 'kullanici' => 'u1']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'too_many_attempts'], $this->govde());
        self::assertSame(0, $this->say('SELECT count(*) FROM baglantilar'));
    }

    public function testSuresiDolmusKodReddedilir(): void
    {
        $kod = $this->kodIste('a@example.com');
        $this->db->executeStatement("UPDATE dogrulama_kodlari SET son_kullanma = now() - interval '1 second'");
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $kod, 'kullanici' => 'u1']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'expired_code'], $this->govde());
    }

    public function testKullanilmisKodTekrarKullanilamaz(): void
    {
        $kod = $this->kodIste('a@example.com');
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $kod, 'kullanici' => 'u1']);
        self::assertResponseStatusCodeSame(200);
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $kod, 'kullanici' => 'saldirgan']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->say("SELECT count(*) FROM baglantilar WHERE harici_kullanici = 'saldirgan'"));
    }

    public function testYeniKodEskisiniGecersizKilar(): void
    {
        $eski = $this->kodIste('a@example.com');
        $yeni = $this->kodIste('a@example.com');
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $eski, 'kullanici' => 'u1']);
        // Kodlar rastgele; nadiren aynı çıkarsa bu iddia anlamsız olur.
        if ($eski !== $yeni) {
            self::assertResponseStatusCodeSame(422);
        }
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $yeni, 'kullanici' => 'u1']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testEskiKodYenisiKullanildiktanSonraDaGecmez(): void
    {
        // Kırma I7 ile doğdu: doğrulama yalnız EN SON aktif koda bakar; eski
        // kodlar geçersiz kılınmasaydı, yeni kod kullanılınca "en son aktif"
        // eski kod olur ve yeniden geçerli hâle gelirdi.
        $eski = $this->kodIste('a@example.com');
        $yeni = $this->kodIste('a@example.com');
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $yeni, 'kullanici' => 'u1']);
        self::assertResponseStatusCodeSame(200);
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'a@example.com', 'kod' => $eski, 'kullanici' => 'saldirgan']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->say("SELECT count(*) FROM baglantilar WHERE harici_kullanici = 'saldirgan'"));
    }

    public function testKodBaskaEpostaylaKullanilamaz(): void
    {
        // Kod (uygulama, e-posta) için verilir; başka e-postayla geçmez.
        $kod = $this->kodIste('a@example.com');
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => 'b@example.com', 'kod' => $kod, 'kullanici' => 'u1']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['error' => 'invalid_code'], $this->govde());
    }

    // --- hak ---

    public function testBagliKullaniciAktifAbonelik(): void
    {
        $this->bagla('Ayse@Example.com', 'kc-42');
        $this->abonelik('keepchat', 'AYSE@example.com', 'active');
        $this->istek('GET', '/apps/keepchat/kullanicilar/kc-42/hak');

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('GET', '/apps/{app}/kullanicilar/{kullanici}/hak', $this->client->getResponse());
        self::assertSame(['aktif' => true, 'durum' => 'active'], $this->govde());
    }

    public function testPastDueErisimVerir(): void
    {
        // A5: Stripe yeniden denerken kullanıcı kilitlenmez.
        $this->bagla('a@example.com', 'u1');
        $this->abonelik('keepchat', 'a@example.com', 'past_due');
        $this->istek('GET', '/apps/keepchat/kullanicilar/u1/hak');

        self::assertSame(['aktif' => true, 'durum' => 'past_due'], $this->govde());
    }

    public function testIptalEdilmisAbonelikErisimVermez(): void
    {
        $this->bagla('a@example.com', 'u1');
        $this->abonelik('keepchat', 'a@example.com', 'canceled');
        $this->istek('GET', '/apps/keepchat/kullanicilar/u1/hak');

        self::assertSame(['aktif' => false, 'durum' => 'canceled'], $this->govde());
    }

    public function testBaskaUygulamaninAboneligiErisimVermez(): void
    {
        $this->bagla('a@example.com', 'u1');
        $this->abonelik('baska', 'a@example.com', 'active');
        $this->istek('GET', '/apps/keepchat/kullanicilar/u1/hak');

        self::assertSame(['aktif' => false, 'durum' => null], $this->govde());
    }

    public function testBaglanmamisKullanici404(): void
    {
        $this->istek('GET', '/apps/keepchat/kullanicilar/kimse/hak');

        self::assertResponseStatusCodeSame(404);
        Sozlesme::yanitiDogrula('GET', '/apps/{app}/kullanicilar/{kullanici}/hak', $this->client->getResponse());
    }

    // --- kısıtlar (tuzak §4) ---

    public function testBaglantiKisitiUygulamasizDaDuser(): void
    {
        $kisi = $this->db->fetchOne("INSERT INTO kisiler (email) VALUES ('k@example.com') RETURNING id");
        $this->db->insert('baglantilar', ['app' => 'keepchat', 'harici_kullanici' => 'u1', 'kisi_id' => $kisi]);
        $this->expectException(UniqueConstraintViolationException::class);
        $this->db->insert('baglantilar', ['app' => 'keepchat', 'harici_kullanici' => 'u1', 'kisi_id' => $kisi]);
    }

    public function testKisiEpostasiBuyukHarfleYazilamaz(): void
    {
        $this->expectException(DriverException::class);
        $this->db->insert('kisiler', ['email' => 'Buyuk@Example.com']);
    }

    // --- yardımcılar ---

    /** @param array<string, mixed>|null $govde */
    private function istek(string $method, string $yol, ?array $govde = null, string $anahtar = self::ANAHTAR): void
    {
        $sunucu = ['HTTP_AUTHORIZATION' => 'Bearer '.$anahtar, 'CONTENT_TYPE' => 'application/json'];
        $this->client->request($method, $yol, [], [], $sunucu, null === $govde ? null : (string) json_encode($govde));
    }

    private function kodIste(string $email): string
    {
        $this->istek('POST', '/apps/keepchat/baglama/kod', ['email' => $email]);
        self::assertResponseStatusCodeSame(202);

        return $this->sonKod();
    }

    private function sonKod(): string
    {
        $mesaj = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mesaj);
        preg_match('/\b(\d{6})\b/', (string) $mesaj->getTextBody(), $m);
        self::assertArrayHasKey(1, $m, 'e-postada 6 haneli kod yok');

        return $m[1];
    }

    private function baskaKod(string $kod): string
    {
        return str_pad((string) (((int) $kod + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function bagla(string $email, string $kullanici): void
    {
        $this->istek('POST', '/apps/keepchat/baglama/dogrula', ['email' => $email, 'kod' => $this->kodIste($email), 'kullanici' => $kullanici]);
        self::assertResponseStatusCodeSame(200);
    }

    private function abonelik(string $app, string $email, string $durum): void
    {
        $this->db->insert('subscriptions', [
            'stripe_subscription_id' => 'sub_'.bin2hex(random_bytes(4)), 'stripe_customer_id' => 'cus_x',
            'status' => $durum, 'email' => $email, 'app' => $app,
        ]);
    }

    /** @return array<mixed> */
    private function govde(): array
    {
        $g = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($g) ? $g : [];
    }

    private function say(string $sql): int
    {
        $n = $this->db->fetchOne($sql);

        return is_numeric($n) ? (int) $n : -1;
    }
}
