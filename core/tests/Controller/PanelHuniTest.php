<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Panelde huni düzenleme (A7 / E1.2). */
final class PanelHuniTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;
    private Huniler $huniler;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));
    }

    public function testGirisYapmadanHuniEkraniVeKayitKapali(): void
    {
        $this->client->request('GET', '/panel/huniler/keepchat/genel');
        self::assertResponseRedirects('/panel/giris');

        $this->client->jsonRequest('POST', '/panel/huniler/keepchat/genel/taslak', ['tanim' => self::tanim('X')]);
        self::assertResponseRedirects('/panel/giris');
        self::assertEquals($this->huniler->bosTaslak('keepchat'), $this->huniler->taslak('keepchat', 'genel'));
    }

    public function testListeYayinVeYayinlanmamisDegisikligiGosterir(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Bir'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('İki'));
        $this->girisYap();

        $this->client->request('GET', '/panel/huniler');

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('[data-uygulama=keepchat] tr[data-huni=genel]', 'v1');
        self::assertSelectorTextContains('[data-uygulama=keepchat] tr[data-huni=genel]', 'varsayılan');
        self::assertSelectorTextContains('[data-uygulama=keepchat] tr[data-huni=genel]', 'yayınlanmamış değişiklik');
    }

    public function testDuzenleyiciTaslagiKacislanmisOlarakGomer(): void
    {
        // Metin HTML/özniteliği kıramaz: "> ve <script> öznitelikte kaçışlanır.
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('"><script>alert(1)</script>'));
        $this->girisYap();

        $this->client->request('GET', '/panel/huniler/keepchat/genel');
        $html = (string) $this->client->getResponse()->getContent();

        self::assertResponseStatusCodeSame(200);
        self::assertStringNotContainsString('<script>alert(1)', $html);
        $gomulu = $this->client->getCrawler()->filter('#duzenleyici')->attr('data-taslak');
        self::assertEquals(self::tanim('"><script>alert(1)</script>'), json_decode((string) $gomulu, true));
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    public function testDuzenleyiciAdresiIcerikOzetiTasir(): void
    {
        // Önbellekteki eski düzenleyici çalışmasın: adres içerikle değişir.
        $this->girisYap();

        $this->client->request('GET', '/panel/huniler/keepchat/genel');
        $src = (string) $this->client->getCrawler()->filter('script[src*=huni-duzenleyici]')->attr('src');

        $dosya = \dirname(__DIR__, 2).'/public/varliklar/huni-duzenleyici.js';
        self::assertSame('/varliklar/huni-duzenleyici.js?v='.substr((string) md5_file($dosya), 0, 10), $src);
    }

    public function testOnizlemeBaglantisiGecerliAnahtarTasir(): void
    {
        $this->girisYap();

        $this->client->request('GET', '/panel/huniler/keepchat/genel');
        $adres = (string) $this->client->getCrawler()->selectLink('Taslağı önizle ↗')->attr('href');

        self::assertStringStartsWith('https://huni.test/keepchat/?huni=genel&onizleme=', $adres);
        self::assertTrue($this->huniler->anahtarGecerli('keepchat', 'genel', substr($adres, (int) strrpos($adres, '=') + 1), time()));
    }

    public function testKaydetGecerliyiYazarGecersizdeHataDonerYazmaz(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();

        $this->kaydet(self::tanim('Yeni'), $jeton);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('Yeni', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);

        $bozuk = self::tanim('Bozuk');
        $bozuk['adimlar'] = array_reverse($bozuk['adimlar']);
        $this->kaydet($bozuk, $jeton);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('EN SONDA', (string) $this->client->getResponse()->getContent());
        self::assertSame('Yeni', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);
    }

    public function testKaydetCsrfJetonuIster(): void
    {
        $this->girisYap();

        foreach (['', 'yanlis'] as $jeton) {
            $this->kaydet(self::tanim('Sahte'), $jeton);
            self::assertResponseStatusCodeSame(403);
        }
        self::assertEquals($this->huniler->bosTaslak('keepchat'), $this->huniler->taslak('keepchat', 'genel'));
    }

    public function testYayinlaSurumAcarAyniTaslagiIkinciKezYayinlamaz(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('X'));
        $this->girisYap();

        $this->yayinlaTikla();
        self::assertSelectorTextContains('.bildirim.tamam', 'genel v1 yayında');

        $this->yayinlaTikla();
        self::assertSelectorTextContains('.bildirim.hata', 'yayınlanacak değişiklik yok');
        self::assertSame([1], $this->db->fetchFirstColumn('SELECT surum FROM huni_surumleri'));
    }

    public function testYayinlaVeGeriYukleCsrfIster(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('X'));
        $this->girisYap();

        $this->client->request('POST', '/panel/huniler/keepchat/genel/yayinla', ['_token' => 'yanlis']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.bildirim.hata', 'jetonu geçersiz');
        self::assertSame([], $this->db->fetchFirstColumn('SELECT surum FROM huni_surumleri'));
    }

    public function testPaneldenYeniHuniKuralVeVarsayilan(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Genel'));
        $this->girisYap();

        $liste = $this->client->request('GET', '/panel/huniler');
        $this->client->submit($liste->filter('[data-uygulama=keepchat] [data-form=yeni-huni]')->form(['ad' => 'fitness', 'kaynak' => 'genel']));
        self::assertResponseRedirects('/panel/huniler/keepchat/fitness');
        self::assertEquals(self::tanim('Genel'), $this->huniler->taslak('keepchat', 'fitness'));

        $liste = $this->client->request('GET', '/panel/huniler');
        $this->client->submit($liste->filter('[data-uygulama=keepchat] [data-form=kural]')->form(['parametre' => 'k', 'deger' => 'Fit', 'huni' => 'fitness']));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.bildirim.tamam', 'Kural eklendi');
        self::assertSelectorTextContains('[data-bolum=kurallar]', 'k=fit');

        $this->client->submit($this->client->getCrawler()->filter('tr[data-huni=fitness]')->selectButton('Varsayılan yap')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('tr[data-huni=fitness]', 'varsayılan');
    }

    public function testAyniAdlaIkinciHuniHataGosterir(): void
    {
        $this->girisYap();

        $liste = $this->client->request('GET', '/panel/huniler');
        $this->client->submit($liste->filter('[data-uygulama=keepchat] [data-form=yeni-huni]')->form(['ad' => 'genel']));
        self::assertResponseRedirects('/panel/huniler');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.bildirim.hata', 'zaten var');
    }

    public function testEskiSurumTaslagaGeriYuklenir(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Eski'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Yeni'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->girisYap();

        $crawler = $this->client->request('GET', '/panel/huniler/keepchat/genel');
        self::assertSame(['v2 yayında', 'v1'], $crawler->filter('[data-bolum=surumler] tbody tr td:first-child')->each(static fn ($td): string => trim($td->text())));
        $this->client->submit($crawler->filter('[data-bolum=surumler] tbody tr')->last()->selectButton('Taslağa geri yükle')->form());
        $this->client->followRedirect();

        self::assertSelectorTextContains('.bildirim.tamam', 'v1 taslağa alındı');
        self::assertSame('Eski', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);
        self::assertSame([2, 1], $this->db->fetchFirstColumn('SELECT surum FROM huni_surumleri ORDER BY surum DESC'));
    }

    public function testBilinmeyenUygulama404(): void
    {
        $this->girisYap();

        $this->client->request('GET', '/panel/huniler/olmayan');

        self::assertResponseStatusCodeSame(404);
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }

    private function jeton(): string
    {
        $this->client->request('GET', '/panel/huniler/keepchat/genel');

        return (string) $this->client->getCrawler()->filter('#duzenleyici')->attr('data-jeton');
    }

    /** @param array<string, mixed> $tanim */
    private function kaydet(array $tanim, string $jeton): void
    {
        $this->client->request('POST', '/panel/huniler/keepchat/genel/taslak', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $jeton,
        ], (string) json_encode(['tanim' => $tanim]));
    }

    private function yayinlaTikla(): void
    {
        $crawler = $this->client->request('GET', '/panel/huniler/keepchat/genel');
        $this->client->submit($crawler->filter('#yayinla')->form());
        $this->client->followRedirect();
    }

    /** @return array{sayfaBasligi: string, adimlar: array<int, array<string, mixed>>} */
    private static function tanim(string $baslik): array
    {
        return [
            'sayfaBasligi' => $baslik,
            'adimlar' => [
                ['id' => 'giris', 'tur' => 'tanitim', 'baslik' => 'Merhaba', 'metin' => 'Kısa', 'dugme' => 'Başla'],
                ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '9,99 €', 'dugme' => 'Abone ol', 'plan' => 'monthly'],
            ],
        ];
    }
}
