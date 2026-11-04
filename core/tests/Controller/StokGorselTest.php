<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Arac\AracKapsami;
use App\Huni\Arac\HuniAraclari;
use App\Huni\Huniler;
use App\Medya\Medyalar;
use App\Medya\StokGorseller;
use App\Panel\YoneticiSaglayici;
use App\Tests\Support\SahtePexels;
use App\Tests\Support\SahteRevenueCat;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * V3b: stok fotoğraf (Pexels) — arama anahtarla gider; içe alma görsel
 * bilgisini Pexels'ten okur, yalnız Pexels'in görsel sunucusundan indirir,
 * yüklenen gibi yeniden kodlar, kaynak/fotoğrafçı/lisansı görselle saklar.
 */
final class StokGorselTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $this->db = $db;
        $this->db->executeStatement('TRUNCATE medya, huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari');
        SahtePexels::sifirla();
        SahteRevenueCat::sifirla();
    }

    public function testAramaAnahtarlaGiderSonuclarSadelesir(): void
    {
        $this->girisYap();

        $this->client->request('GET', '/panel/medya/keepchat/stok', ['q' => 'sleeping woman', 'yon' => 'portrait']);

        self::assertResponseIsSuccessful();
        $j = $this->json();
        self::assertSame(['ad' => 'Pexels', 'adres' => 'https://www.pexels.com'], $j['kaynak']);
        self::assertSame([
            'kimlik' => 101, 'kucuk' => 'https://images.pexels.com/photos/101/k.jpeg?h=350', 'genislik' => 4000, 'yukseklik' => 6000,
            'yazar' => 'Ayşe Fotoğrafçı', 'sayfa' => 'https://www.pexels.com/photo/uyuyan-kadin-101/', 'aciklama' => 'Yatakta uyuyan kadın',
        ], \is_array($j['sonuclar']) ? $j['sonuclar'][0] : null);
        self::assertCount(1, SahtePexels::$istekler);
        $istek = SahtePexels::$istekler[0];
        self::assertStringStartsWith('https://api.pexels.com/v1/search?', $istek['url']);
        parse_str((string) parse_url($istek['url'], \PHP_URL_QUERY), $sorgu);
        self::assertSame(['query' => 'sleeping woman', 'per_page' => '15', 'locale' => 'tr-TR', 'orientation' => 'portrait'], $sorgu);
        self::assertSame(['Authorization: pexels-test-sahte'], $istek['headers']);
    }

    public function testGecersizAramaPexelseGitmez(): void
    {
        $this->girisYap();
        foreach ([['q' => ''], ['q' => str_repeat('a', 101)], ['q' => 'x', 'yon' => 'yan']] as $sorgu) {
            $this->client->request('GET', '/panel/medya/keepchat/stok', $sorgu);
            self::assertResponseStatusCodeSame(422, (string) json_encode($sorgu));
        }
        self::assertSame([], SahtePexels::$istekler);
    }

    public function testIceAlmaYenidenKodlarKaynakVeLisansSaklar(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();

        $this->client->request('POST', '/panel/medya/keepchat/stok/101', [], [], ['HTTP_X_CSRF_TOKEN' => $jeton]);

        self::assertResponseStatusCodeSame(201);
        $id = $this->json()['id'] ?? null;
        self::assertIsString($id);
        // Bilgi Pexels'ten (istemciden değil), görsel Pexels'in görsel sunucusundan.
        self::assertSame(['https://api.pexels.com/v1/photos/101', 'https://images.pexels.com/photos/101/b.jpeg?w=940&dpr=2'], array_column(SahtePexels::$istekler, 'url'));
        // Yeniden kodlandı: 1130×1700 → en uzun kenar 1600 (1130·1600/1700 = 1063,5 → 1064).
        self::assertEquals([1064, 1600], $this->db->fetchNumeric('SELECT genislik, yukseklik FROM medya WHERE id = :id', ['id' => $id]));
        $this->client->request('GET', '/panel/medya/keepchat');
        $liste = $this->json()['gorseller'] ?? null;
        self::assertIsArray($liste);
        self::assertSame([
            'ad' => 'pexels', 'adres' => 'https://www.pexels.com/photo/uyuyan-kadin-101/', 'yazar' => 'Ayşe Fotoğrafçı',
            'yazarAdresi' => 'https://www.pexels.com/@ayse', 'lisans' => 'Pexels Lisansı', 'lisansAdresi' => 'https://www.pexels.com/license/',
        ], \is_array($liste[0]) ? $liste[0]['kaynak'] : null);
    }

    public function testYabanciAdresliGorselIndirilmez(): void
    {
        $this->girisYap();
        $jeton = $this->jeton();
        SahtePexels::$fotoEki = ['src' => ['large2x' => 'https://kotu.example/x.jpeg']];

        $this->client->request('POST', '/panel/medya/keepchat/stok/101', [], [], ['HTTP_X_CSRF_TOKEN' => $jeton]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['https://api.pexels.com/v1/photos/101'], array_column(SahtePexels::$istekler, 'url'));
        // Yabancı adrese hiç istek gitmedi (kırma T1: yalnız "kayıt yok" yetmiyordu — indirilen bozuk yanıt da reddediliyordu).
        self::assertSame(0, SahteRevenueCat::sayi());
        self::assertSame(0, $this->sayim());
    }

    public function testJetonsuzIceAlmaYokPexelsHatasiMesajli(): void
    {
        $this->girisYap();
        $this->client->request('POST', '/panel/medya/keepchat/stok/101');
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], SahtePexels::$istekler);

        SahtePexels::$durum = 500;
        $this->client->request('POST', '/panel/medya/keepchat/stok/101', [], [], ['HTTP_X_CSRF_TOKEN' => $this->jeton()]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Pexels görseli bulunamadı ya da bilgisi eksik'], $this->json()['hatalar'] ?? null);
        self::assertSame(0, $this->sayim());
    }

    public function testGirissizErisimYok(): void
    {
        $this->client->request('GET', '/panel/medya/keepchat/stok', ['q' => 'x']);
        self::assertResponseRedirects();
        self::assertSame([], SahtePexels::$istekler);
    }

    public function testKaynakliGorselLisanssizOlamaz(): void
    {
        // Tuzak §4: kayıt yolu dışında da kısıt bekçi.
        $this->expectException(DriverException::class);
        $this->db->insert('medya', ['app' => 'keepchat', 'id' => str_repeat('a', 64), 'genislik' => 1, 'yukseklik' => 1, 'bayt' => 1, 'ozgun_ad' => 'x', 'kaynak' => 'pexels', 'kaynak_adresi' => 'https://www.pexels.com/photo/1/']);
    }

    public function testAnahtarsizAracYokPanel503(): void
    {
        $c = static::getContainer();
        $medyalar = $c->get(Medyalar::class);
        $huniler = $c->get(Huniler::class);
        $ekran = $c->get(\App\Huni\Ekran\EkranCekici::class);
        self::assertInstanceOf(Medyalar::class, $medyalar);
        self::assertInstanceOf(Huniler::class, $huniler);
        self::assertInstanceOf(\App\Huni\Ekran\EkranCekici::class, $ekran);
        $kapali = new StokGorseller(new MockHttpClient(), $medyalar, $this->db, new NullLogger(), '');
        self::assertFalse($kapali->acik());
        self::assertSame(['stok fotoğraf kapalı (PEXELS_API_KEY yok)'], $kapali->ara('x', null));
        $acik = $c->get(HuniAraclari::class);
        self::assertInstanceOf(HuniAraclari::class, $acik);
        self::assertContains('stok_gorsel_ara', array_column($acik->tanimlar(new AracKapsami('keepchat')), 'name'));
        $sablonlar = $c->get(\App\Huni\Sablonlar::class);
        self::assertInstanceOf(\App\Huni\Sablonlar::class, $sablonlar);
        $kapaliAraclar = new HuniAraclari($huniler, \dirname(__DIR__, 2).'/config/huni/tanim-semasi.json', 'https://huni.test', $ekran, $kapali, $sablonlar);
        self::assertNotContains('stok_gorsel_ara', array_column($kapaliAraclar->tanimlar(new AracKapsami('keepchat')), 'name'));
    }

    public function testAiAraciIceAlirGorselKimligiDoner(): void
    {
        $araclar = static::getContainer()->get(HuniAraclari::class);
        self::assertInstanceOf(HuniAraclari::class, $araclar);

        $s = $araclar->calistir('stok_gorsel_al', ['kimlik' => 102], new AracKapsami('keepchat'));

        self::assertFalse($s->hata);
        self::assertSame('Pexels Lisansı', $s->icerik['lisans']);
        self::assertSame(1, $this->sayim());
        self::assertTrue($araclar->calistir('stok_gorsel_al', ['kimlik' => '102'], new AracKapsami('keepchat'))->hata, 'kimlik tam sayı');
    }

    private function jeton(): string
    {
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $huniler->yeniHuni('keepchat', 'genel', null);
        $this->client->request('GET', '/panel/api/uygulamalar/keepchat/huniler/genel/duzenleyici');
        $j = $this->json();

        return \is_string($j['jeton'] ?? null) ? $j['jeton'] : '';
    }

    /** @return array<mixed> */
    private function json(): array
    {
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);

        return \is_array($j) ? $j : [];
    }

    private function sayim(): int
    {
        $n = $this->db->fetchOne('SELECT count(*) FROM medya');

        return is_numeric($n) ? (int) $n : -1;
    }

    private function girisYap(): void
    {
        $saglayici = static::getContainer()->get(YoneticiSaglayici::class);
        self::assertInstanceOf(YoneticiSaglayici::class, $saglayici);
        $this->client->loginUser($saglayici->loadUserByIdentifier('yonetici'), 'panel');
    }
}
