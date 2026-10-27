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
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari');
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
        self::assertSelectorTextSame('nav.yan a[aria-current=page]', 'Huniler');
    }

    public function testListedeAcYayindakiniYaDaTaslagiOlcumsuzAcar(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Bir'));
        $this->huniler->yayinla('keepchat', 'genel');
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'taslak', 'genel'));
        $this->girisYap();

        $liste = $this->client->request('GET', '/panel/huniler');

        // Ç70: yayındaki de imzalı önizlemeyle açılır — kontrol ziyaret sayılmaz.
        $yayin = $liste->filter('tr[data-huni=genel] a[data-ac]');
        self::assertSame('Aç', trim($yayin->text()));
        self::assertSame('_blank', $yayin->attr('target'));
        $adres = (string) $yayin->attr('href');
        self::assertStringStartsWith('https://huni.test/keepchat/?huni=genel&yayin=1&onizleme=', $adres);
        self::assertTrue($this->huniler->anahtarGecerli('keepchat', 'genel', substr($adres, (int) strrpos($adres, '=') + 1), time()));
        $onizleme = (string) $liste->filter('tr[data-huni=taslak] a[data-ac]')->attr('href');
        self::assertStringStartsWith('https://huni.test/keepchat/?huni=taslak&onizleme=', $onizleme);
        self::assertTrue($this->huniler->anahtarGecerli('keepchat', 'taslak', substr($onizleme, (int) strrpos($onizleme, '=') + 1), time()));
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
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

    /** Ç41: listede ziyaretsiz huniye "Sil", ziyaretliye "Arşivle"; varsayılanda yok. */
    public function testListedenHuniSilinirYaDaArsivlenir(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Genel'));
        $this->huniler->yeniHuni('keepchat', 'bos', 'genel');
        $this->huniler->yeniHuni('keepchat', 'eski', 'genel');
        $this->huniler->yayinla('keepchat', 'eski');
        $this->db->executeStatement("INSERT INTO huni_ziyaretleri (app, huni, surum) VALUES ('keepchat', 'eski', 1)");
        $this->girisYap();

        $liste = $this->client->request('GET', '/panel/huniler');
        self::assertCount(0, $liste->filter('tr[data-huni=genel] [data-form=kaldir]'));
        self::assertSame('Sil', trim($liste->filter('tr[data-huni=bos] [data-form=kaldir] button')->text()));
        self::assertSame('Arşivle', trim($liste->filter('tr[data-huni=eski] [data-form=kaldir] button')->text()));

        $this->client->request('POST', '/panel/huniler/keepchat/bos/kaldir', ['_token' => 'yanlis']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.bildirim.hata', 'jetonu geçersiz');
        self::assertNotNull($this->huniler->taslak('keepchat', 'bos'));

        $this->client->submit($this->client->getCrawler()->filter('tr[data-huni=bos] [data-form=kaldir]')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.bildirim.tamam', "'bos' silindi");
        $this->client->submit($this->client->getCrawler()->filter('tr[data-huni=eski] [data-form=kaldir]')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.bildirim.tamam', "'eski' arşivlendi");
        self::assertSame(['genel'], $this->client->getCrawler()->filter('tr[data-huni]')->each(static fn ($tr): string => (string) $tr->attr('data-huni')));
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

    public function testSurukleBirakSayfasiManifesttekiBetigiYukler(): void
    {
        // Derleme kontrolde testten önce (bin/kontrol editor-derle); adres içerik özetli.
        $manifest = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/public/varliklar/panel/.vite/manifest.json'), true);
        self::assertIsArray($manifest);
        self::assertIsArray($manifest['src/main.tsx'] ?? null);
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('"><script>alert(1)</script>'));
        $this->client->request('GET', '/panel/huniler/keepchat/genel');
        self::assertResponseRedirects('/panel/giris');
        $this->girisYap();

        $crawler = $this->client->request('GET', '/panel/huniler/keepchat/genel');

        self::assertResponseStatusCodeSame(200);
        $betik = $manifest['src/main.tsx']['file'] ?? null;
        self::assertIsString($betik);
        self::assertSame('/varliklar/panel/'.$betik, $crawler->filter('script[type=module]')->attr('src'));
        self::assertEquals(self::tanim('"><script>alert(1)</script>'), json_decode((string) $crawler->filter('#puck-duzenleyici')->attr('data-taslak'), true));
        self::assertStringNotContainsString('<script>alert(1)', (string) $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    /**
     * Ç39: iki sekme aynı taslağı açarsa ikinci kayıt birincisini sessizce
     * ezmez — açılıştaki imza artık eşleşmiyorsa 409, taslak değişmez.
     */
    public function testEskiImzaylaKayitCakismaVerirYazmaz(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        $ilkSekme = $this->imza();
        $ikinciSekme = $this->imza();
        self::assertSame($ilkSekme, $ikinciSekme);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $ilkSekme);

        $this->kaydet(self::tanim('Birinci'), $jeton, $ilkSekme);
        self::assertResponseStatusCodeSame(200);
        $yeni = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($yeni);
        self::assertSame($this->imza(), $yeni['imza'] ?? null);

        $this->kaydet(self::tanim('İkinci'), $jeton, $ikinciSekme);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(['hatalar' => [Huniler::CAKISMA]], json_decode((string) $this->client->getResponse()->getContent(), true));
        self::assertSame('Birinci', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);

        $this->kaydet(self::tanim('İmzasız'), $jeton, '');
        self::assertResponseStatusCodeSame(409);

        // Yenilenen sekme güncel imzayla kaydedebilir.
        $this->kaydet(self::tanim('İkinci'), $jeton, $yeni['imza']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('İkinci', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);
    }

    /** b2 (Ç53): klasik düzenleyici ve form uçları kalktı; Puck tek düzenleyici. */
    public function testKlasikDuzenleyiciVeFormUclariYok(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('X'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->girisYap();

        $crawler = $this->client->request('GET', '/panel/huniler/keepchat/genel');
        self::assertCount(0, $crawler->filter('script[src*=huni-duzenleyici]'));
        self::assertCount(1, $crawler->filter('#puck-duzenleyici'));
        self::assertFileDoesNotExist(\dirname(__DIR__, 2).'/public/varliklar/huni-duzenleyici.js');

        $jeton = $this->jeton();
        foreach (['/panel/huniler/keepchat/genel/yayinla', '/panel/huniler/keepchat/genel/surumler/1/taslaga'] as $eski) {
            $this->client->request('POST', $eski, ['_token' => $jeton]);
            self::assertResponseStatusCodeSame(404, $eski);
        }
        $this->client->request('GET', '/panel/huniler/keepchat/genel/surukle');
        self::assertResponseStatusCodeSame(404);
        self::assertSame([1], $this->db->fetchFirstColumn('SELECT surum FROM huni_surumleri'));
    }

    /** b1: sürükle-bırak üst çubuğu — durum, yayınla, geri yükle JSON ile. */
    public function testDurumSurumlerFarkVeTazeOnizlemeVerir(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Eski'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->client->request('GET', '/panel/huniler/keepchat/genel/durum');
        self::assertResponseRedirects('/panel/giris');
        $this->girisYap();

        $durum = $this->json('GET', '/panel/huniler/keepchat/genel/durum');
        self::assertFalse($durum['farkli']);
        self::assertIsArray($durum['surumler']);
        self::assertSame([1], array_column($durum['surumler'], 'surum'));
        self::assertSame('https://huni.test/keepchat/?huni=genel', $durum['yayindaki']);
        $onizleme = $durum['onizleme'];
        self::assertIsString($onizleme);
        self::assertTrue($this->huniler->anahtarGecerli('keepchat', 'genel', substr($onizleme, (int) strrpos($onizleme, '=') + 1), time()));
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');

        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Yeni'));
        self::assertTrue($this->json('GET', '/panel/huniler/keepchat/genel/durum')['farkli']);
    }

    public function testDurumdanYayinlaSurumAcarDegisiklikYoksa422(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('X'));
        $this->girisYap();
        $jeton = $this->jeton();

        self::assertSame(['surum' => 1], $this->json('POST', '/panel/huniler/keepchat/genel/durum/yayinla', $jeton));
        $this->json('POST', '/panel/huniler/keepchat/genel/durum/yayinla', $jeton);
        self::assertResponseStatusCodeSame(422);
        // PHPStan aynı çağrının sonucunu öncekiyle aynı sayıyor: gövde ayrıca okunur.
        $govde = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($govde);
        self::assertSame(['hatalar'], array_keys($govde));
        self::assertStringContainsString('yayınlanacak değişiklik yok', (string) json_encode($govde['hatalar'], \JSON_UNESCAPED_UNICODE));
        self::assertSame([1], $this->db->fetchFirstColumn('SELECT surum FROM huni_surumleri'));
    }

    public function testDurumdanGeriYukleTaslagiDoner(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Eski'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Yeni'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->girisYap();
        $jeton = $this->jeton();

        $yanit = $this->json('POST', '/panel/huniler/keepchat/genel/durum/surumler/1/taslaga', $jeton);
        self::assertIsArray($yanit['tanim']);
        self::assertSame('Eski', $yanit['tanim']['sayfaBasligi']);
        // Ç39: geri yüklenen taslakla açılan düzenleyici bu imzayla kaydedebilir.
        self::assertSame($this->imza(), $yanit['imza'] ?? null);
        self::assertSame('Eski', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);
        self::assertSame([2, 1], $this->db->fetchFirstColumn('SELECT surum FROM huni_surumleri ORDER BY surum DESC'), 'geri yükleme yayınlamaz');

        $this->json('POST', '/panel/huniler/keepchat/genel/durum/surumler/9/taslaga', $jeton);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDurumYayinlaVeGeriYukleCsrfIster(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('X'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Y'));
        $this->girisYap();

        foreach (['', 'yanlis'] as $jeton) {
            $this->json('POST', '/panel/huniler/keepchat/genel/durum/yayinla', $jeton);
            self::assertResponseStatusCodeSame(403);
            $this->json('POST', '/panel/huniler/keepchat/genel/durum/surumler/1/taslaga', $jeton);
            self::assertResponseStatusCodeSame(403);
        }
        self::assertSame([1], $this->db->fetchFirstColumn('SELECT surum FROM huni_surumleri'));
        self::assertSame('Y', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);
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

        return (string) $this->client->getCrawler()->filter('#puck-duzenleyici')->attr('data-jeton');
    }

    /** @return array<string, mixed> */
    private function json(string $yontem, string $adres, string $jeton = ''): array
    {
        $this->client->request($yontem, $adres, [], [], ['HTTP_X_CSRF_TOKEN' => $jeton]);
        $govde = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($govde);
        $sonuc = [];
        foreach ($govde as $ad => $deger) {
            $sonuc[(string) $ad] = $deger;
        }

        return $sonuc;
    }

    /**
     * @param array<string, mixed> $tanim
     * @param string|null          $imza  null → sayfanın şu anki imzası (Ç39)
     */
    private function kaydet(array $tanim, string $jeton, ?string $imza = null): void
    {
        $imza ??= $this->imza();
        $this->client->request('POST', '/panel/huniler/keepchat/genel/taslak', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $jeton,
        ], (string) json_encode(['tanim' => $tanim, 'imza' => $imza]));
    }

    /** Düzenleyicinin açılışta aldığı taslak imzası. */
    private function imza(): string
    {
        $this->client->request('GET', '/panel/huniler/keepchat/genel');

        return (string) $this->client->getCrawler()->filter('#puck-duzenleyici')->attr('data-imza');
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
