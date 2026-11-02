<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\Sozlesme;
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

    /** P2: liste JSON ucundan; panel sayfası React kabuğu. */
    public function testListeYayinVeYayinlanmamisDegisikligiVerir(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Bir'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('İki'));
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'taslak', 'genel'));
        $this->girisYap();

        $this->client->request('GET', '/panel/huniler');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->client->getCrawler()->filter('#panel-uygulama'));

        [$durum, $liste] = $this->api('GET', '/panel/api/uygulamalar/keepchat/huniler');
        self::assertSame(200, $durum);
        self::assertIsArray($liste);
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/huniler', $this->client->getResponse());
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSame('https://huni.test/keepchat/', $liste['adres']);
        self::assertSame(Huniler::SECIM_PARAMETRELERI, $liste['parametreler']);
        $huniler = $liste['huniler'];
        self::assertIsArray($huniler);
        self::assertSame(['genel', 'taslak'], array_column($huniler, 'ad'));
        [$genel, $taslak] = $huniler;
        self::assertIsArray($genel);
        self::assertIsArray($taslak);
        self::assertSame([true, 1, true, false, '/panel/huniler/keepchat/genel'], [$genel['varsayilan'], $genel['yayinda'], $genel['farkli'], $genel['ziyaretli'], $genel['duzenle']]);
        self::assertSame([false, null], [$taslak['varsayilan'], $taslak['yayinda']]);
        // Ç70: "Aç" süreli önizleme — yayındaki sürüm ya da taslak, ziyaret sayılmaz.
        foreach ([[$genel, 'genel', '&yayin=1'], [$taslak, 'taslak', '']] as [$h, $ad, $ek]) {
            $adres = \is_string($h['ac']) ? $h['ac'] : '';
            self::assertStringStartsWith("https://huni.test/keepchat/?huni={$ad}{$ek}&onizleme=", $adres);
            self::assertTrue($this->huniler->anahtarGecerli('keepchat', $ad, substr($adres, (int) strrpos($adres, '=') + 1), time()));
        }
    }

    public function testApiYeniHuniKuralTasiVeVarsayilan(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Genel'));
        $this->girisYap();
        $jeton = $this->kabukJetonu();

        [$durum, $yeni] = $this->api('POST', '/panel/api/uygulamalar/keepchat/huniler', $jeton, ['ad' => 'fitness', 'kaynak' => 'genel']);
        self::assertSame(201, $durum);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/huniler', $this->client->getResponse());
        self::assertSame(['ad' => 'fitness', 'duzenle' => '/panel/huniler/keepchat/fitness'], $yeni);
        self::assertEquals(self::tanim('Genel'), $this->huniler->taslak('keepchat', 'fitness'));

        self::assertSame(204, $this->api('POST', '/panel/api/uygulamalar/keepchat/kurallar', $jeton, ['parametre' => 'k', 'deger' => 'Fit', 'huni' => 'fitness'])[0]);
        self::assertSame(204, $this->api('POST', '/panel/api/uygulamalar/keepchat/kurallar', $jeton, ['parametre' => 'k', 'deger' => 'iki', 'huni' => 'genel'])[0]);
        self::assertSame([['k', 'fit'], ['k', 'iki']], array_map(static fn (array $k): array => [$k['parametre'], $k['deger']], $this->huniler->kurallar('keepchat')));
        [$ilk, $ikinci] = $this->huniler->kurallar('keepchat');
        self::assertSame(204, $this->api('POST', "/panel/api/uygulamalar/keepchat/kurallar/{$ikinci['id']}/tasi", $jeton, ['yon' => 'yukari'])[0]);
        self::assertSame(['iki', 'fit'], array_column($this->huniler->kurallar('keepchat'), 'deger'));
        self::assertSame(204, $this->api('DELETE', "/panel/api/uygulamalar/keepchat/kurallar/{$ilk['id']}", $jeton)[0]);
        self::assertSame(['iki'], array_column($this->huniler->kurallar('keepchat'), 'deger'));
        self::assertSame(404, $this->api('DELETE', "/panel/api/uygulamalar/keepchat/kurallar/{$ilk['id']}", $jeton)[0]);

        self::assertSame(204, $this->api('POST', '/panel/api/uygulamalar/keepchat/huniler/fitness/varsayilan', $jeton)[0]);
        self::assertSame(['fitness'], array_column(array_filter($this->huniler->huniler('keepchat'), static fn (array $h): bool => $h['varsayilan']), 'ad'));
        self::assertSame(404, $this->api('POST', '/panel/api/uygulamalar/keepchat/huniler/olmayan/varsayilan', $jeton)[0]);
    }

    public function testApiHatalari422VeBilinmeyenUygulama404(): void
    {
        $this->girisYap();
        $jeton = $this->kabukJetonu();

        [$durum, $govde] = $this->api('POST', '/panel/api/uygulamalar/keepchat/huniler', $jeton, ['ad' => 'genel']);
        self::assertSame(422, $durum);
        Sozlesme::yanitiDogrula('POST', '/panel/api/uygulamalar/{app}/huniler', $this->client->getResponse());
        self::assertIsArray($govde);
        self::assertStringContainsString('zaten var', implode(' ', \is_array($govde['hatalar'] ?? null) ? array_map(static fn (mixed $h): string => \is_string($h) ? $h : '', $govde['hatalar']) : []));
        self::assertSame(422, $this->api('POST', '/panel/api/uygulamalar/keepchat/kurallar', $jeton, ['parametre' => 'renk', 'deger' => 'x', 'huni' => 'genel'])[0]);
        self::assertSame(422, $this->api('POST', '/panel/api/uygulamalar/keepchat/huniler', $jeton, ['ad' => 5])[0]);
        foreach (['/panel/api/uygulamalar/olmayan/huniler', '/panel/api/uygulamalar/olmayan/kurallar'] as $adres) {
            self::assertSame(404, $this->api('POST', $adres, $jeton, ['ad' => 'x', 'parametre' => 'k', 'deger' => 'x', 'huni' => 'x'])[0]);
        }
        self::assertSame(404, $this->api('GET', '/panel/api/uygulamalar/olmayan/huniler')[0]);
    }

    /** Ç41: ziyaretsiz huni silinir, ziyaretli arşivlenir, varsayılan kaldırılamaz. */
    public function testApiHuniSilinirYaDaArsivlenir(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Genel'));
        $this->huniler->yeniHuni('keepchat', 'bos', 'genel');
        $this->huniler->yeniHuni('keepchat', 'eski', 'genel');
        $this->huniler->yayinla('keepchat', 'eski');
        $this->db->executeStatement("INSERT INTO huni_ziyaretleri (app, huni, surum) VALUES ('keepchat', 'eski', 1)");
        $this->girisYap();
        $jeton = $this->kabukJetonu();

        self::assertSame([200, ['sonuc' => 'silindi']], $this->api('DELETE', '/panel/api/uygulamalar/keepchat/huniler/bos', $jeton));
        Sozlesme::yanitiDogrula('DELETE', '/panel/api/uygulamalar/{app}/huniler/{ad}', $this->client->getResponse());
        self::assertSame([200, ['sonuc' => 'arsivlendi']], $this->api('DELETE', '/panel/api/uygulamalar/keepchat/huniler/eski', $jeton));
        self::assertSame(422, $this->api('DELETE', '/panel/api/uygulamalar/keepchat/huniler/genel', $jeton)[0]);
        self::assertSame(['genel'], array_column($this->huniler->huniler('keepchat'), 'ad'));
    }

    /** Her yazma ucu kabuğun jetonunu ister; eski form uçları yok. */
    public function testApiYazmaCsrfIsterEskiFormlarYok(): void
    {
        $this->huniler->yeniHuni('keepchat', 'bos', null);
        $this->girisYap();

        foreach ([
            ['POST', '/panel/api/uygulamalar/keepchat/huniler', ['ad' => 'yeni']],
            ['DELETE', '/panel/api/uygulamalar/keepchat/huniler/bos', []],
            ['POST', '/panel/api/uygulamalar/keepchat/huniler/bos/varsayilan', []],
            ['POST', '/panel/api/uygulamalar/keepchat/kurallar', ['parametre' => 'k', 'deger' => 'x', 'huni' => 'bos']],
        ] as [$yontem, $adres, $govde]) {
            foreach (['', 'yanlis', $this->editorJetonu()] as $jeton) {
                self::assertSame(403, $this->api($yontem, $adres, $jeton, $govde)[0], "{$yontem} {$adres}");
            }
        }
        self::assertSame(['genel', 'bos'], array_column($this->huniler->huniler('keepchat'), 'ad'));
        self::assertSame([], $this->huniler->kurallar('keepchat'));
        foreach (['/panel/huniler/keepchat/yeni', '/panel/huniler/keepchat/kurallar', '/panel/huniler/keepchat/bos/kaldir', '/panel/huniler/keepchat/bos/varsayilan'] as $eski) {
            $this->client->request('POST', $eski, ['_token' => 'x']);
            self::assertContains($this->client->getResponse()->getStatusCode(), [404, 405], $eski);
        }
    }

    /** P3: düzenleyici panel uygulamasının sayfası; veri açılış ucundan (JSON, kaçışsız gömme yok). */
    public function testDuzenleyiciSayfasiKabukVeAcilisUcu(): void
    {
        $manifest = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/public/varliklar/panel/.vite/manifest.json'), true);
        self::assertIsArray($manifest);
        self::assertArrayNotHasKey('src/main.tsx', $manifest, 'ayrı düzenleyici girişi kalktı');
        $giris = $manifest['src/uygulama/main.tsx'] ?? null;
        self::assertIsArray($giris);
        $kabuk = $giris['file'] ?? null;
        self::assertIsString($kabuk);
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('"><script>alert(1)</script>'));
        foreach (['/panel/huniler/keepchat/genel', '/panel/api/uygulamalar/keepchat/huniler/genel/duzenleyici'] as $adres) {
            $this->client->request('GET', $adres);
            self::assertResponseRedirects('/panel/giris', null, $adres);
        }
        $this->girisYap();

        $crawler = $this->client->request('GET', '/panel/huniler/keepchat/genel');
        self::assertResponseStatusCodeSame(200);
        self::assertSame('/varliklar/panel/'.$kabuk, $crawler->filter('script[type=module]')->attr('src'));
        self::assertCount(1, $crawler->filter('#panel-uygulama'));
        self::assertStringNotContainsString('alert(1)', (string) $this->client->getResponse()->getContent());
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');

        [$durum, $acilis] = $this->api('GET', '/panel/api/uygulamalar/keepchat/huniler/genel/duzenleyici');
        self::assertSame(200, $durum);
        Sozlesme::yanitiDogrula('GET', '/panel/api/uygulamalar/{app}/huniler/{ad}/duzenleyici', $this->client->getResponse());
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertIsArray($acilis);
        self::assertEquals(self::tanim('"><script>alert(1)</script>'), $acilis['taslak']);
        self::assertSame($this->huniler->taslakImzasi('keepchat', 'genel'), $acilis['imza']);
        self::assertSame('keepchat · genel', $acilis['baslik']);
        self::assertSame([
            'kaydet' => '/panel/huniler/keepchat/genel/taslak',
            'durum' => '/panel/huniler/keepchat/genel/durum',
            'medya' => '/panel/medya/keepchat',
            'aiDurum' => '/panel/api/ai/durum',
            'aiSohbet' => '/panel/api/ai/huniler/keepchat/genel/sohbet',
        ], $acilis['adresler']);
        // Açılıştaki jeton düzenleyici uçlarında geçer (kayıt 200).
        $this->kaydet(self::tanim('Yeni'), \is_string($acilis['jeton']) ? $acilis['jeton'] : '');
        self::assertResponseStatusCodeSame(200);

        foreach (['/panel/huniler/keepchat/olmayan', '/panel/api/uygulamalar/keepchat/huniler/olmayan/duzenleyici', '/panel/api/uygulamalar/olmayan/huniler/genel/duzenleyici'] as $adres) {
            $this->client->request('GET', $adres);
            self::assertResponseStatusCodeSame(404, $adres);
        }
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
        self::assertCount(0, $crawler->filter('#puck-duzenleyici'));
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

    /** Panel kabuğunun jetonu (panel-api). */
    private function kabukJetonu(): string
    {
        $this->client->request('GET', '/panel/huniler');

        return (string) $this->client->getCrawler()->filter('#panel-uygulama')->attr('data-jeton');
    }

    /** Düzenleyicinin jetonu (huni-keepchat) — panel API'sinde geçmemeli. */
    private function editorJetonu(): string
    {
        return $this->jeton();
    }

    /**
     * @param array<string, mixed> $govde
     *
     * @return array{int, array<string, mixed>|null}
     */
    private function api(string $yontem, string $adres, string $jeton = '', array $govde = []): array
    {
        $this->client->request($yontem, $adres, server: ['HTTP_X_CSRF_TOKEN' => $jeton, 'CONTENT_TYPE' => 'application/json'], content: [] === $govde ? null : (string) json_encode($govde));
        $yanit = $this->client->getResponse();
        $icerik = (string) $yanit->getContent();

        $govde = '' === $icerik ? null : json_decode($icerik, true);
        if (!\is_array($govde)) {
            return [$yanit->getStatusCode(), null];
        }
        $sonuc = [];
        foreach ($govde as $ad => $deger) {
            $sonuc[(string) $ad] = $deger;
        }

        return [$yanit->getStatusCode(), $sonuc];
    }

    private function jeton(): string
    {
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/huniler/genel/duzenleyici');
        $acilis = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($acilis) && \is_string($acilis['jeton'] ?? null) ? $acilis['jeton'] : '';
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
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/huniler/genel/duzenleyici');
        $acilis = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($acilis) && \is_string($acilis['imza'] ?? null) ? $acilis['imza'] : '';
    }

    /** @return array{sayfaBasligi: string, adimlar: array<int, array<string, mixed>>} */
    private static function tanim(string $baslik): array
    {
        return [
            'sayfaBasligi' => $baslik,
            'adimlar' => [
                ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [['tur' => 'metin', 'metin' => 'Kısa']], 'dugme' => 'Başla'],
                ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '9,99 €', 'dugme' => 'Abone ol', 'plan' => 'monthly'],
            ],
        ];
    }
}
