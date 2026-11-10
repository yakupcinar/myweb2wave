<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
use App\Yasal\Taslaklar;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M9b1 (A23): yasal veriler + metinler. Taslak yayında değildir (kaydedilmemiş
 * belge 404); yer tutucular sayfa çizilirken dolar; HTML yorumlanmaz.
 */
final class PanelYasalTest extends WebTestCase
{
    private const VERILER = ['unvan' => 'Örnek Yazılım A.Ş.', 'adres' => 'Kadıköy, İstanbul', 'kayitNo' => 'MERSİS 0123'];

    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->gocHali();
    }

    /** Sonraki testler göçteki ayar satırına güveniyor (§13). */
    protected function tearDown(): void
    {
        $this->gocHali();
        parent::tearDown();
    }

    public function testGirisYapmadanKapaliTaslakYayindaDegil(): void
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/yasal');
        self::assertResponseRedirects('/panel/giris');
        $this->client->request('PUT', '/panel/api/uygulamalar/keepchat/yasal/sartlar/tr', content: '{}');
        self::assertResponseRedirects('/panel/giris');

        $this->client->request('GET', '/yasal/keepchat/sartlar/tr');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPanelTaslaklariVeEksikleriGosterir(): void
    {
        $this->girisYap();
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/yasal');
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/yasal', $this->client->getResponse());
        $g = $this->json();

        self::assertSame(['unvan' => '', 'adres' => '', 'kayitNo' => ''], $g['veriler']);
        self::assertIsArray($g['belgeler']);
        self::assertCount(8, $g['belgeler']);
        $sartlar = $this->belge($g, 'sartlar', 'tr');
        self::assertFalse($sartlar['kayitli']);
        self::assertNull($sartlar['guncellendi']);
        self::assertSame(Taslaklar::metin('sartlar', 'tr'), $sartlar['metin']);
        self::assertSame('http://localhost/yasal/keepchat/sartlar/tr', $sartlar['adres']);
        // Ad ayarlarda dolu (KeepChat); diğerleri boş.
        self::assertEqualsCanonicalizing(['unvan', 'adres', 'kayitNo', 'eposta'], $sartlar['eksikler']);
    }

    public function testKaydedilenMetinYayindaYerTutucularDolar(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        $this->db->executeStatement("UPDATE uygulama_ayarlari SET destek_epostasi = 'destek@example.com' WHERE app = 'keepchat'");
        $this->put('/panel/api/uygulamalar/keepchat/yasal/veriler', self::VERILER, $jeton);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/yasal/veriler', $this->client->getResponse());
        self::assertSame([], $this->belge($this->json(), 'sartlar', 'tr')['eksikler']);

        $metin = "Giriş {{uygulama}} — {{unvan}}.\nikinci satır <script>alert(1)</script>\n\n## Başlık **kalın**\n- bir **iki**\n- üç\n\n{{adres}} · {{kayitNo}} · {{eposta}}";
        $this->put('/panel/api/uygulamalar/keepchat/yasal/sartlar/tr', ['metin' => $metin], $jeton);
        self::assertResponseIsSuccessful();
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/yasal/{belge}/{dil}', $this->client->getResponse());
        self::assertTrue($this->belge($this->json(), 'sartlar', 'tr')['kayitli']);

        $this->client->request('GET', '/yasal/keepchat/sartlar/tr');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<html lang="tr">', $html);
        self::assertStringContainsString('<p>Giriş KeepChat — Örnek Yazılım A.Ş.. ikinci satır &lt;script&gt;alert(1)&lt;/script&gt;</p>', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('<h2>Başlık <strong>kalın</strong></h2>', $html);
        self::assertStringContainsString('<ul><li>bir <strong>iki</strong></li><li>üç</li></ul>', $html);
        self::assertStringContainsString('<p>Kadıköy, İstanbul · MERSİS 0123 · destek@example.com</p>', $html);
        self::assertStringContainsString('Son güncelleme: '.date('Y-m-d'), $html);

        // Öteki dil kaydedilmedi → yayında değil.
        $this->client->request('GET', '/yasal/keepchat/sartlar/en');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAyarDegisinceSayfaYenidenKaydedilmedenGuncellenirBosDegerTire(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        $this->put('/panel/api/uygulamalar/keepchat/yasal/gizlilik/en', ['metin' => '{{unvan}} / {{kayitNo}}'], $jeton);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/yasal/keepchat/gizlilik/en');
        self::assertStringContainsString('<p>— / —</p>', (string) $this->client->getResponse()->getContent());

        $this->db->executeStatement("UPDATE uygulama_ayarlari SET unvan = 'Yeni Unvan' WHERE app = 'keepchat'");
        $this->client->request('GET', '/yasal/keepchat/gizlilik/en');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<p>Yeni Unvan / —</p>', $html);
        self::assertStringContainsString('Last updated:', $html);
    }

    /** @return iterable<string, array{mixed}> */
    public static function gecersizMetinler(): iterable
    {
        yield 'bilinmeyen yer tutucu' => [['metin' => 'Merhaba {{sirket}}']];
        yield 'boş' => [['metin' => "  \n "]];
        yield 'çok uzun' => [['metin' => str_repeat('a', 50001)]];
        yield 'fazla alan' => [['metin' => 'x', 'dil' => 'tr']];
        yield 'metin değil' => [['metin' => 5]];
    }

    #[DataProvider('gecersizMetinler')]
    public function testGecersizMetinReddedilirYayinaCikmaz(mixed $govde): void
    {
        $this->girisYap();
        $this->put('/panel/api/uygulamalar/keepchat/yasal/sartlar/tr', $govde, $this->jeton());
        self::assertResponseStatusCodeSame(422);
        Sozlesme::yanitiDogrula('PUT', '/panel/api/uygulamalar/{app}/yasal/{belge}/{dil}', $this->client->getResponse());
        $this->client->request('GET', '/yasal/keepchat/sartlar/tr');
        self::assertResponseStatusCodeSame(404);
    }

    public function testBilinmeyenBelgeDilVeJetonsuz(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        $this->put('/panel/api/uygulamalar/keepchat/yasal/iade/tr', ['metin' => 'x'], $jeton);
        self::assertResponseStatusCodeSame(404);
        $this->put('/panel/api/uygulamalar/keepchat/yasal/sartlar/de', ['metin' => 'x'], $jeton);
        self::assertResponseStatusCodeSame(404);
        $this->put('/panel/api/uygulamalar/bilinmeyen/yasal/sartlar/tr', ['metin' => 'x'], $jeton);
        self::assertResponseStatusCodeSame(404);
        $this->put('/panel/api/uygulamalar/keepchat/yasal/sartlar/tr', ['metin' => 'x'], '');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/yasal/keepchat/sartlar/tr');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/yasal/bilinmeyen/sartlar/tr');
        self::assertResponseStatusCodeSame(404);
    }

    public function testVerilerDogrulanirAyarSatiriYoksaKaydedilmez(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        $this->put('/panel/api/uygulamalar/keepchat/yasal/veriler', ['unvan' => str_repeat('a', 201)] + self::VERILER, $jeton);
        self::assertResponseStatusCodeSame(422);
        $this->put('/panel/api/uygulamalar/keepchat/yasal/veriler', ['fazla' => 'x'] + self::VERILER, $jeton);
        self::assertResponseStatusCodeSame(422);

        $this->db->executeStatement('DELETE FROM uygulama_ayarlari');
        $this->put('/panel/api/uygulamalar/keepchat/yasal/veriler', self::VERILER, $jeton);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['hatalar' => ['önce Ayarlar sayfasında uygulamanın kimliğini kaydedin']], $this->json());
    }

    /** Her taslak olduğu gibi kaydedilebilir (bilinmeyen yer tutucu yok) ve çizilir. */
    public function testTaslaklarGecerli(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        foreach (Taslaklar::BELGELER as $belge) {
            foreach (Taslaklar::DILLER as $dil) {
                $this->put("/panel/api/uygulamalar/keepchat/yasal/{$belge}/{$dil}", ['metin' => Taslaklar::metin($belge, $dil)], $jeton);
                self::assertResponseIsSuccessful("{$belge}/{$dil}");
                $this->client->request('GET', "/yasal/keepchat/{$belge}/{$dil}");
                self::assertResponseIsSuccessful("{$belge}/{$dil}");
                self::assertStringNotContainsString('{{', (string) $this->client->getResponse()->getContent());
            }
        }
    }

    /** §4: belge listesi DB kısıtıyla da sınırlı. */
    public function testDbKisitiBilinmeyenBelgeyiReddeder(): void
    {
        $this->expectException(DriverException::class);
        $this->db->executeStatement("INSERT INTO yasal_metinler (app, belge, dil, metin) VALUES ('keepchat', 'iade', 'tr', 'x')");
    }

    /** §51 */
    public function testSayfaAdresiAcilir(): void
    {
        $this->girisYap();
        $this->client->request('GET', '/panel/yasal');
        self::assertResponseIsSuccessful();
    }

    private function gocHali(): void
    {
        $this->db->executeStatement("TRUNCATE yasal_metinler; DELETE FROM uygulama_ayarlari; INSERT INTO uygulama_ayarlari (app, ad, sema) VALUES ('keepchat', 'KeepChat', 'keepchat')");
    }

    /**
     * @param array<string, mixed> $g
     *
     * @return array<string, mixed>
     */
    private function belge(array $g, string $belge, string $dil): array
    {
        self::assertIsArray($g['belgeler']);
        $bulunan = array_values(array_filter($g['belgeler'], static fn (mixed $b): bool => \is_array($b) && $b['belge'] === $belge && $b['dil'] === $dil));
        self::assertCount(1, $bulunan, "belge yok: {$belge}/{$dil}");
        /** @var array<string, mixed> $b */
        $b = $bulunan[0];

        return $b;
    }

    private function put(string $adres, mixed $govde, string $jeton): void
    {
        $this->client->request('PUT', $adres, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $jeton], (string) json_encode($govde));
    }

    private function jeton(): string
    {
        $this->client->request('GET', '/panel/yasal');
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

    /** @return array<string, mixed> */
    private function json(): array
    {
        /** @var array<string, mixed> $v */
        $v = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $v;
    }
}
