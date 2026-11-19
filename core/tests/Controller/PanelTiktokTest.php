<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Atif\DonusumAktarimi;
use App\Atif\TiktokBaglantisi;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\SahteRevenueCat;
use App\Tests\Support\SahteTiktok;
use App\Tests\Support\Sozlesme;
use App\Uygulama\Sifreleyici;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A31: TikTok bağlantısı panelden (A30'un karşılığı) — anahtarlar DB'de
 * şifreli, panelde yalnız son 4 hane; boş anahtar alanı "değiştirme", ''
 * kaldırır. "Bağlantıyı dene": dönüşüm yalnız test olay koduyla deneme olayı
 * (Events API anahtarını yan etkisiz okuyan uç yok), harcama reklamveren
 * bilgisini Marketing API anahtarıyla okur; mesaj anahtar içermez. Dönüşüm
 * gönderimi gönderim anındaki bağlantıyı (Pixel + anahtar) okur.
 */
final class PanelTiktokTest extends WebTestCase
{
    private const EVENTS = 'ttevents_anahtari_0123456789';
    private const RAPOR = 'ttrapor_anahtari_9876543210';

    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE tiktok_baglantilari, reklam_donusumleri');
        SahteTiktok::sifirla();
        SahteRevenueCat::sifirla();
    }

    public function testKaydetMaskeliVeSifreli(): void
    {
        $jeton = $this->giris();
        $this->put($jeton, ['pixelKodu' => ' CABCDE12345 ', 'eventsAnahtari' => self::EVENTS, 'testKodu' => 'TEST123', 'reklamveren' => '7123456789012', 'raporAnahtari' => self::RAPOR]);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/tiktok', $this->client->getResponse());
        $j = $this->json();
        self::assertSame(['CABCDE12345', ['durum' => 'kayitli', 'son4' => '6789'], 'TEST123', '7123456789012', ['durum' => 'kayitli', 'son4' => '3210'], true],
            [$j['pixelKodu'], $j['eventsAnahtari'], $j['testKodu'], $j['reklamveren'], $j['raporAnahtari'], $j['sifrelemeHazir']]);

        // Yanıtlarda ve DB'de anahtarın kendisi yok; DB değeri çözülünce anahtar.
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/tiktok');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/tiktok', $this->client->getResponse());
        $govde = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString(self::EVENTS, $govde);
        self::assertStringNotContainsString(self::RAPOR, $govde);
        $satir = $this->db->fetchAssociative('SELECT events_anahtari, rapor_anahtari FROM tiktok_baglantilari');
        self::assertIsArray($satir);
        self::assertIsString($satir['events_anahtari']);
        self::assertIsString($satir['rapor_anahtari']);
        self::assertStringNotContainsString('anahtari_0123456789', $satir['events_anahtari'].base64_decode($satir['events_anahtari']));
        self::assertSame(self::EVENTS, $this->sifreleyici()->coz($satir['events_anahtari']));
        self::assertSame(self::RAPOR, $this->sifreleyici()->coz($satir['rapor_anahtari']));
        self::assertSame(['pixel' => 'CABCDE12345', 'events' => self::EVENTS, 'testKodu' => 'TEST123', 'reklamveren' => '7123456789012', 'rapor' => self::RAPOR], $this->baglanti()->oku('keepchat'));
    }

    /** Anahtar alanı yoksa / null ise değişmez; '' kaldırır; düz alan boşsa kaldırılır. */
    public function testAnahtarDegismezYaDaKaldirilir(): void
    {
        $jeton = $this->giris();
        $this->put($jeton, ['pixelKodu' => 'CABCDE12345', 'eventsAnahtari' => self::EVENTS, 'raporAnahtari' => self::RAPOR]);
        $this->put($jeton, ['pixelKodu' => 'CZZZZZ99999', 'eventsAnahtari' => null]);
        self::assertResponseIsSuccessful();
        $t = $this->baglanti()->oku('keepchat');
        self::assertSame(['CZZZZZ99999', self::EVENTS, self::RAPOR], [$t['pixel'], $t['events'], $t['rapor']]);

        $this->put($jeton, ['pixelKodu' => '', 'eventsAnahtari' => '']);
        $t = $this->baglanti()->oku('keepchat');
        self::assertSame([null, null, self::RAPOR], [$t['pixel'], $t['events'], $t['rapor']]);
        self::assertSame(['durum' => 'yok', 'son4' => null], $this->json()['eventsAnahtari']);
    }

    public function testDogrulama(): void
    {
        $jeton = $this->giris();
        $hata = function (array $govde) use ($jeton): string {
            $this->put($jeton, $govde);
            self::assertResponseStatusCodeSame(422);
            Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/tiktok', $this->client->getResponse());

            return implode(' | ', array_map(static fn (mixed $h): string => \is_string($h) ? $h : '', (array) ($this->json()['hatalar'] ?? [])));
        };
        self::assertStringStartsWith('pixelKodu:', $hata(['pixelKodu' => 'cabcde12345']));
        self::assertStringStartsWith('reklamveren:', $hata(['reklamveren' => 'act_12345678']));
        self::assertStringStartsWith('testKodu:', $hata(['testKodu' => 'TEST 1']));
        self::assertStringStartsWith('eventsAnahtari:', $hata(['eventsAnahtari' => 'kisa']));
        self::assertStringStartsWith('raporAnahtari:', $hata(['raporAnahtari' => 'boşluk var içinde 0123456789']));
        self::assertStringStartsWith('gövde yalnız', $hata(['pixelKodu' => 'CABCDE12345', 'pixelId' => '123']));
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM tiktok_baglantilari'));

        $this->put('yanlis', ['pixelKodu' => 'CABCDE12345']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('PUT', '/panel/api/uygulamalar/yok/tiktok', server: ['HTTP_X_CSRF_TOKEN' => $jeton], content: '{}');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM tiktok_baglantilari'));
        // DB kısıtı ayrıca (tuzak §4).
        $this->expectException(DriverException::class);
        $this->db->insert('tiktok_baglantilari', ['app' => 'keepchat', 'reklamveren' => 'act_123456']);
    }

    /** Şifreleme anahtarı yoksa anahtar kaydedilmez; değişmişse okunamaz — gönderim kapanır. */
    public function testSifrelemeAnahtariYoksaYaDaDegistiyse(): void
    {
        $anahtarsiz = $this->baglanti(new Sifreleyici(''));
        self::assertSame(['anahtar saklanamıyor: sunucuda şifreleme anahtarı (AYAR_SIFRELEME_ANAHTARI) yok'], $anahtarsiz->kaydet('keepchat', ['pixelKodu' => 'CABCDE12345', 'eventsAnahtari' => self::EVENTS]));
        self::assertFalse($anahtarsiz->panel('keepchat')['sifrelemeHazir']);
        self::assertSame([], $anahtarsiz->kaydet('keepchat', ['pixelKodu' => 'CABCDE12345']), 'anahtarsız alanlar kaydedilir');
        self::assertFalse($this->baglanti()->donusumAcik('keepchat'), 'anahtarsız Pixel gönderim açmaz');

        self::assertSame([], $this->baglanti()->kaydet('keepchat', ['pixelKodu' => 'CABCDE12345', 'eventsAnahtari' => self::EVENTS]));
        self::assertTrue($this->baglanti()->donusumAcik('keepchat'));
        $baska = $this->baglanti(new Sifreleyici(base64_encode(str_repeat('x', 32))));
        self::assertSame(['durum' => 'okunamadi', 'son4' => null], $baska->panel('keepchat')['eventsAnahtari']);
        self::assertNull($baska->oku('keepchat')['events']);
        self::assertFalse($baska->donusumAcik('keepchat'));
    }

    /**
     * Dene: dönüşüm test kodu yoksa istek atmaz (canlı veriye olay düşmesin);
     * varsa test olayı Events API anahtarıyla gider. Harcama reklamveren
     * bilgisini Marketing API anahtarıyla okur; TikTok hatası anahtarsız döner.
     */
    public function testDene(): void
    {
        $jeton = $this->giris();
        $this->dene($jeton);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/tiktok/dene', $this->client->getResponse());
        $j = $this->json();
        self::assertSame([['durum' => 'ayarsiz', 'mesaj' => 'Pixel kodu ve Events API anahtarı girilmedi.'], ['durum' => 'ayarsiz', 'mesaj' => 'Reklamveren kimliği ve Marketing API anahtarı girilmedi.']], [$j['donusum'], $j['harcama']]);

        // Test kodu yok: dönüşüm denenmez, istek yok.
        $this->put($jeton, ['pixelKodu' => 'CABCDE12345', 'eventsAnahtari' => self::EVENTS]);
        $this->dene($jeton);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/tiktok/dene', $this->client->getResponse());
        $donusum = $this->json()['donusum'];
        self::assertIsArray($donusum);
        // Ç158 yan not: bilgiler kayıtlı, yalnız test kodu yok → "ayarsız" değil "kayıtlı".
        self::assertSame('kayitli', $donusum['durum'] ?? null);
        self::assertSame(0, SahteRevenueCat::sayi(), 'test kodu yokken olay gönderilmez');

        $this->put($jeton, ['pixelKodu' => 'CABCDE12345', 'testKodu' => 'TEST123', 'reklamveren' => '7123456789012', 'raporAnahtari' => self::RAPOR]);
        SahteTiktok::$yanitlar = [
            '/open_api/v1.3/advertiser/info/' => [200, ['code' => 40001, 'message' => 'Access token is invalid: '.self::RAPOR, 'data' => []]],
        ];
        $this->dene($jeton);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/tiktok/dene', $this->client->getResponse());
        $j = $this->json();
        self::assertSame('tamam', \is_array($j['donusum']) ? ($j['donusum']['durum'] ?? null) : null);
        // Deneme olayı: Events API anahtarı, kayıtlı Pixel ve test kodu.
        self::assertSame(1, SahteRevenueCat::sayi());
        self::assertSame('https://business-api.tiktok.com/open_api/v1.3/event/track/', SahteRevenueCat::istek(0)['url']);
        self::assertContains('Access-Token: '.self::EVENTS, SahteRevenueCat::istek(0)['headers']);
        $olay = SahteRevenueCat::govde(0);
        self::assertSame(['CABCDE12345', 'TEST123'], [$olay['event_source_id'] ?? null, $olay['test_event_code'] ?? null]);
        $harcama = $j['harcama'];
        self::assertIsArray($harcama);
        self::assertSame('hata', $harcama['durum'] ?? null);
        self::assertSame('TikTok reddetti: Access token is invalid: … (kod 40001)', $harcama['mesaj'] ?? null);
        self::assertStringNotContainsString(self::RAPOR, (string) $this->client->getResponse()->getContent());

        SahteTiktok::$yanitlar = [
            '/open_api/v1.3/advertiser/info/' => [200, ['code' => 0, 'message' => 'OK', 'data' => ['list' => [['name' => 'web2quiz', 'currency' => 'EUR']]]]],
        ];
        $this->dene($jeton);
        self::assertSame(['durum' => 'tamam', 'mesaj' => 'Reklamveren: web2quiz (EUR)'], $this->json()['harcama']);
        $sorgu = SahteTiktok::$sorgular[1] ?? [];
        self::assertSame('["7123456789012"]', $sorgu['advertiser_ids'] ?? null);
        self::assertIsArray($sorgu['basliklar'] ?? null);
        self::assertContains('Access-Token: '.self::RAPOR, $sorgu['basliklar']);

        $this->client->request('POST', '/panel/api/uygulamalar/keepchat/tiktok/dene', server: ['HTTP_X_CSRF_TOKEN' => 'yanlis']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testKaldir(): void
    {
        $jeton = $this->giris();
        $this->put($jeton, ['pixelKodu' => 'CABCDE12345', 'eventsAnahtari' => self::EVENTS]);
        $this->client->request('DELETE', '/panel/api/uygulamalar/keepchat/tiktok', server: ['HTTP_X_CSRF_TOKEN' => 'yanlis']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('DELETE', '/panel/api/uygulamalar/keepchat/tiktok', server: ['HTTP_X_CSRF_TOKEN' => $jeton]);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('DELETE', '/panel/api/uygulamalar/{app}/tiktok', $this->client->getResponse());
        self::assertSame('', $this->json()['pixelKodu']);
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM tiktok_baglantilari'));
        $this->client->request('DELETE', '/panel/api/uygulamalar/keepchat/tiktok', server: ['HTTP_X_CSRF_TOKEN' => $jeton]);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Kuyruktaki dönüşüm kendi uygulamasının bağlantısıyla gider — Pixel kodu
     * yükteki eski değer değil, gönderim anındaki; bağlantı kalktıysa gitmez.
     */
    public function testDonusumGonderimdekiBaglantiyla(): void
    {
        self::assertSame([], $this->baglanti()->kaydet('keepchat', ['pixelKodu' => 'CABCDE12345', 'eventsAnahtari' => self::EVENTS]));
        self::assertSame([], $this->baglanti()->kaydet('baska', ['pixelKodu' => 'CBASKA54321', 'eventsAnahtari' => 'baska_events_anahtari_0123']));
        foreach (['keepchat' => 'cs_k', 'baska' => 'cs_b', 'yok' => 'cs_y'] as $app => $olay) {
            $this->db->insert('reklam_donusumleri', ['saglayici' => 'tiktok', 'app' => $app, 'event_id' => $olay, 'payload' => '{"event_source_id":"CESKIPIXEL00","data":[{"event_id":"'.$olay.'"}]}']);
        }
        $aktarim = static::getContainer()->get(DonusumAktarimi::class);
        self::assertInstanceOf(DonusumAktarimi::class, $aktarim);

        self::assertSame(['gonderilen' => 2, 'basarisiz' => 1], $aktarim->gonder());
        self::assertContains('Access-Token: '.self::EVENTS, SahteRevenueCat::istek(0)['headers']);
        self::assertContains('Access-Token: baska_events_anahtari_0123', SahteRevenueCat::istek(1)['headers']);
        self::assertSame(['CABCDE12345', 'CBASKA54321'], [SahteRevenueCat::govde(0)['event_source_id'] ?? null, SahteRevenueCat::govde(1)['event_source_id'] ?? null]);
        self::assertSame('uygulamanın TikTok bağlantısı yok', $this->db->fetchOne("SELECT son_hata FROM reklam_donusumleri WHERE event_id = 'cs_y'"));
    }

    private function baglanti(?Sifreleyici $sifreleyici = null): TiktokBaglantisi
    {
        if (null === $sifreleyici) {
            $b = static::getContainer()->get(TiktokBaglantisi::class);
            self::assertInstanceOf(TiktokBaglantisi::class, $b);

            return $b;
        }
        $http = static::getContainer()->get(HttpClientInterface::class);
        self::assertInstanceOf(HttpClientInterface::class, $http);

        return new TiktokBaglantisi($this->db, $sifreleyici, $http);
    }

    private function sifreleyici(): Sifreleyici
    {
        $s = static::getContainer()->get(Sifreleyici::class);
        self::assertInstanceOf(Sifreleyici::class, $s);

        return $s;
    }

    private function giris(): string
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
        $this->client->request('GET', '/panel/entegrasyonlar');

        return (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
    }

    /** @param array<mixed> $govde */
    private function put(string $jeton, array $govde): void
    {
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/tiktok', server: ['HTTP_X_CSRF_TOKEN' => $jeton, 'CONTENT_TYPE' => 'application/json'], content: (string) json_encode($govde));
    }

    private function dene(string $jeton): void
    {
        $this->client->request('POST', '/panel/api/uygulamalar/keepchat/tiktok/dene', server: ['HTTP_X_CSRF_TOKEN' => $jeton]);
    }

    /**
     * @phpstan-impure
     *
     * @return array<string, mixed>
     */
    private function json(): array
    {
        /** @var array<string, mixed> $v */
        $v = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $v;
    }
}
