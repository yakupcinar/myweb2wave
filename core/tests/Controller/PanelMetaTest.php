<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Atif\DonusumAktarimi;
use App\Atif\MetaBaglantisi;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\SahteMetaGraph;
use App\Tests\Support\SahteRevenueCat;
use App\Tests\Support\Sozlesme;
use App\Uygulama\Sifreleyici;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A30: Meta bağlantısı panelden — anahtarlar DB'de şifreli, panelde yalnız
 * son 4 hane; boş anahtar alanı "değiştirme", '' kaldırır. "Bağlantıyı
 * dene" Graph'a gerçek istek (testte SahteMetaGraph); mesaj anahtar içermez.
 * Dönüşüm gönderimi gönderim anındaki bağlantıyı okur.
 */
final class PanelMetaTest extends WebTestCase
{
    private const CAPI = 'EAAGcapi_anahtari_0123456789';
    private const INSIGHTS = 'EAAGinsights_anahtari_987654';

    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE meta_baglantilari, reklam_donusumleri');
        SahteMetaGraph::sifirla();
        SahteRevenueCat::sifirla();
    }

    public function testKaydetMaskeliVeSifreli(): void
    {
        $jeton = $this->giris();
        $this->put($jeton, ['pixelId' => ' 987654321 ', 'capiAnahtari' => self::CAPI, 'testKodu' => 'TEST123', 'reklamHesabi' => 'act_12345678', 'insightsAnahtari' => self::INSIGHTS]);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/meta', $this->client->getResponse());
        $j = $this->json();
        self::assertSame(['987654321', ['durum' => 'kayitli', 'son4' => '6789'], 'TEST123', 'act_12345678', ['durum' => 'kayitli', 'son4' => '7654'], true, 'v99.0'],
            [$j['pixelId'], $j['capiAnahtari'], $j['testKodu'], $j['reklamHesabi'], $j['insightsAnahtari'], $j['sifrelemeHazir'], $j['surum']]);

        // Yanıtlarda ve DB'de anahtarın kendisi yok; DB değeri çözülünce anahtar.
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/meta');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/meta', $this->client->getResponse());
        $govde = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString(self::CAPI, $govde);
        self::assertStringNotContainsString(self::INSIGHTS, $govde);
        $satir = $this->db->fetchAssociative('SELECT capi_anahtari, insights_anahtari FROM meta_baglantilari');
        self::assertIsArray($satir);
        self::assertIsString($satir['capi_anahtari']);
        self::assertStringNotContainsString('capi_anahtari_0123456789', $satir['capi_anahtari'].base64_decode($satir['capi_anahtari']));
        self::assertSame(self::CAPI, $this->sifreleyici()->coz($satir['capi_anahtari']));
        self::assertSame(['pixel' => '987654321', 'capi' => self::CAPI, 'testKodu' => 'TEST123', 'hesap' => 'act_12345678', 'insights' => self::INSIGHTS], $this->baglanti()->oku('keepchat'));
    }

    /** Anahtar alanı yoksa / null ise değişmez; '' kaldırır; düz alan boşsa kaldırılır. */
    public function testAnahtarDegismezYaDaKaldirilir(): void
    {
        $jeton = $this->giris();
        $this->put($jeton, ['pixelId' => '987654321', 'capiAnahtari' => self::CAPI, 'insightsAnahtari' => self::INSIGHTS]);
        $this->put($jeton, ['pixelId' => '111112222', 'capiAnahtari' => null]);
        self::assertResponseIsSuccessful();
        self::assertSame(['111112222', self::CAPI, self::INSIGHTS], array_values(array_intersect_key($this->baglanti()->oku('keepchat'), array_flip(['pixel', 'capi', 'insights']))));

        $this->put($jeton, ['pixelId' => '', 'capiAnahtari' => '']);
        $m = $this->baglanti()->oku('keepchat');
        self::assertSame([null, null, self::INSIGHTS], [$m['pixel'], $m['capi'], $m['insights']]);
        self::assertSame(['durum' => 'yok', 'son4' => null], $this->json()['capiAnahtari']);
    }

    public function testDogrulama(): void
    {
        $jeton = $this->giris();
        $hata = function (array $govde) use ($jeton): string {
            $this->put($jeton, $govde);
            self::assertResponseStatusCodeSame(422);
            Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/meta', $this->client->getResponse());

            return implode(' | ', array_map(static fn (mixed $h): string => \is_string($h) ? $h : '', (array) ($this->json()['hatalar'] ?? [])));
        };
        self::assertStringStartsWith('pixelId:', $hata(['pixelId' => '12ab']));
        self::assertStringStartsWith('reklamHesabi:', $hata(['reklamHesabi' => '12345678']));
        self::assertStringStartsWith('testKodu:', $hata(['testKodu' => 'TEST 1']));
        self::assertStringStartsWith('capiAnahtari:', $hata(['capiAnahtari' => 'kisa']));
        self::assertStringStartsWith('insightsAnahtari:', $hata(['insightsAnahtari' => 'boşluk var içinde 0123456789']));
        self::assertStringStartsWith('gövde yalnız', $hata(['pixelId' => '987654321', 'erisim' => 'x']));
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM meta_baglantilari'));

        $this->put('yanlis', ['pixelId' => '987654321']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('PUT', '/panel/api/uygulamalar/yok/meta', server: ['HTTP_X_CSRF_TOKEN' => $jeton], content: '{}');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM meta_baglantilari'));
        // DB kısıtı ayrıca (tuzak §4).
        $this->expectException(DriverException::class);
        $this->db->insert('meta_baglantilari', ['app' => 'keepchat', 'pixel_id' => 'abc']);
    }

    /** Şifreleme anahtarı yoksa anahtar kaydedilmez; değişmişse okunamaz — gönderim kapanır. */
    public function testSifrelemeAnahtariYoksaYaDaDegistiyse(): void
    {
        $anahtarsiz = $this->baglanti(new Sifreleyici(''));
        self::assertSame(['anahtar saklanamıyor: sunucuda şifreleme anahtarı (AYAR_SIFRELEME_ANAHTARI) yok'], $anahtarsiz->kaydet('keepchat', ['pixelId' => '987654321', 'capiAnahtari' => self::CAPI]));
        self::assertFalse($anahtarsiz->panel('keepchat')['sifrelemeHazir']);
        self::assertSame([], $anahtarsiz->kaydet('keepchat', ['pixelId' => '987654321']), 'anahtarsız alanlar kaydedilir');

        self::assertSame([], $this->baglanti()->kaydet('keepchat', ['pixelId' => '987654321', 'capiAnahtari' => self::CAPI]));
        self::assertTrue($this->baglanti()->donusumAcik('keepchat'));
        $baska = $this->baglanti(new Sifreleyici(base64_encode(str_repeat('x', 32))));
        self::assertSame(['durum' => 'okunamadi', 'son4' => null], $baska->panel('keepchat')['capiAnahtari']);
        self::assertNull($baska->oku('keepchat')['capi']);
        self::assertFalse($baska->donusumAcik('keepchat'));
    }

    /** Dene: Pixel CAPI anahtarıyla, hesap Insights anahtarıyla okunur; Meta hatası anahtarsız döner. */
    public function testDene(): void
    {
        $jeton = $this->giris();
        $this->dene($jeton);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/meta/dene', $this->client->getResponse());
        $j = $this->json();
        self::assertSame([['durum' => 'ayarsiz', 'mesaj' => 'Pixel ID ve CAPI anahtarı girilmedi.'], ['durum' => 'ayarsiz', 'mesaj' => 'Reklam hesabı ve Insights anahtarı girilmedi.']], [$j['donusum'], $j['harcama']]);
        self::assertSame([], SahteMetaGraph::$sorgular, 'ayarsızken istek yok');

        $this->put($jeton, ['pixelId' => '987654321', 'capiAnahtari' => self::CAPI, 'reklamHesabi' => 'act_12345678', 'insightsAnahtari' => self::INSIGHTS]);
        SahteMetaGraph::$yanitlar = [
            '/v99.0/987654321' => [200, ['name' => 'KeepChat Pixel', 'id' => '987654321']],
            '/v99.0/act_12345678' => [400, ['error' => ['message' => 'Invalid OAuth access token '.self::INSIGHTS, 'code' => 190]]],
        ];
        $this->dene($jeton);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/meta/dene', $this->client->getResponse());
        $j = $this->json();
        self::assertSame(['durum' => 'tamam', 'mesaj' => 'Pixel: KeepChat Pixel'], $j['donusum']);
        $harcama = $j['harcama'];
        self::assertIsArray($harcama);
        self::assertSame('hata', $harcama['durum'] ?? null);
        self::assertIsString($harcama['mesaj'] ?? null);
        self::assertStringStartsWith('Meta reddetti: Invalid OAuth access token', $harcama['mesaj']);
        self::assertStringNotContainsString(self::INSIGHTS, (string) $this->client->getResponse()->getContent());
        /** @var list<array<int|string, mixed>> $sorgular statik alan; önceki boşluk denetimi tipi daraltmasın */
        $sorgular = SahteMetaGraph::$sorgular;
        self::assertSame([['/v99.0/987654321', self::CAPI, 'name'], ['/v99.0/act_12345678', self::INSIGHTS, 'name,currency']],
            array_map(static fn (array $s): array => [$s['yol'] ?? null, $s['access_token'] ?? null, $s['fields'] ?? null], $sorgular));

        $this->client->request('POST', '/panel/api/uygulamalar/keepchat/meta/dene', server: ['HTTP_X_CSRF_TOKEN' => 'yanlis']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testKaldir(): void
    {
        $jeton = $this->giris();
        $this->put($jeton, ['pixelId' => '987654321', 'capiAnahtari' => self::CAPI]);
        $this->client->request('DELETE', '/panel/api/uygulamalar/keepchat/meta', server: ['HTTP_X_CSRF_TOKEN' => 'yanlis']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('DELETE', '/panel/api/uygulamalar/keepchat/meta', server: ['HTTP_X_CSRF_TOKEN' => $jeton]);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('DELETE', '/panel/api/uygulamalar/{app}/meta', $this->client->getResponse());
        self::assertSame('', $this->json()['pixelId']);
        self::assertSame(0, $this->db->fetchOne('SELECT count(*) FROM meta_baglantilari'));
        $this->client->request('DELETE', '/panel/api/uygulamalar/keepchat/meta', server: ['HTTP_X_CSRF_TOKEN' => $jeton]);
        self::assertResponseStatusCodeSame(404);
    }

    /** Kuyruktaki dönüşüm kendi uygulamasının bağlantısıyla gider; bağlantı kalktıysa gitmez. */
    public function testDonusumGonderimdekiBaglantiyla(): void
    {
        self::assertSame([], $this->baglanti()->kaydet('keepchat', ['pixelId' => '987654321', 'capiAnahtari' => self::CAPI]));
        self::assertSame([], $this->baglanti()->kaydet('baska', ['pixelId' => '555556666', 'capiAnahtari' => 'baska_capi_anahtari_0123456']));
        foreach (['keepchat' => 'cs_k', 'baska' => 'cs_b', 'yok' => 'cs_y'] as $app => $olay) {
            $this->db->insert('reklam_donusumleri', ['saglayici' => 'meta', 'app' => $app, 'event_id' => $olay, 'payload' => '{"data":[{"event_id":"'.$olay.'"}]}']);
        }
        $aktarim = static::getContainer()->get(DonusumAktarimi::class);
        self::assertInstanceOf(DonusumAktarimi::class, $aktarim);

        self::assertSame(['gonderilen' => 2, 'basarisiz' => 1], $aktarim->gonder());
        self::assertSame([
            'https://graph.facebook.com/v99.0/987654321/events?access_token='.self::CAPI,
            'https://graph.facebook.com/v99.0/555556666/events?access_token=baska_capi_anahtari_0123456',
        ], array_map(static fn (int $i): string => SahteRevenueCat::istek($i)['url'], [0, 1]));
        self::assertSame('uygulamanın Meta bağlantısı yok', $this->db->fetchOne("SELECT son_hata FROM reklam_donusumleri WHERE event_id = 'cs_y'"));
    }

    private function baglanti(?Sifreleyici $sifreleyici = null): MetaBaglantisi
    {
        if (null === $sifreleyici) {
            $b = static::getContainer()->get(MetaBaglantisi::class);
            self::assertInstanceOf(MetaBaglantisi::class, $b);

            return $b;
        }
        $http = static::getContainer()->get(HttpClientInterface::class);
        self::assertInstanceOf(HttpClientInterface::class, $http);

        return new MetaBaglantisi($this->db, $sifreleyici, $http, 'v99.0');
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
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/meta', server: ['HTTP_X_CSRF_TOKEN' => $jeton, 'CONTENT_TYPE' => 'application/json'], content: (string) json_encode($govde));
    }

    private function dene(string $jeton): void
    {
        $this->client->request('POST', '/panel/api/uygulamalar/keepchat/meta/dene', server: ['HTTP_X_CSRF_TOKEN' => $jeton]);
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
