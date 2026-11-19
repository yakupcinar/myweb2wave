<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Ç152: paneldeki her yazma tek dinleyiciden panel_denetim'e yazılır — kim,
 * hangi rota, hangi kaynak, sonuç. Gövde (anahtar, sır) yazılmaz; okuma ve
 * oturumsuz istek yazılmaz; reddedilen deneme durumuyla yazılır; iz
 * sonradan değiştirilemez.
 */
final class PanelDenetimTest extends WebTestCase
{
    private const CAPI = 'EAAGdenetim_gizli_anahtar_0123456789';

    private KernelBrowser $client;
    private Connection $db;
    private string $jeton = '';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE panel_denetim, webhook_hedefleri, webhook_teslimleri, meta_baglantilari');
    }

    private function giris(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
        $this->client->request('GET', '/panel/entegrasyonlar');
        self::assertResponseIsSuccessful();
        $this->jeton = (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
    }

    /** @param array<string, mixed> $govde */
    private function istek(string $yontem, string $yol, array $govde = [], ?string $jeton = null): void
    {
        $this->client->request($yontem, '/panel/api/uygulamalar/keepchat'.$yol, server: ['HTTP_X_CSRF_TOKEN' => $jeton ?? $this->jeton], content: (string) json_encode($govde));
    }

    /** @return list<array{kullanici: string, yontem: string, rota: string, kaynak: array<string, string>, durum: int}> */
    private function kayitlar(): array
    {
        /** @var list<array{kullanici: string, yontem: string, rota: string, kaynak: string, durum: int}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative('SELECT kullanici, yontem, rota, kaynak, durum FROM panel_denetim ORDER BY id');

        return array_map(static function (array $s): array {
            /** @var array<string, string> $kaynak */
            $kaynak = json_decode($s['kaynak'], true, flags: \JSON_THROW_ON_ERROR);
            ksort($kaynak); // JSONB anahtar sırasını tutmaz (tuzak §28)
            $s['kaynak'] = $kaynak;

            return $s;
        }, $satirlar);
    }

    public function testYazmaKimRotaKaynakDurumIleYazilir(): void
    {
        $this->giris();
        $this->istek('POST', '/webhooklar', ['adres' => 'https://alici.example.com/kanca', 'olaylar' => ['odeme.fatura']]);
        self::assertResponseStatusCodeSame(201);
        $id = $this->db->fetchOne('SELECT id::text FROM webhook_hedefleri');
        self::assertIsString($id);
        $this->istek('DELETE', '/webhooklar/'.$id);
        self::assertResponseStatusCodeSame(204);

        self::assertSame([
            ['kullanici' => 'yonetici', 'yontem' => 'POST', 'rota' => 'panel_api_webhook_ekle', 'kaynak' => ['app' => 'keepchat'], 'durum' => 201],
            ['kullanici' => 'yonetici', 'yontem' => 'DELETE', 'rota' => 'panel_api_webhook_sil', 'kaynak' => ['app' => 'keepchat', 'id' => $id], 'durum' => 204],
        ], $this->kayitlar());
    }

    public function testReddedilenDenemeDurumuylaYazilir(): void
    {
        $this->giris();
        $this->istek('POST', '/webhooklar', ['adres' => 'http://duz.example.com', 'olaylar' => ['odeme.fatura']]);
        self::assertResponseStatusCodeSame(422);
        $this->istek('POST', '/webhooklar', ['adres' => 'https://alici.example.com', 'olaylar' => ['odeme.fatura']], 'yanlis-jeton');
        self::assertResponseStatusCodeSame(403);

        // /panel/api dışındaki panel yazması da (düzenleyicinin taslak ucu).
        $this->client->request('POST', '/panel/huniler/keepchat/genel/taslak', server: ['HTTP_X_CSRF_TOKEN' => 'yanlis-jeton'], content: '{}');

        self::assertSame([[422, 'panel_api_webhook_ekle'], [403, 'panel_api_webhook_ekle'], [$this->client->getResponse()->getStatusCode(), 'panel_huni_taslak']], array_map(static fn (array $k): array => [$k['durum'], $k['rota']], $this->kayitlar()));
        self::assertSame(0, $this->db->fetchOne('SELECT count(*)::int FROM webhook_hedefleri'), 'reddedilen hiçbir şey eklemedi');
    }

    public function testGovdeYazilmaz(): void
    {
        $this->giris();
        $this->istek('PUT', '/meta', ['pixelId' => '987654321', 'capiAnahtari' => self::CAPI]);
        self::assertResponseIsSuccessful();

        self::assertSame(['panel_api_meta_kaydet'], array_column($this->kayitlar(), 'rota'));
        $tumu = $this->db->fetchOne('SELECT string_agg(row_to_json(d)::text, \'\') FROM panel_denetim d');
        self::assertIsString($tumu);
        self::assertStringNotContainsString('denetim_gizli', $tumu, 'anahtar izde yok');
        self::assertStringNotContainsString('987654321', $tumu, 'gövdedeki hiçbir değer izde yok');
    }

    public function testOkumaVeOturumsuzIstekYazilmaz(): void
    {
        $this->istek('POST', '/webhooklar', ['adres' => 'https://alici.example.com/kanca', 'olaylar' => ['odeme.fatura']], 'x');
        self::assertNotSame(201, $this->client->getResponse()->getStatusCode(), 'oturumsuz ekleyemez');
        self::assertSame([], $this->kayitlar(), 'oturumsuz istek yazılmaz');

        $this->giris();
        $this->istek('GET', '/webhooklar');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->kayitlar(), 'okuma yazılmaz');
    }

    public function testIzDegistirilemez(): void
    {
        $this->giris();
        $this->istek('DELETE', '/meta');
        self::assertCount(1, $this->kayitlar());

        foreach (['UPDATE panel_denetim SET durum = 200', 'DELETE FROM panel_denetim'] as $sql) {
            try {
                $this->db->executeStatement($sql);
                self::fail("geçmemeliydi: {$sql}");
            } catch (\Doctrine\DBAL\Exception $e) {
                self::assertStringContainsString('panel_denetim yalnız ekleme alır', $e->getMessage());
            }
        }
        self::assertCount(1, $this->kayitlar());
    }

    public function testKomutSonKayitlariYenidenEskiyeYazar(): void
    {
        $this->giris();
        $this->istek('DELETE', '/meta');
        $this->istek('DELETE', '/tiktok');

        $kernel = static::$kernel;
        self::assertNotNull($kernel);
        $komut = new CommandTester((new Application($kernel))->find('app:panel-denetim'));
        self::assertSame(0, $komut->execute(['--son' => '1']));
        $satirlar = array_values(array_filter(explode("\n", $komut->getDisplay())));
        self::assertCount(1, $satirlar);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ · yonetici · DELETE panel_api_tiktok_sil \{"app":"keepchat"\} → \d{3}$/', $satirlar[0]);

        self::assertSame(2, $komut->execute(['--son' => '0']), 'sınır dışı reddedilir');
    }

    public function testPanelUcuSozlesmeyleYenidenEskiye(): void
    {
        $this->giris();
        $this->istek('DELETE', '/meta');
        $this->client->request('POST', '/panel/api/abonelikler/sub_YOK123/iptal', server: ['HTTP_X_CSRF_TOKEN' => $this->jeton]);
        // Parametresiz rota (panele giriş): kaynak JSON'da {} olmalı, [] değil (sözleşme nesne ister).
        $this->db->insert('panel_denetim', ['kullanici' => 'yonetici', 'yontem' => 'POST', 'rota' => 'panel_giris', 'kaynak' => '{}', 'durum' => 302]);

        $this->client->request('GET', '/panel/api/denetim');
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('GET', '/panel/api/denetim', $this->client->getResponse());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        /** @var array{kayitlar: list<array{rota: string, kaynak: array<string, string>}>} $j */
        $j = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('"kaynak":{}', (string) $this->client->getResponse()->getContent());
        self::assertSame([
            ['panel_giris', []],
            ['panel_api_abonelik_iptal', ['abonelik' => 'sub_YOK123']],
            ['panel_api_meta_sil', ['app' => 'keepchat']],
        ], array_map(static fn (array $k): array => [$k['rota'], $k['kaynak']], $j['kayitlar']));
        self::assertCount(3, $this->kayitlar(), 'okuma ucu kendini yazmaz');
    }

    public function testOturumsuzOkunmazSayfaDogrudanAcilir(): void
    {
        $this->client->request('GET', '/panel/api/denetim');
        self::assertResponseRedirects('/panel/giris');
        $this->client->request('GET', '/panel/islem-gecmisi');
        self::assertResponseRedirects('/panel/giris');

        $this->giris();
        $this->client->request('GET', '/panel/islem-gecmisi');
        self::assertResponseIsSuccessful('doğrudan adres de açılır (tuzak §51)');
        self::assertCount(1, $this->client->getCrawler()->filter('#panel-uygulama'));
    }

    /** Yeni panel yazma ucu eklenince İşlem geçmişi'nde okunur adı da yazılmalı (yoksa rota adı görünür). */
    public function testHerPanelYazmaRotasininSayfadaAdiVar(): void
    {
        $router = static::getContainer()->get('router');
        self::assertInstanceOf(\Symfony\Component\Routing\RouterInterface::class, $router);
        $sayfa = (string) file_get_contents(\dirname(__DIR__, 2).'/panel/src/uygulama/sayfalar/IslemGecmisi.tsx');
        $yazmalar = [];
        foreach ($router->getRouteCollection() as $ad => $rota) {
            if (str_starts_with($rota->getPath(), '/panel') && [] !== array_intersect($rota->getMethods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                $yazmalar[] = $ad;
            }
        }
        self::assertGreaterThanOrEqual(45, \count($yazmalar), 'rota listesi okundu');
        self::assertSame([], array_values(array_filter($yazmalar, static fn (string $ad): bool => !str_contains($sayfa, "    {$ad}: "))));
    }
}
