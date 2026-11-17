<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Kimlik\GirisBaglantisi;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use App\Uygulama\UygulamaAyarlari;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;

/**
 * M9a (A23): uygulama kimliği panelde — uçlar (sözleşme), doğrulama, DB
 * kısıtı ve kullanıldığı yerler: e-postanın gönderen adı / yanıt adresi,
 * giriş bağlantısının şeması. Ayar satırı yoksa yedek yok.
 */
final class PanelAyarlarTest extends WebTestCase
{
    private const DOLU = ['ad' => 'Keep Chat', 'sema' => 'keepchat2', 'magazaAdresi' => 'https://apps.apple.com/app/id123', 'destekEpostasi' => 'destek@example.com'];

    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->gocHali();
        $this->db->executeStatement('TRUNCATE dogrulama_kodlari');
    }

    /** Sonraki testler KeepChat şemasına güveniyor — değiştirilen satır geri döner (§13). */
    protected function tearDown(): void
    {
        $this->gocHali();
        parent::tearDown();
    }

    private function gocHali(): void
    {
        $this->db->executeStatement("DELETE FROM uygulama_ayarlari; INSERT INTO uygulama_ayarlari (app, ad, sema) VALUES ('keepchat', 'KeepChat', 'keepchat')");
    }

    public function testGirisYapmadanKapali(): void
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/ayarlar');
        self::assertResponseRedirects('/panel/giris');
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/ayarlar', content: '{}');
        self::assertResponseRedirects('/panel/giris');
    }

    public function testGocunTasidigiAyarlarOkunur(): void
    {
        $this->girisYap();
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/ayarlar');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/ayarlar', $this->client->getResponse());
        self::assertSame(['ad' => 'KeepChat', 'sema' => 'keepchat', 'magazaAdresi' => '', 'destekEpostasi' => ''], $this->json());

        $this->client->request('GET', '/panel/api/uygulamalar/bilinmeyen/ayarlar');
        self::assertResponseStatusCodeSame(404);
    }

    public function testKaydedilirVeKullanilir(): void
    {
        $this->girisYap();
        $this->kaydet(['ad' => '  Keep Chat  '] + self::DOLU, $this->jeton());
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/ayarlar', $this->client->getResponse());
        self::assertSame(self::DOLU, $this->json());

        // Doğrulama kodu e-postası: gönderen adı + yanıt adresi.
        $this->client->request('POST', '/apps/keepchat/baglama/kod', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer test-anahtar-keepchat', 'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['email' => 'a@example.com']));
        self::assertResponseStatusCodeSame(202);
        $mesaj = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mesaj);
        self::assertSame('Keep Chat', $mesaj->getFrom()[0]->getName());
        self::assertSame(['destek@example.com'], array_map(static fn ($a) => $a->getAddress(), $mesaj->getReplyTo()));

        // Giriş bağlantısı yeni şemayla.
        $baglanti = $this->giris()->uret($this->db, 'keepchat', 'a@example.com', 'cs_ayar');
        self::assertStringStartsWith('keepchat2://giris?jeton=', $baglanti['uygulama_adresi']);
    }

    public function testDestekBossaYanitAdresiYok(): void
    {
        $e = $this->ayarlar()->eposta('keepchat');
        self::assertSame('KeepChat', $e->getFrom()[0]->getName());
        self::assertSame([], $e->getReplyTo());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function gecersizler(): iterable
    {
        yield 'boş ad' => [['ad' => ' '] + self::DOLU];
        yield 'uzun ad' => [['ad' => str_repeat('a', 61)] + self::DOLU];
        yield 'büyük harfli şema' => [['sema' => 'KeepChat'] + self::DOLU];
        yield 'tek harfli şema' => [['sema' => 'k'] + self::DOLU];
        yield 'rakamla başlayan şema' => [['sema' => '1keep'] + self::DOLU];
        yield 'http mağaza' => [['magazaAdresi' => 'http://apps.apple.com/app/id1'] + self::DOLU];
        yield 'yarım mağaza' => [['magazaAdresi' => 'https://'] + self::DOLU];
        yield 'geçersiz e-posta' => [['destekEpostasi' => 'destek'] + self::DOLU];
        yield 'bilinmeyen alan' => [['fazla' => 'x'] + self::DOLU];
        yield 'metin değil' => [['ad' => 5] + self::DOLU];
    }

    /** @param array<string, mixed> $govde */
    #[DataProvider('gecersizler')]
    public function testGecersizReddedilir(array $govde): void
    {
        $this->girisYap();
        $this->kaydet($govde, $this->jeton());
        self::assertResponseStatusCodeSame(422);
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/ayarlar', $this->client->getResponse());
        self::assertSame('keepchat', $this->ayarlar()->oku('keepchat')['sema'], 'kayıt değişmez');
    }

    public function testJetonsuz403(): void
    {
        $this->girisYap();
        $this->kaydet(self::DOLU, '');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('keepchat', $this->ayarlar()->oku('keepchat')['sema']);
    }

    /** §4: uygulamadaki doğrulamanın arkasındaki kısıt ayrıca ölçülür. */
    public function testDbKisitiGecersizSemayiReddeder(): void
    {
        $this->expectException(DriverException::class);
        $this->db->executeStatement("UPDATE uygulama_ayarlari SET sema = 'Keep Chat' WHERE app = 'keepchat'");
    }

    public function testAyarSatiriYoksaSemaYedeksizHataVerir(): void
    {
        $this->db->executeStatement('DELETE FROM uygulama_ayarlari');
        $this->expectException(\LogicException::class);
        $this->giris()->uret($this->db, 'keepchat', 'a@example.com', 'cs_ayar');
    }

    /** §51: doğrudan adres / yenileme 404 olmasın. */
    public function testSayfaAdresiAcilir(): void
    {
        $this->girisYap();
        $this->client->request('GET', '/panel/ayarlar');
        self::assertResponseIsSuccessful();
    }

    /** @param array<string, mixed> $govde */
    private function kaydet(array $govde, string $jeton): void
    {
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/ayarlar', [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $jeton,
        ], (string) json_encode($govde));
    }

    private function jeton(): string
    {
        $this->client->request('GET', '/panel/ayarlar');
        self::assertResponseIsSuccessful();
        $jeton = (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
        self::assertNotSame('', $jeton);

        return $jeton;
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }

    private function ayarlar(): UygulamaAyarlari
    {
        $a = static::getContainer()->get(UygulamaAyarlari::class);
        self::assertInstanceOf(UygulamaAyarlari::class, $a);

        return $a;
    }

    private function giris(): GirisBaglantisi
    {
        $g = static::getContainer()->get(GirisBaglantisi::class);
        self::assertInstanceOf(GirisBaglantisi::class, $g);

        return $g;
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        /** @var array<string, mixed> $v */
        $v = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $v;
    }
}
