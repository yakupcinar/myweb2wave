<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Huni\Tanim;
use App\Huni\Tema;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use DoctrineMigrations\Version20261020180000;
use DoctrineMigrations\Version20261102120000;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** Huni panelden yönetilir (A7): taslak, değişmez sürümler, önizleme. */
final class HuniTest extends WebTestCase
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
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, webhook_teslimleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, medya, yasal_metinler');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));
    }

    /**
     * M9b2 (A23): yanıtta yalnız YAYINDAKİ (kaydedilmiş) yasal sayfalar, huninin
     * dilinde (tr); hiç yoksa alan yok. Önizleme de aynısını alır.
     */
    public function testYayindakiYasalSayfalarinAdresi(): void
    {
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Genel')));
        self::assertSame(1, $this->huniler->yayinla('keepchat', 'genel'));
        $this->client->request('GET', '/huni/keepchat');
        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('yasal', $this->jsonYanit(), 'yayında belge yokken alan yok');

        $this->db->executeStatement("INSERT INTO yasal_metinler (app, belge, dil, metin) VALUES
            ('keepchat', 'sartlar', 'tr', 'x'), ('keepchat', 'cerez', 'tr', 'x'), ('keepchat', 'gizlilik', 'en', 'x')");
        $this->client->request('GET', '/huni/keepchat');
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        self::assertSame([
            'sartlar' => 'http://localhost/yasal/keepchat/sartlar/tr',
            'cerez' => 'http://localhost/yasal/keepchat/cerez/tr',
        ], $this->jsonYanit()['yasal'] ?? null);

        $anahtar = $this->huniler->onizlemeAnahtari('keepchat', 'genel', time());
        $this->client->request('GET', '/huni/keepchat/taslak', ['huni' => 'genel', 'anahtar' => $anahtar]);
        self::assertResponseIsSuccessful();
        self::assertSame(['sartlar', 'cerez'], array_keys((array) ($this->jsonYanit()['yasal'] ?? [])));
    }

    /**
     * M9d2: İngilizce huni İngilizce yasal sayfalara bağlanır (yayında olanlara);
     * önizleme de. Desteklenmeyen dil kaydedilmez.
     */
    public function testIngilizceHuniIngilizceYasalSayfalar(): void
    {
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', ['dil' => 'en'] + self::tanim('Main')));
        self::assertSame(1, $this->huniler->yayinla('keepchat', 'genel'));
        $this->db->executeStatement("INSERT INTO yasal_metinler (app, belge, dil, metin) VALUES
            ('keepchat', 'sartlar', 'tr', 'x'), ('keepchat', 'gizlilik', 'en', 'x')");

        $this->client->request('GET', '/huni/keepchat');
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        $yanit = $this->jsonYanit();
        $tanim = $yanit['tanim'] ?? null;
        self::assertIsArray($tanim);
        self::assertSame('en', $tanim['dil'] ?? null);
        self::assertSame(['gizlilik' => 'http://localhost/yasal/keepchat/gizlilik/en'], $yanit['yasal'] ?? null);
        $anahtar = $this->huniler->onizlemeAnahtari('keepchat', 'genel', time());
        $this->client->request('GET', '/huni/keepchat/taslak', ['huni' => 'genel', 'anahtar' => $anahtar]);
        self::assertSame(['gizlilik'], array_keys((array) ($this->jsonYanit()['yasal'] ?? [])));

        self::assertSame(['dil tr ya da en olmalı'], $this->huniler->taslakKaydet('keepchat', 'genel', ['dil' => 'de'] + self::tanim('Main')));
    }

    /** Ç41: ziyaretsiz huni iz bırakmadan silinir (sürümleri ve kuralları da). */
    public function testZiyaretsizHuniSilinir(): void
    {
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Genel')));
        self::assertSame(1, $this->huniler->yayinla('keepchat', 'genel'));
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'deneme', 'genel'));
        self::assertSame(1, $this->huniler->yayinla('keepchat', 'deneme'));
        self::assertSame([], $this->huniler->kuralEkle('keepchat', 'k', 'x', 'deneme'));

        self::assertSame('silindi', $this->huniler->kaldir('keepchat', 'deneme'));

        self::assertNull($this->huniler->taslak('keepchat', 'deneme'));
        self::assertSame([], $this->db->fetchFirstColumn("SELECT surum FROM huni_surumleri WHERE ad = 'deneme'"));
        self::assertSame([], $this->huniler->kurallar('keepchat'));
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'deneme', null), 'ad yeniden kullanılabilir');
    }

    /** Ç41: ziyareti olan huni silinmez (rapor verisi) — arşivlenir, kurallardan çıkar, seçilmez. */
    public function testZiyaretliHuniArsivlenirSecilmezListelenmez(): void
    {
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Genel')));
        self::assertSame(1, $this->huniler->yayinla('keepchat', 'genel'));
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'eski', 'genel'));
        self::assertSame(1, $this->huniler->yayinla('keepchat', 'eski'));
        self::assertSame([], $this->huniler->kuralEkle('keepchat', 'k', 'x', 'eski'));
        $this->db->executeStatement("INSERT INTO huni_ziyaretleri (app, huni, surum) VALUES ('keepchat', 'eski', 1)");
        self::assertSame('eski', $this->huniler->sec('keepchat', ['huni' => 'eski']));

        self::assertSame('arsivlendi', $this->huniler->kaldir('keepchat', 'eski'));

        self::assertSame([1], $this->db->fetchFirstColumn("SELECT surum FROM huni_surumleri WHERE ad = 'eski'"), 'ziyaretin sürümü korunur');
        self::assertSame([], $this->huniler->kurallar('keepchat'));
        self::assertSame('genel', $this->huniler->sec('keepchat', ['huni' => 'eski', 'k' => 'x']));
        self::assertSame(['genel'], array_column($this->huniler->huniler('keepchat'), 'ad'));
        self::assertNull($this->huniler->taslak('keepchat', 'eski'));
        self::assertNotSame([], $this->huniler->kuralEkle('keepchat', 'k', 'y', 'eski'));
        self::assertFalse($this->huniler->varsayilanYap('keepchat', 'eski'));
        self::assertSame("'eski' yok ya da arşivde", $this->huniler->kaldir('keepchat', 'eski'));
    }

    public function testVarsayilanHuniKaldirilamaz(): void
    {
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Genel')));

        self::assertStringContainsString('varsayılan', $this->huniler->kaldir('keepchat', 'genel'));
        self::assertNotNull($this->huniler->taslak('keepchat', 'genel'));
    }

    /** Tuzak §4: uygulamadaki "varsayılan mı" kontrolü kısıtı görünmez kılar — kısıtı doğrudan ölç. */
    public function testVarsayilanHuniVeritabanindaArsivlenemez(): void
    {
        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $this->db->executeStatement("UPDATE huniler SET arsiv_at = now() WHERE app = 'keepchat' AND ad = 'genel'");
    }

    public function testYayinlananEnSonSurumOkunurEskisiDegismez(): void
    {
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Bir')));
        self::assertSame(1, $this->huniler->yayinla('keepchat', 'genel'));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('İki')));
        self::assertSame(2, $this->huniler->yayinla('keepchat', 'genel'));

        $this->client->request('GET', '/huni/keepchat');

        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        // assertEquals: JSONB nesne anahtarlarını yeniden sıralar; içerik aynı olmalı.
        self::assertEquals(['huni' => 'genel', 'surum' => 2, 'tanim' => self::tanim('İki')], $this->yanit(fiyatsiz: true));
        // M1c: fiyat ülkeye (IP) göre — yalnız ziyaretçinin tarayıcısı önbelleğe alır, CDN değil.
        self::assertResponseHeaderSame('Cache-Control', 'max-age=60, private');
        self::assertSame(
            ['Bir', 'İki'],
            $this->db->fetchFirstColumn("SELECT tanim->>'sayfaBasligi' FROM huni_surumleri WHERE app = 'keepchat' ORDER BY surum"),
        );
    }

    public function testTemaYayindaDonerSozlesmeyeUyar(): void
    {
        $tanim = self::tanim('Temali') + ['tema' => ['anaRenk' => '#16a34a', 'arkaPlan' => '#0b1220', 'metinRengi' => '#f8fafc', 'yaziTipi' => 'yuvarlak', 'kose' => 20]];
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        $yanit = $this->yanit();
        self::assertIsArray($yanit['tanim'] ?? null);
        self::assertEquals($tanim['tema'], $yanit['tanim']['tema'] ?? null);
    }

    public function testPaywallFaydaVeBaglantilarYayindaDonerSozlesmeyeUyar(): void
    {
        $tanim = self::ile(2, 'faydalar', ['Sınırsız sohbet']);
        $tanim['adimlar'][2]['gizlilik'] = 'https://ornek.com/gizlilik';
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        $yanit = $this->yanit();
        $adimlar = \is_array($yanit['tanim'] ?? null) ? ($yanit['tanim']['adimlar'] ?? null) : null;
        self::assertIsArray($adimlar);
        $paywall = $adimlar[2] ?? null;
        self::assertIsArray($paywall);
        self::assertSame(['Sınırsız sohbet'], $paywall['faydalar'] ?? null);
        self::assertSame('https://ornek.com/gizlilik', $paywall['gizlilik'] ?? null);
    }

    /** T1: ekran adımı = başlık + katalogdaki bloklardan liste; yedi blok türü, görseller medya denetimine girer. */
    public function testEkranAdimiGecerliYayindaSozlesmeyeUyar(): void
    {
        $g = str_repeat('c', 64);
        $tanim = self::tanim('X');
        array_splice($tanim['adimlar'], 1, 0, [self::ekran([
            ['tur' => 'gorsel', 'gorsel' => $g, 'gorselMetni' => 'Sohbet ekranı'],
            ['tur' => 'baslik', 'metin' => 'Neden KeepChat?'],
            ['tur' => 'metin', 'metin' => 'Sohbetlerin tek yerde.'],
            ['tur' => 'maddeler', 'maddeler' => ['Yedekleme', 'Arama']],
            ['tur' => 'istatistik', 'deger' => '%87', 'metin' => 'kullanıcı ilk hafta düzenli kullanıyor'],
            ['tur' => 'yorum', 'metin' => 'Çok iyi.', 'yazar' => 'Ayşe', 'unvan' => 'Öğretmen', 'puan' => 5],
            ['tur' => 'yorum', 'metin' => 'Kullanışlı.', 'yazar' => 'Can'],
            ['tur' => 'kutu', 'metin' => 'İstediğin zaman iptal edebilirsin.', 'simge' => '🔒'],
            ['tur' => 'kutu', 'metin' => 'Simgesiz not.'],
            ['tur' => 'baslik', 'metin' => 'İkinci başlık'],
        ], ['hedef' => 'paywall', 'ilerlemeGoster' => false])]);
        self::assertSame([], Tanim::hatalar($tanim, ['monthly']));
        self::assertContains($g, Tanim::gorseller($tanim));
        self::assertSame(['görsel cccccccc… bu uygulamada yok'], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->medyaEkle($g);
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
    }

    /**
     * T1c: yeni huninin boş taslağı yalnız güncel türleri kullanır — metinleri
     * doldurulunca geçerli (eski tür düzenleyicide yanlış bileşenle açılırdı;
     * kırma E2 ilk koşuda yeşildi, bu test yoktu).
     */
    public function testBosTaslakMetinleriDolduruluncaGecerli(): void
    {
        $t = $this->huniler->bosTaslak('keepchat');
        $t['sayfaBasligi'] = 'Yeni';
        $adimlar = $t['adimlar'];
        self::assertIsArray($adimlar);
        foreach ($adimlar as $i => $adim) {
            self::assertIsArray($adim);
            $adimlar[$i] = array_merge($adim, ['baslik' => 'Başlık']);
        }
        $t['adimlar'] = $adimlar;

        self::assertSame([], Tanim::hatalar($t, ['monthly']));
    }

    /**
     * T3a: kural tabanlı yönlendirme — özellik sözlükteki adlarla, cevap bu ya
     * da önceki adımdan, hedef yalnız ileri.
     *
     * @param array<mixed> $kurallar
     *
     * @return array<mixed>
     */
    private static function yonlendirmeli(array $kurallar): array
    {
        $t = self::tanim('X');
        $t['adimlar'][1]['yonlendirme'] = $kurallar;
        // Sonraki adımda da soru var: "cevap.ara" yalnız sıra yüzünden reddedilmeli.
        array_splice($t['adimlar'], 2, 0, [self::ekran([['tur' => 'soru', 'secenekler' => [['id' => 'x', 'metin' => 'X'], ['id' => 'y', 'metin' => 'Y']]]], ['id' => 'ara', 'baslik' => 'Ara', 'dugme' => null])]);

        return $t;
    }

    /**
     * T3b: koşullu gösterim — `ara` ekranı yalnız koşul tutunca gösterilir.
     *
     * @param array<mixed> $goster
     *
     * @return array<mixed>
     */
    private static function gosterli(array $goster, int $adim = 2): array
    {
        $t = self::tanim('X');
        array_splice($t['adimlar'], 2, 0, [self::ekran([['tur' => 'soru', 'secenekler' => [['id' => 'x', 'metin' => 'X'], ['id' => 'y', 'metin' => 'Y']]]], ['id' => 'ara', 'baslik' => 'Ara', 'dugme' => null])]);
        $t['adimlar'][$adim]['goster'] = $goster;

        return $t;
    }

    /**
     * T3c: koşullu değer — `ara` ekranının ve giriş ekranındaki metin bloğunun
     * alanları kurala bağlanır.
     *
     * @param array<mixed> $ekranda
     * @param array<mixed> $blokta
     * @param array<mixed> $soruda
     *
     * @return array<mixed>
     */
    private static function degerli(array $ekranda, array $blokta = [], array $soruda = []): array
    {
        $t = self::tanim('X');
        if ([] !== $blokta) {
            $t['adimlar'][0]['bloklar'] = [['tur' => 'metin', 'metin' => 'Kısa', 'degerler' => $blokta]];
        }
        if ([] !== $soruda) {
            $t['adimlar'][1]['bloklar'] = [['tur' => 'soru', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']], 'degerler' => $soruda]];
        }
        array_splice($t['adimlar'], 2, 0, [self::ekran([['tur' => 'soru', 'secenekler' => [['id' => 'x', 'metin' => 'X'], ['id' => 'y', 'metin' => 'Y']]]], ['id' => 'ara', 'baslik' => 'Ara', 'dugme' => null, 'degerler' => [] === $ekranda ? null : $ekranda])]);

        return $t;
    }

    public function testDegerlerGecerliYayindaSozlesmeyeUyar(): void
    {
        $t = self::degerli(
            [['kosullar' => [['ozellik' => 'cevap.soru1', 'islem' => 'icerir', 'deger' => 'a']], 'alan' => 'baslik', 'deger' => 'A için']],
            [['baglac' => 'herhangi', 'kosullar' => [['ozellik' => 'utm.source', 'islem' => 'esit', 'deger' => 'meta']], 'alan' => 'metin', 'deger' => 'Meta']],
        );
        self::assertSame([], Tanim::hatalar($t, ['monthly']));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $t));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
    }

    public function testGosterGecerliYayindaSozlesmeyeUyar(): void
    {
        $t = self::gosterli(['baglac' => 'herhangi', 'kosullar' => [
            ['ozellik' => 'cevap.soru1', 'islem' => 'icerir', 'deger' => 'a'],
            ['ozellik' => 'utm.source', 'islem' => 'esit', 'deger' => 'meta'],
        ]]);
        self::assertSame([], Tanim::hatalar($t, ['monthly']));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $t));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
    }

    public function testYonlendirmeGecerliYayindaSozlesmeyeUyar(): void
    {
        $t = self::yonlendirmeli([
            ['kosullar' => [['ozellik' => 'cevap.soru1', 'islem' => 'icerir', 'deger' => 'a']], 'hedef' => 'paywall'],
            ['baglac' => 'herhangi', 'kosullar' => [
                ['ozellik' => 'utm.source', 'islem' => 'esit', 'deger' => 'meta'],
                ['ozellik' => 'izin.reklam', 'islem' => 'var'],
                ['ozellik' => 'huni.surum', 'islem' => 'esit_degil', 'deger' => 2],
                ['ozellik' => 'cevap.soru1', 'islem' => 'icermez', 'deger' => 'b'],
                ['ozellik' => 'utm.campaign', 'islem' => 'yok'],
            ], 'hedef' => 'ara'],
        ]);
        self::assertSame([], Tanim::hatalar($t, ['monthly']));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $t));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
    }

    /** T1c: bloksuz ekran (başlık + düğme, ör. "Teşekkürler!") geçerli — boş metinli tanıtım böyle taşınır. */
    public function testBloksuzEkranGecerli(): void
    {
        $t = self::tanim('X');
        array_splice($t['adimlar'], 1, 0, [self::ekran([])]);
        self::assertSame([], Tanim::hatalar($t, ['monthly']));
    }

    /** T1c: ekranın kapağı — tanıtımın görseli ve üç düzeni (taşıma için). */
    public function testEkranKapagiGecerli(): void
    {
        $g = str_repeat('c', 64);
        foreach (['ust', 'kahraman', 'tamEkran'] as $duzen) {
            $t = self::tanim('X');
            array_splice($t['adimlar'], 1, 0, [self::ekran([['tur' => 'metin', 'metin' => 'x'], ['tur' => 'maddeler', 'maddeler' => ['a']], ['tur' => 'istatistik', 'deger' => '1', 'metin' => 'y'], ['tur' => 'baslik', 'metin' => 'z']], ['gorsel' => $g, 'gorselMetni' => 'Kapak', 'duzen' => $duzen])]);
            self::assertSame([], Tanim::hatalar($t, ['monthly']), $duzen);
            self::assertContains($g, Tanim::gorseller($t));
        }
    }

    /**
     * T1b: ekranda soru bloğu — tek seçimde düğme yok (seçenek geçirir, hedefi
     * seçenekte), çoklu/serbest yanıtta ekranın düğmesi gönderir.
     */
    public function testEkranSoruBloguGecerliYayindaSozlesmeyeUyar(): void
    {
        $g = str_repeat('d', 64);
        $sec = [['id' => 'aile', 'metin' => 'Ailemle', 'simge' => '👪', 'hedef' => 'paywall'], ['id' => 'is', 'metin' => 'İş', 'gorsel' => $g]];
        $tanim = self::tanim('X');
        array_splice($tanim['adimlar'], 1, 0, [
            self::ekran([['tur' => 'metin', 'metin' => 'Kimle?'], ['tur' => 'soru', 'secenekler' => $sec, 'duzen' => 'izgara'], ['tur' => 'kutu', 'metin' => 'Gizli kalır.']], ['id' => 'tek', 'dugme' => null]),
            self::ekran([['tur' => 'soru', 'secimTuru' => 'coklu', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']]]], ['id' => 'cok', 'hedef' => 'paywall']),
            self::ekran([['tur' => 'soru', 'secimTuru' => 'metin', 'yanitZorunlu' => true]], ['id' => 'yazi']),
            // E1: e-posta sorusu, izin kutusuyla.
            self::ekran([['tur' => 'soru', 'secimTuru' => 'eposta', 'yanitZorunlu' => true, 'izinMetni' => 'İpuçları ve teklifler gönderilebilir.']], ['id' => 'eposta']),
        ]);
        self::assertSame([], Tanim::hatalar($tanim, ['monthly']));
        // Seçenek görseli de bu uygulamaya yüklenmiş olmalı.
        self::assertContains($g, Tanim::gorseller($tanim));
        $this->medyaEkle($g);
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
    }

    /** Ç101: açık ana renk ya da temasız (varsayılan 3,19:1) tam ekran kapak geçer; kahramanda kural yok. */
    public function testTamEkranKapaktaAcikDugmeGecer(): void
    {
        $t = self::tanim('X');
        array_splice($t['adimlar'], 1, 0, [self::ekran([['tur' => 'baslik', 'metin' => 'Başlık']], ['gorsel' => str_repeat('c', 64), 'duzen' => 'tamEkran'])]);
        self::assertSame([], Tanim::hatalar($t, ['monthly']), 'varsayılan ana renk');
        self::assertSame([], Tanim::hatalar($t + ['tema' => ['anaRenk' => '#b7a6ff'] + Tema::VARSAYILAN], ['monthly']));
        $t['adimlar'][1]['duzen'] = 'kahraman';
        self::assertSame([], Tanim::hatalar($t + ['tema' => ['anaRenk' => '#111827'] + Tema::VARSAYILAN], ['monthly']));
    }

    /**
     * V5: grafik, önce/sonra ve ölçek geçerli; ölçeğin cevabı ("1"…"5") koşulda
     * seçenek gibi; önce/sonra görselleri "yüklenmiş mi" denetimine girer;
     * yayın sözleşmeye uyar.
     */
    public function testV5BloklariGecerliYayindaSozlesmeyeUyar(): void
    {
        [$a, $b] = [str_repeat('a', 64), str_repeat('b', 64)];
        $tanim = self::tanim('X');
        array_splice($tanim['adimlar'], 1, 0, [
            self::ekran([['tur' => 'soru', 'secimTuru' => 'olcek', 'olcekSol' => 'Hiç katılmıyorum', 'olcekSag' => 'Tamamen katılıyorum']], ['id' => 'olcek', 'dugme' => null, 'yonlendirme' => [['kosullar' => [['ozellik' => 'cevap.olcek', 'islem' => 'icerir', 'deger' => '5']], 'hedef' => 'paywall']]]),
            self::ekran([['tur' => 'grafik', 'baslangic' => 'Bugün', 'hedef' => '4. hafta', 'degerler' => [['kosullar' => [['ozellik' => 'cevap.olcek', 'islem' => 'icerir', 'deger' => '1']], 'alan' => 'hedef', 'deger' => '6. hafta']]], ['tur' => 'grafik', 'baslangic' => 'Şimdi', 'hedef' => 'Hedef', 'egri' => 'azalan'], ['tur' => 'onceSonra', 'once' => $a, 'sonra' => $b, 'onceEtiketi' => 'Önce', 'sonraEtiketi' => 'Sonra']], ['id' => 'sonuc']),
        ]);
        self::assertSame([], Tanim::hatalar($tanim, ['monthly']));
        self::assertSame([$a, $b], array_values(array_intersect(Tanim::gorseller($tanim), [$a, $b])));
        $this->medyaEkle($a);
        $this->medyaEkle($b);
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
    }

    /** Mobil görünüm 4: yükleme adımı geçerli, yayında sözleşmeye uyar. */
    public function testYuklemeAdimiGecerliYayindaSozlesmeyeUyar(): void
    {
        $tanim = self::tanim('X');
        array_splice($tanim['adimlar'], 2, 0, [
            ['id' => 'hazirlik', 'tur' => 'yukleme', 'baslik' => 'Planın hazırlanıyor', 'maddeler' => ['Cevapların inceleniyor', 'Plan seçiliyor'], 'sure' => 5],
            ['id' => 'kisa', 'tur' => 'yukleme', 'baslik' => 'Bir saniye'],
        ]);
        self::assertSame([], Tanim::hatalar($tanim, ['monthly']));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
    }

    /** Ç44: ekran kapağı ve seçenek görseli isteğe bağlı alt metin taşır. */
    public function testGorselMetniGecerliYayindaDoner(): void
    {
        $g = str_repeat('c', 64);
        $tanim = self::ile(0, 'gorsel', $g);
        $tanim['adimlar'][0]['gorselMetni'] = 'Uygulamanın sohbet ekranı';
        $tanim['adimlar'][1]['bloklar'] = [['tur' => 'soru', 'secenekler' => [
            ['id' => 'a', 'metin' => 'A', 'gorsel' => $g, 'gorselMetni' => 'Aile fotoğrafı'],
            ['id' => 'b', 'metin' => 'B'],
        ]]];
        self::assertSame([], Tanim::hatalar($tanim, ['monthly']));
        $this->medyaEkle($g);
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
    }

    /** G5: tanıtım düzeni; ust görselsiz de geçerli, kahraman/tamEkran görselle. */
    public function testTanitimDuzeniGecerli(): void
    {
        $gorselli = self::ile(0, 'gorsel', str_repeat('c', 64));
        foreach (['ust', 'kahraman', 'tamEkran'] as $duzen) {
            $t = $gorselli;
            $t['adimlar'][0]['duzen'] = $duzen;
            self::assertSame([], Tanim::hatalar($t, ['monthly']), $duzen);
        }
        self::assertSame([], Tanim::hatalar(self::ile(0, 'duzen', 'ust'), ['monthly']));
    }

    /** G4: seçmeli soruda seçenek dizilişi (soru bloğu). */
    public function testSoruDuzeniGecerli(): void
    {
        self::assertSame([], Tanim::hatalar(self::blok(['duzen' => 'izgara']), ['monthly']));
        self::assertSame([], Tanim::hatalar(self::blok(['duzen' => 'liste']), ['monthly']));
        $coklu = self::blok(['duzen' => 'izgara', 'secimTuru' => 'coklu'], ['dugme' => 'Devam']);
        self::assertSame([], Tanim::hatalar($coklu, ['monthly']));
    }

    /** Mobil görünüm 3: seçenek simgesi — tek grafem emoji (ZWJ aile, bayrak, ten tonu dahil). */
    public function testSecenekSimgesiGecerli(): void
    {
        $t = self::blok(['secenekler' => [
            ['id' => 'a', 'metin' => 'İş', 'simge' => '💼'],
            ['id' => 'b', 'metin' => 'Aile', 'simge' => '👨‍👩‍👧'],
            ['id' => 'c', 'metin' => 'Türkiye', 'simge' => '🇹🇷'],
            ['id' => 'd', 'metin' => 'Beğen', 'simge' => '👍🏽'],
            ['id' => 'e', 'metin' => 'Simgesiz'],
        ]]);
        self::assertSame([], Tanim::hatalar($t, ['monthly']));
    }

    public function testLogoGecerliYayindaDonerSozlesmeyeUyar(): void
    {
        $logo = str_repeat('a1', 32);
        $tanim = self::tanim('Logolu') + ['logo' => $logo];
        // Logo da adım görselleri gibi bu uygulamaya yüklenmiş olmalı (E2.3).
        self::assertSame(['görsel a1a1a1a1… bu uygulamada yok'], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->medyaEkle($logo);
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        $yanit = $this->yanit();
        self::assertSame($logo, \is_array($yanit['tanim'] ?? null) ? ($yanit['tanim']['logo'] ?? null) : null);
    }

    public function testIlerlemeTercihiYayindaDonerSozlesmeyeUyar(): void
    {
        $tanim = self::ile(0, 'ilerlemeGoster', false);
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        $tanimYaniti = $this->yanit()['tanim'] ?? null;
        self::assertIsArray($tanimYaniti);
        $adimlar = $tanimYaniti['adimlar'] ?? null;
        self::assertIsArray($adimlar);
        $ilkAdim = $adimlar[0] ?? null;
        self::assertIsArray($ilkAdim);
        self::assertFalse($ilkAdim['ilerlemeGoster'] ?? null);
    }

    public function testYayinYoksa404(): void
    {
        $this->client->request('GET', '/huni/keepchat');

        self::assertResponseStatusCodeSame(404);
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
    }

    public function testGecersizTaslakKaydedilmezYayinlanmaz(): void
    {
        $bozuk = self::tanim('X');
        $bozuk['adimlar'][0]['id'] = 'Büyük';

        self::assertNotSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $bozuk));
        self::assertEquals($this->huniler->bosTaslak('keepchat'), $this->huniler->taslak('keepchat', 'genel'));
        self::assertSame(["'yok' adında huni yok"], $this->huniler->yayinla('keepchat', 'yok'));
        self::assertNotSame([], $this->huniler->yayinla('keepchat', 'genel'), 'boş şablon doğrulamadan geçmemeli');
        self::assertSame(['bilinmeyen uygulama'], $this->huniler->taslakKaydet('olmayan', 'genel', self::tanim('X')));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bozuklar')]
    public function testDogrulamaKurallari(string $beklenen, mixed $bozuk): void
    {
        $hatalar = Tanim::hatalar($bozuk, ['monthly']);

        self::assertNotSame([], $hatalar);
        self::assertStringContainsString($beklenen, implode(' | ', $hatalar));
    }

    /**
     * Her örnek geçerli tanımı TEK bir kurala aykırı yapar.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function bozuklar(): iterable
    {
        $t = self::tanim('X');
        yield 'tekrar eden kimlik' => ['tekrar ediyor', self::ile(1, 'id', 'giris')];
        yield 'geçersiz kimlik' => ['kimlik küçük harf', self::ile(0, 'id', 'Giris')];
        yield 'bilinmeyen tür' => ['tür ekran, yukleme ya da paywall', self::ile(0, 'tur', 'html')];
        // T1c: eski içerik türleri ekrana taşındı (Ç86).
        yield 'eski tanıtım türü' => ['adım 1: tür ekran, yukleme ya da paywall', self::ile(0, 'tur', 'tanitim')];
        // T1: ekran adımı ve blok kataloğu.
        $ekr = static function (array $bloklar, array $ek = []): array {
            $t = self::tanim('X');
            array_splice($t['adimlar'], 1, 0, [self::ekran($bloklar, $ek)]);

            return $t;
        };
        $b = ['tur' => 'baslik', 'metin' => 'Başlık'];
        yield 'ekran 13 blok' => ['bloklar 0–12 öğeli liste', $ekr(array_fill(0, 13, $b))];
        yield 'ekran bloklar liste değil' => ['bloklar 0–12 öğeli liste', $ekr(['a' => $b])];
        yield 'ekran bloklar yok' => ['alanlar tam olarak', $ekr([], ['bloklar' => null])];
        yield 'ekran başlıksız' => ['alanlar tam olarak id, tur, baslik, bloklar', $ekr([$b], ['baslik' => null])];
        yield 'ekran başlığı boş' => ['adım 2 baslik: 1–200', $ekr([$b], ['baslik' => ' '])];
        yield 'ekran hedefi geriye' => ['hedef sonraki adımlardan', $ekr([$b], ['hedef' => 'giris'])];
        yield 'blok türü bilinmeyen' => ['blok 2: tür baslik, metin, gorsel, maddeler, yorum, istatistik, kutu, grafik, onceSonra ya da soru', $ekr([$b, ['tur' => 'html', 'metin' => 'x']])];
        yield 'blok dizi değil' => ['blok 2: tür', $ekr([$b, 'metin'])];
        yield 'blok fazla alan' => ['blok 2: alanlar tam olarak', $ekr([$b, ['tur' => 'metin', 'metin' => 'x', 'renk' => 'kirmizi']])];
        yield 'blok eksik alan' => ['blok 2: alanlar tam olarak', $ekr([$b, ['tur' => 'istatistik', 'deger' => '%5']])];
        yield 'blok başlık uzun' => ['blok 1 metin: 1–200 karakter', $ekr([['tur' => 'baslik', 'metin' => str_repeat('a', 201)]])];
        yield 'blok metin boş' => ['blok 2 metin: 1–1000 karakter', $ekr([$b, ['tur' => 'metin', 'metin' => ' ']])];
        yield 'blok görsel kimliği' => ['blok 2: görsel kimliği geçersiz', $ekr([$b, ['tur' => 'gorsel', 'gorsel' => 'a.png']])];
        yield 'blok görsel metni boş' => ['blok 2 gorselMetni: 1–200', $ekr([$b, ['tur' => 'gorsel', 'gorsel' => str_repeat('c', 64), 'gorselMetni' => '']])];
        yield 'blok madde yok' => ['blok 2: maddeler 1–6 öğeli liste', $ekr([$b, ['tur' => 'maddeler', 'maddeler' => []]])];
        yield 'blok yedi madde' => ['blok 2: maddeler 1–6 öğeli liste', $ekr([$b, ['tur' => 'maddeler', 'maddeler' => array_fill(0, 7, 'a')]])];
        yield 'blok madde uzun' => ['blok 2 madde 1: 1–120', $ekr([$b, ['tur' => 'maddeler', 'maddeler' => [str_repeat('a', 121)]]])];
        yield 'blok istatistik değeri uzun' => ['blok 2 deger: 1–12 karakter', $ekr([$b, ['tur' => 'istatistik', 'deger' => str_repeat('9', 13), 'metin' => 'x']])];
        yield 'blok puan 6' => ['blok 2: puan 1–5 tam sayı', $ekr([$b, ['tur' => 'yorum', 'metin' => 'x', 'yazar' => 'A', 'puan' => 6]])];
        yield 'blok puan metin' => ['blok 2: puan 1–5 tam sayı', $ekr([$b, ['tur' => 'yorum', 'metin' => 'x', 'yazar' => 'A', 'puan' => '5']])];
        yield 'blok yazar boş' => ['blok 2 yazar: 1–80', $ekr([$b, ['tur' => 'yorum', 'metin' => 'x', 'yazar' => '']])];
        yield 'blok kutu simgesi harf' => ['blok 2: simge tek emoji olmalı', $ekr([$b, ['tur' => 'kutu', 'metin' => 'x', 'simge' => 'A']])];
        yield 'blok kutu uzun' => ['blok 2 metin: 1–500', $ekr([$b, ['tur' => 'kutu', 'metin' => str_repeat('a', 501)]])];
        yield 'blok yerinde kimlik' => ['adım 2 blok 1', $ekr([['tur' => 'baslik', 'metin' => 'x', 'id' => 'b1']])];
        // T1c: ekran kapağı.
        $kapak = ['gorsel' => str_repeat('c', 64)];
        yield 'ekran kapak kimliği' => ['adım 2: görsel kimliği geçersiz', $ekr([$b], ['gorsel' => 'a.png'])];
        yield 'ekran kapaksız alt metin' => ['adım 2: gorselMetni yalnız gorsel ile', $ekr([$b], ['gorselMetni' => 'x'])];
        yield 'ekran kapaksız kahraman' => ['adım 2: duzen kahraman görsel ister', $ekr([$b], ['duzen' => 'kahraman'])];
        yield 'ekran düzen bilinmeyen' => ['adım 2: duzen: ust, kahraman, tamEkran', $ekr([$b], $kapak + ['duzen' => 'yan'])];
        yield 'tam ekranda kutu' => ['blok 2: tam ekranda yalnız baslik, metin, maddeler, istatistik', $ekr([$b, ['tur' => 'kutu', 'metin' => 'x']], $kapak + ['duzen' => 'tamEkran'])];
        // Ç101: dolu düğme kapak karartmasında (siyah–#1f1f1f) en az 3:1.
        $koyuTema = static function (string $ana): array {
            $t = self::tanim('X');
            array_splice($t['adimlar'], 1, 0, [self::ekran([['tur' => 'baslik', 'metin' => 'Başlık']], ['gorsel' => str_repeat('c', 64), 'duzen' => 'tamEkran'])]);

            return $t + ['tema' => ['anaRenk' => $ana] + Tema::VARSAYILAN];
        };
        yield 'tam ekran kapakta koyu düğme' => ['adım 2: tam ekran kapakta düğme karartmada görünmüyor (ana renk 1.08:1, en az 3.0:1)', $koyuTema('#111827')];
        yield 'tam ekran kapakta sınırın altı' => ['ana renk 2.46:1', $koyuTema('#1d4ed8')];
        yield 'tam ekranda soru' => ['blok 1: tam ekranda yalnız', $ekr([['tur' => 'soru', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']]]], $kapak + ['duzen' => 'tamEkran', 'dugme' => null])];
        // T3a: yönlendirme kuralları.
        $kural = static fn (array $kosul, array $ek = []): array => self::yonlendirmeli([array_merge(['kosullar' => [$kosul], 'hedef' => 'paywall'], $ek)]);
        yield 'kural liste değil' => ['yonlendirme 1–10 kurallık liste', self::yonlendirmeli(['x' => 1])];
        yield 'kural fazla alan' => ['kural 1: alanlar tam olarak', $kural(['ozellik' => 'utm.source', 'islem' => 'var'], ['renk' => 'x'])];
        yield 'kural hedefi geriye' => ['kural 1: hedef sonraki adımlardan', $kural(['ozellik' => 'utm.source', 'islem' => 'var'], ['hedef' => 'giris'])];
        yield 'kural bağlacı' => ['kural 1: baglac hepsi ya da herhangi', $kural(['ozellik' => 'utm.source', 'islem' => 'var'], ['baglac' => 'ya'])];
        yield 'koşulsuz kural' => ['kural 1: kosullar 1–5', self::yonlendirmeli([['kosullar' => [], 'hedef' => 'paywall']])];
        yield 'bilinmeyen özellik' => ['koşul 1: ozellik sözlükte yok: cihaz.tur', $kural(['ozellik' => 'cihaz.tur', 'islem' => 'var'])];
        yield 'hunide bilinmeyen özellik' => ['koşul 1: odeme.durum hunide bilinmez', $kural(['ozellik' => 'odeme.durum', 'islem' => 'var'])];
        yield 'sonraki adımın cevabı' => ['koşul 1: cevap.ara bu adımda ya da önceki adımlarda sorulmuyor', $kural(['ozellik' => 'cevap.ara', 'islem' => 'var'])];
        yield 'olmayan seçenek' => ['koşul 1: deger soru1 sorusunun seçeneklerinden olmalı (a, b)', $kural(['ozellik' => 'cevap.soru1', 'islem' => 'icerir', 'deger' => 'c'])];
        yield 'listede eşit' => ['koşul 1: cevap.soru1 için islem icerir, icermez, var ya da yok', $kural(['ozellik' => 'cevap.soru1', 'islem' => 'esit', 'deger' => 'a'])];
        yield 'metinde içerir' => ['koşul 1: utm.source için islem esit, esit_degil, var ya da yok', $kural(['ozellik' => 'utm.source', 'islem' => 'icerir', 'deger' => 'a'])];
        yield 'değersiz eşit' => ['koşul 1: deger gerekli', $kural(['ozellik' => 'utm.source', 'islem' => 'esit'])];
        yield 'var ile değer' => ['koşul 1: var ve yok değer almaz', $kural(['ozellik' => 'utm.source', 'islem' => 'var', 'deger' => 'x'])];
        yield 'sürüm metin' => ['koşul 1: huni.surum değeri tam sayı olmalı', $kural(['ozellik' => 'huni.surum', 'islem' => 'esit', 'deger' => '2'])];
        // T3b: koşullu gösterim (yalnız önceki adımların cevabı; ilk adımda yok).
        $goster = static fn (array $g, int $adim = 2): array => self::gosterli($g, $adim);
        yield 'göster koşulsuz' => ['goster: kosullar 1–5', $goster(['kosullar' => []])];
        yield 'göster fazla alan' => ['goster: alanlar tam olarak kosullar ve (isteğe bağlı) baglac', $goster(['kosullar' => [['ozellik' => 'utm.source', 'islem' => 'var']], 'hedef' => 'paywall'])];
        yield 'göster bağlacı' => ['goster: baglac hepsi ya da herhangi', $goster(['baglac' => 'ya', 'kosullar' => [['ozellik' => 'utm.source', 'islem' => 'var']]])];
        yield 'göster kendi cevabı' => ['goster koşul 1: cevap.ara bu adımda ya da önceki adımlarda sorulmuyor', $goster(['kosullar' => [['ozellik' => 'cevap.ara', 'islem' => 'var']]])];
        yield 'göster ilk adımda' => ['goster ilk adımda olmaz', $goster(['kosullar' => [['ozellik' => 'utm.source', 'islem' => 'var']]], 0)];
        yield 'göster koşul türü' => ['goster koşul 1: cevap.soru1 için islem icerir', $goster(['kosullar' => [['ozellik' => 'cevap.soru1', 'islem' => 'esit', 'deger' => 'a']]])];
        // T3c: koşullu değer — yalnız var olan metin alanı, değer o alanın kuralıyla.
        $kd = static fn (string $alan, mixed $deger, array $ek = []): array => array_merge(['kosullar' => [['ozellik' => 'utm.source', 'islem' => 'var']], 'alan' => $alan, 'deger' => $deger], $ek);
        yield 'değer: ekranda olmayan alan' => ['adım 3 degerler 1: dugme bu öğede değiştirilemez', self::degerli([$kd('dugme', 'Git')])];
        yield 'değer: bilinmeyen alan' => ['adım 3 degerler 1: renk bu öğede değiştirilemez', self::degerli([$kd('renk', 'x')])];
        yield 'değer: uzun başlık' => ['adım 3 degerler 1 baslik: 1–200 karakter', self::degerli([$kd('baslik', str_repeat('a', 201))])];
        yield 'değer: metin değil' => ['adım 3 degerler 1 baslik: 1–200 karakter', self::degerli([$kd('baslik', 5)])];
        yield 'değer: fazla alan' => ['adım 3 degerler 1: alanlar tam olarak kosullar, alan, deger', self::degerli([$kd('baslik', 'x', ['hedef' => 'paywall'])])];
        yield 'değer: kendi cevabı' => ['adım 3 degerler 1 koşul 1: cevap.ara bu adımda ya da önceki', self::degerli([$kd('baslik', 'x', ['kosullar' => [['ozellik' => 'cevap.ara', 'islem' => 'var']]])])];
        yield 'değer: liste değil' => ['adım 3: degerler 1–20 kurallık liste', self::degerli(['x' => 1])];
        yield 'blok değeri: olmayan alan' => ['adım 1 blok 1 degerler 1: gorsel bu öğede değiştirilemez', self::degerli([], [$kd('gorsel', str_repeat('a', 64))])];
        yield 'blok değeri: uzun metin' => ['adım 1 blok 1 degerler 1 metin: 1–1000 karakter', self::degerli([], [$kd('metin', str_repeat('a', 1001))])];
        yield 'blok değeri: soru bloğunda' => ['adım 2 blok 1: soru bloğunda degerler olmaz', self::degerli([], [], [$kd('metin', 'x')])];
        yield 'uzun değer' => ['koşul 1: deger 1–200 karakter', $kural(['ozellik' => 'utm.source', 'islem' => 'esit', 'deger' => str_repeat('a', 201)])];
        // T1b: soru bloğu.
        $iki = [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']];
        $tekli = ['tur' => 'soru', 'secenekler' => $iki];
        yield 'iki soru bloğu' => ['en çok bir soru bloğu', $ekr([$tekli, $tekli], ['dugme' => null])];
        yield 'tek seçimde düğme' => ['tek seçimli soru bloğu olan ekranda dugme olmaz', $ekr([$tekli])];
        yield 'tek seçimde ekran hedefi' => ['hedef seçeneklerde', $ekr([$tekli], ['dugme' => null, 'hedef' => 'paywall'])];
        yield 'çoklu seçimde düğme yok' => ['dugme olmalı', $ekr([$tekli + ['secimTuru' => 'coklu']], ['dugme' => null])];
        yield 'sorusuz ekranda düğme yok' => ['dugme olmalı', $ekr([$b], ['dugme' => null])];
        yield 'ekran düğmesi uzun' => ['adım 2 dugme: 1–60', $ekr([$b], ['dugme' => str_repeat('a', 61)])];
        yield 'soru bloğu tek seçenek' => ['blok 1: 2–8 seçenek', $ekr([['tur' => 'soru', 'secenekler' => [['id' => 'a', 'metin' => 'A']]]], ['dugme' => null])];
        yield 'soru bloğu seçenek kimliği tekrar' => ['blok 1 seçenek 2: kimlik geçersiz ya da tekrar ediyor', $ekr([['tur' => 'soru', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'a', 'metin' => 'B']]]], ['dugme' => null])];
        yield 'soru bloğu hedefi geriye' => ['blok 1 seçenek 1: hedef sonraki adımlardan', $ekr([['tur' => 'soru', 'secenekler' => [['id' => 'a', 'metin' => 'A', 'hedef' => 'giris'], ['id' => 'b', 'metin' => 'B']]]], ['dugme' => null])];
        yield 'soru bloğu çoklu hedef' => ['çoklu seçimde seçenek hedefi', $ekr([['tur' => 'soru', 'secimTuru' => 'coklu', 'secenekler' => [['id' => 'a', 'metin' => 'A', 'hedef' => 'paywall'], ['id' => 'b', 'metin' => 'B']]]])];
        yield 'soru bloğu serbest yanıtta seçenek' => ['blok 1: alanlar tam olarak', $ekr([['tur' => 'soru', 'secimTuru' => 'metin', 'secenekler' => $iki]])];
        yield 'soru bloğu seçeneksiz' => ['blok 1: alanlar tam olarak', $ekr([['tur' => 'soru']], ['dugme' => null])];
        yield 'soru bloğu tekli zorunlu' => ['yanitZorunlu yalnız serbest yanıt', $ekr([$tekli + ['yanitZorunlu' => true]], ['dugme' => null])];
        yield 'soru bloğu serbest düzen' => ['duzen yalnız seçmeli soruda', $ekr([['tur' => 'soru', 'secimTuru' => 'metin', 'duzen' => 'izgara']])];
        yield 'soru bloğu biçim' => ['blok 1: secimTuru tekli, coklu, metin, eposta ya da olcek', $ekr([$tekli + ['secimTuru' => 'iki']])];
        // E1: e-posta sorusu serbest yanıt ailesinde; izin kutusu yalnız onda.
        $eposta = ['tur' => 'soru', 'secimTuru' => 'eposta'];
        yield 'e-posta sorusunda seçenek' => ['blok 1: alanlar tam olarak', $ekr([$eposta + ['secenekler' => $iki]])];
        yield 'e-posta sorusunda düzen' => ['duzen yalnız seçmeli soruda', $ekr([$eposta + ['duzen' => 'izgara']])];
        yield 'e-posta sorusu düğmesiz' => ['dugme olmalı', $ekr([$eposta], ['dugme' => null])];
        yield 'izin metni serbest yanıtta' => ['blok 1: izinMetni yalnız eposta sorusunda', $ekr([['tur' => 'soru', 'secimTuru' => 'metin', 'izinMetni' => 'x']])];
        yield 'izin metni uzun' => ['blok 1 izinMetni: 1–200 karakter', $ekr([$eposta + ['izinMetni' => str_repeat('a', 201)]])];
        yield 'izin metni boş' => ['blok 1 izinMetni: 1–200 karakter', $ekr([$eposta + ['izinMetni' => ' ']])];
        $epostaKosulu = self::blok(['secimTuru' => 'eposta', 'secenekler' => null], ['dugme' => 'Devam', 'yonlendirme' => [['kosullar' => [['ozellik' => 'cevap.soru1', 'islem' => 'var']], 'hedef' => 'paywall']]]);
        yield 'e-posta cevabı koşulda' => ['koşul 1: cevap.soru1 e-posta sorusu; e-posta cevabı koşulda kullanılmaz', $epostaKosulu];
        // V5: grafik, önce/sonra, ölçek.
        $olcek = ['tur' => 'soru', 'secimTuru' => 'olcek', 'olcekSol' => 'Hayır', 'olcekSag' => 'Evet'];
        yield 'grafik hedefsiz' => ['blok 1: alanlar tam olarak tur, baslangic, hedef', $ekr([['tur' => 'grafik', 'baslangic' => 'Bugün']])];
        yield 'grafik eğri bilinmeyen' => ['blok 1: egri artan ya da azalan olmalı', $ekr([['tur' => 'grafik', 'baslangic' => 'A', 'hedef' => 'B', 'egri' => 'yatay']])];
        yield 'grafik etiket uzun' => ['blok 1 hedef: 1–40 karakter', $ekr([['tur' => 'grafik', 'baslangic' => 'A', 'hedef' => str_repeat('b', 41)]])];
        yield 'önce/sonra görsel kimliği' => ['blok 1 sonra: görsel kimliği geçersiz', $ekr([['tur' => 'onceSonra', 'once' => str_repeat('a', 64), 'sonra' => 'x.png', 'onceEtiketi' => 'Önce', 'sonraEtiketi' => 'Sonra']])];
        yield 'önce/sonra tam ekranda' => ['tam ekranda yalnız', $ekr([['tur' => 'onceSonra', 'once' => str_repeat('a', 64), 'sonra' => str_repeat('b', 64), 'onceEtiketi' => 'Ö', 'sonraEtiketi' => 'S']], ['gorsel' => str_repeat('c', 64), 'duzen' => 'tamEkran'])];
        yield 'ölçekte seçenek' => ['ölçek sorusunda secenekler yazılmaz', $ekr([$olcek + ['secenekler' => $iki]], ['dugme' => null])];
        yield 'ölçekte düzen' => ['ölçek sorusunda duzen olmaz', $ekr([$olcek + ['duzen' => 'izgara']], ['dugme' => null])];
        yield 'ölçek etiketsiz' => ['blok 1: alanlar tam olarak tur, olcekSol, olcekSag', $ekr([['tur' => 'soru', 'secimTuru' => 'olcek', 'olcekSol' => 'Hayır']], ['dugme' => null])];
        yield 'ölçek etiketi tekli soruda' => ['olcekSol ve olcekSag yalnız olcek sorusunda', $ekr([$tekli + ['olcekSol' => 'x']], ['dugme' => null])];
        yield 'ölçekte düğme' => ['tek seçimli soru bloğu olan ekranda dugme olmaz', $ekr([$olcek])];
        $olcekKosulu = self::blok(['secimTuru' => 'olcek', 'secenekler' => null, 'olcekSol' => 'H', 'olcekSag' => 'E'], ['yonlendirme' => [['kosullar' => [['ozellik' => 'cevap.soru1', 'islem' => 'icerir', 'deger' => '6']], 'hedef' => 'paywall']]]);
        yield 'ölçek cevabında olmayan değer' => ['koşul 1: deger soru1 sorusunun seçeneklerinden olmalı (1, 2, 3, 4, 5)', $olcekKosulu];
        // Mobil görünüm 4: yükleme adımı.
        $yuk = static function (array $ek): array {
            $t = self::tanim('X');
            array_splice($t['adimlar'], 2, 0, [['id' => 'hazirlik', 'tur' => 'yukleme', 'baslik' => 'Planın hazırlanıyor'] + $ek]);

            return $t;
        };
        yield 'yükleme süresi kısa' => ['sure: 2–10 tam sayı', $yuk(['sure' => 1])];
        yield 'yükleme süresi metin' => ['sure: 2–10 tam sayı', $yuk(['sure' => '5'])];
        yield 'yükleme madde çok' => ['maddeler: en çok 5', $yuk(['maddeler' => ['a', 'b', 'c', 'd', 'e', 'f']])];
        yield 'yükleme madde uzun' => ['1–80 karakter', $yuk(['maddeler' => [str_repeat('a', 81)]])];
        yield 'yükleme madde boş' => ['1–80 karakter', $yuk(['maddeler' => ['']])];
        yield 'yükleme maddeler liste değil' => ['maddeler: en çok 5', $yuk(['maddeler' => ['x' => 'a']])];
        yield 'yükleme düğme' => ['adım 3', $yuk(['dugme' => 'Devam'])];
        yield 'fazla alan' => ['alanlar tam olarak', self::ile(0, 'html', '<b>x</b>')];
        yield 'ilerleme göstergesi boolean değil' => ['ilerlemeGoster true ya da false', self::ile(0, 'ilerlemeGoster', 'evet')];
        yield 'soru yanıt biçimi geçersiz' => ['secimTuru tekli, coklu, metin, eposta ya da olcek olmalı', self::blok(['secimTuru' => 'belirsiz'])];
        yield 'eksik alan' => ['alanlar tam olarak', self::ile(0, 'baslik', null, sil: true)];
        yield 'uzun metin' => ['1–200 karakter', self::ile(0, 'baslik', str_repeat('a', 201))];
        yield 'boş başlık' => ['1–200 karakter', self::ile(0, 'baslik', '   ')];
        yield 'son adım paywall değil' => ['son adım paywall olmalı', ['sayfaBasligi' => 'X', 'adimlar' => array_reverse($t['adimlar'])]];
        yield 'paywall yok' => ['son adım paywall olmalı', ['sayfaBasligi' => 'X', 'adimlar' => [$t['adimlar'][0], $t['adimlar'][1]]]];
        // M2a: hata uygulamanın planlarını sayar (yönetici ve AI anahtarı tahmin etmesin).
        yield 'bilinmeyen plan' => ["plan 'yillik' bu uygulamada yok (planlar: monthly)", self::ile(2, 'plan', 'yillik')];
        yield 'tek seçenek' => ['2–8 seçenek', self::blok(['secenekler' => [['id' => 'a', 'metin' => 'A']]])];
        yield 'tekrar eden seçenek' => ['geçersiz ya da tekrar ediyor', self::blok(['secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'a', 'metin' => 'B']]])];
        yield 'tek adım' => ['2–50', ['sayfaBasligi' => 'X', 'adimlar' => [$t['adimlar'][2]]]];
        yield 'fazla üst alan' => ['yalnız sayfaBasligi, adimlar', $t + ['css' => 'x']];
        // H5: hedef yalnız ileri.
        $sec = static fn (mixed $hedef): array => [['id' => 'a', 'metin' => 'A', 'hedef' => $hedef], ['id' => 'b', 'metin' => 'B']];
        yield 'seçenek hedefi geriye' => ['hedef sonraki adımlardan', self::blok(['secenekler' => $sec('giris')])];
        yield 'seçenek hedefi kendisi' => ['hedef sonraki adımlardan', self::blok(['secenekler' => $sec('soru1')])];
        yield 'seçenek hedefi yok' => ['hedef sonraki adımlardan', self::blok(['secenekler' => $sec('yok')])];
        yield 'seçenek hedefi dizge değil' => ['hedef sonraki adımlardan', self::blok(['secenekler' => $sec(2)])];
        yield 'tanıtım hedefi kendisi' => ['hedef sonraki adımlardan', self::ile(0, 'hedef', 'giris')];
        // Ç44: görsel alt metni yalnız görselle, 1–200.
        yield 'görselsiz görsel metni' => ['gorselMetni yalnız gorsel ile', self::ile(0, 'gorselMetni', 'Ekran')];
        $uzun = self::ile(0, 'gorsel', str_repeat('c', 64));
        $uzun['adimlar'][0]['gorselMetni'] = str_repeat('a', 201);
        yield 'uzun görsel metni' => ['1–200 karakter', $uzun];
        yield 'seçenekte görselsiz görsel metni' => ['gorselMetni yalnız gorsel ile', self::blok(['secenekler' => [['id' => 'a', 'metin' => 'A', 'gorselMetni' => 'x'], ['id' => 'b', 'metin' => 'B']]])];
        // G5: düzen yalnız tanıtımda, bilinen değer; kahraman/tamEkran görsel ister.
        yield 'düzen bilinmeyen' => ['duzen: ust, kahraman, tamEkran', self::ile(0, 'duzen', 'yan')];
        yield 'görselsiz kahraman' => ['duzen kahraman görsel ister', self::ile(0, 'duzen', 'kahraman')];
        yield 'görselsiz tam ekran' => ['duzen tamEkran görsel ister', self::ile(0, 'duzen', 'tamEkran')];
        yield 'soru bloğunda kapak düzeni' => ['duzen: liste, izgara', self::blok(['duzen' => 'ust'])];
        // G4: soru düzeni yalnız seçmeli soruda.
        yield 'serbest yanıtta düzen' => ['duzen yalnız seçmeli soruda', self::blok(['secimTuru' => 'metin', 'secenekler' => null, 'duzen' => 'izgara'], ['dugme' => 'Devam'])];
        yield 'ekranda soru düzeni' => ['duzen: ust, kahraman, tamEkran', self::ile(0, 'duzen', 'izgara')];
        // Mobil görünüm 3: simge tek emoji, görselle birlikte değil.
        $simge = static fn (mixed $s, array $ek = []): array => self::blok(['secenekler' => [['id' => 'a', 'metin' => 'A', 'simge' => $s] + $ek, ['id' => 'b', 'metin' => 'B']]]);
        yield 'simge harf' => ['simge tek emoji olmalı', $simge('A')];
        yield 'simge iki emoji' => ['simge tek emoji olmalı', $simge('😀😀')];
        yield 'simge emoji + metin' => ['simge tek emoji olmalı', $simge('💼 iş')];
        yield 'simge sayı' => ['simge tek emoji olmalı', $simge(5)];
        yield 'simge ve görsel' => ['simge ve gorsel birlikte olmaz', $simge('💼', ['gorsel' => str_repeat('c', 64)])];
        // Ç61 (5): isteğe bağlı logo — görsel kimliği.
        yield 'logo geçersiz' => ['logo: görsel kimliği geçersiz', $t + ['logo' => 'logo.png']];
        yield 'logo boş' => ['logo: görsel kimliği geçersiz', $t + ['logo' => '']];
        // Ç61 (3): paywall fayda listesi + yasal bağlantılar, isteğe bağlı.
        yield 'fayda liste değil' => ['faydalar 1–6 öğeli liste', self::ile(2, 'faydalar', 'Hızlı')];
        yield 'yedi fayda' => ['faydalar 1–6 öğeli liste', self::ile(2, 'faydalar', array_fill(0, 7, 'Hızlı'))];
        yield 'boş fayda' => ['1–120 karakter', self::ile(2, 'faydalar', ['Hızlı', '  '])];
        yield 'http bağlantı' => ['https:// ile başlayan adres', self::ile(2, 'sartlar', 'http://ornek.com/sartlar')];
        yield 'javascript bağlantı' => ['https:// ile başlayan adres', self::ile(2, 'gizlilik', 'javascript:alert(1)')];
        yield 'boşluklu bağlantı' => ['https:// ile başlayan adres', self::ile(2, 'iptal', 'https://ornek.com/a b')];
        yield 'faydalar paywall dışında' => ['alanlar tam olarak', self::ile(0, 'faydalar', ['Hızlı'])];
        yield 'seçenekte fazla alan' => ['yalnız id, metin', self::blok(['secenekler' => [['id' => 'a', 'metin' => 'A', 'sonraki' => 'paywall'], ['id' => 'b', 'metin' => 'B']]])];
    }

    /** Ç61 (3): fayda listesi ve üç yasal bağlantı geçerli; hiçbiri zorunlu değil. */
    public function testPaywallFaydaVeYasalBaglantilarGecerli(): void
    {
        $t = self::ile(2, 'faydalar', ['Sınırsız sohbet', 'Reklamsız']);
        $t['adimlar'][2] += [
            'sartlar' => 'https://ornek.com/sartlar',
            'gizlilik' => 'https://ornek.com/gizlilik?dil=tr',
            'iptal' => 'https://ornek.com/iptal#iade',
        ];

        self::assertSame([], Tanim::hatalar($t, ['monthly']));
        self::assertSame([], Tanim::hatalar(self::ile(2, 'faydalar', array_fill(0, 6, str_repeat('a', 120))), ['monthly']));
    }

    /** H5: seçenek ve ekran düğmesi ileriki bir adıma atlayabilir. */
    public function testIleriHedefliDallanmaGecerli(): void
    {
        $t = self::blok(['secenekler' => [['id' => 'a', 'metin' => 'A', 'hedef' => 'paywall'], ['id' => 'b', 'metin' => 'B']]]);
        $t['adimlar'][0]['hedef'] = 'paywall';

        self::assertSame([], Tanim::hatalar($t, ['monthly']));
    }

    public function testIlerlemeGostergesiIstegeBagliVeBoolOlmali(): void
    {
        $t = self::ile(0, 'ilerlemeGoster', false);

        self::assertSame([], Tanim::hatalar($t, ['monthly']));
        self::assertSame([], Tanim::hatalar(self::tanim('Eski tanım'), ['monthly']));
    }

    public function testCokluSecimTanimiGecerliVeDevamDugmesiSinirli(): void
    {
        self::assertSame([], Tanim::hatalar(self::blok(['secimTuru' => 'coklu'], ['dugme' => 'Seçtiklerimle devam et']), ['monthly']));
        self::assertNotSame([], Tanim::hatalar(self::blok(['secimTuru' => 'coklu'], ['dugme' => str_repeat('x', 61)]), ['monthly']));
    }

    public function testCokluSecimdeSecenekHedefiKullanilamaz(): void
    {
        $t = self::blok(['secimTuru' => 'coklu', 'secenekler' => [
            ['id' => 'a', 'metin' => 'A', 'hedef' => 'paywall'],
            ['id' => 'b', 'metin' => 'B'],
        ]], ['dugme' => 'Devam']);

        self::assertStringContainsString(
            'çoklu seçimde seçenek hedefi kullanılamaz',
            implode(' | ', Tanim::hatalar($t, ['monthly'])),
        );
    }

    /** T1c: yorum ekranın yorum bloğu; yayında aynen saklanır. */
    public function testYorumBloguYayindaSaklanirVeAlaniSinirlanir(): void
    {
        $t = self::tanim('Yorum');
        $t['adimlar'][1] = self::ekran([['tur' => 'yorum', 'metin' => 'Çok faydalı', 'yazar' => 'Ayşe', 'unvan' => 'Kullanıcı']], ['id' => 'yorum', 'baslik' => 'Deneyim']);
        self::assertSame([], Tanim::hatalar($t, ['monthly']));
        $uzun = $t;
        $uzun['adimlar'][1] = self::ekran([['tur' => 'yorum', 'metin' => 'Çok faydalı', 'yazar' => str_repeat('a', 81)]], ['id' => 'yorum', 'baslik' => 'Deneyim']);
        self::assertNotSame([], Tanim::hatalar($uzun, ['monthly']));

        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $t));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->client->request('GET', '/huni/keepchat');
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        self::assertEquals($t, $this->yanit()['tanim'] ?? null);
    }

    public function testSerbestYanittaSecenekYokVeZorunlulukBoolOlarakSecilebilir(): void
    {
        $serbest = static fn (array $ek): array => self::blok(array_merge(['secimTuru' => 'metin', 'secenekler' => null, 'yanitZorunlu' => false], $ek), ['dugme' => 'Devam']);
        self::assertSame([], Tanim::hatalar($serbest([]), ['monthly']));
        self::assertStringContainsString('yanitZorunlu true ya da false', implode(' | ', Tanim::hatalar($serbest(['yanitZorunlu' => 'evet']), ['monthly'])));
        self::assertNotSame([], Tanim::hatalar($serbest(['secenekler' => [['id' => 'a', 'metin' => 'A']]]), ['monthly']));
    }

    public function testTohumdakiKeepchatHunisiGecerli(): void
    {
        // Göç tohumu (v1) + T1c taşıma göçü doğrulamadan geçmeli — yoksa ilk
        // yayın hiç açılmazdı. Tohum eski türlerle yazılı; taşıma göçü çevirir.
        $tohum = (new \ReflectionMethod(Version20261020180000::class, 'keepchat'))->invoke(null);
        self::assertIsArray($tohum);

        self::assertSame([], Tanim::hatalar(Version20261102120000::ekrana($tohum), ['monthly']));
    }

    public function testOnizlemeAnahtariTaslagiGosterirSahtesiYaDaEskisiGostermez(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Taslak'));
        $iyi = $this->huniler->onizlemeAnahtari('keepchat', 'genel', time());

        $this->client->request('GET', '/huni/keepchat/taslak', ['huni' => 'genel', 'anahtar' => $iyi]);
        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('GET', '/huni/{app}/taslak', $this->client->getResponse());
        self::assertEquals(['huni' => 'genel', 'surum' => null, 'tanim' => self::tanim('Taslak')], $this->yanit(fiyatsiz: true));
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');

        $eski = $this->huniler->onizlemeAnahtari('keepchat', 'genel', time() - Huniler::ONIZLEME_SN - 1);
        $baska = $this->huniler->onizlemeAnahtari('baska', 'genel', time());
        [$son, $imza] = explode('.', $iyi, 2) + ['', ''];
        // Süreyi uzatıp imzayı korumak: imza süreyi de kapsamalı.
        foreach (['', 'bozuk', $eski, $baska, ((int) $son + 999).'.'.$imza] as $anahtar) {
            $this->client->request('GET', '/huni/keepchat/taslak', ['huni' => 'genel', 'anahtar' => $anahtar]);
            self::assertResponseStatusCodeSame(403, $anahtar);
        }
        Sozlesme::yanitiDogrula('GET', '/huni/{app}/taslak', $this->client->getResponse());
    }

    public function testOnizlemeAnahtariYayinIleYayindakiSurumuGosterir(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('Bir'));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('İki'));
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'bos', null));

        $this->client->request('GET', '/huni/keepchat/taslak', ['huni' => 'genel', 'anahtar' => $this->huniler->onizlemeAnahtari('keepchat', 'genel', time()), 'yayin' => '1']);
        self::assertResponseStatusCodeSame(200);
        Sozlesme::yanitiDogrula('GET', '/huni/{app}/taslak', $this->client->getResponse());
        self::assertEquals(['huni' => 'genel', 'surum' => 1, 'tanim' => self::tanim('Bir')], $this->yanit(fiyatsiz: true));
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');

        // Yayınlanmamış hunide yayındaki sürüm yok.
        $this->client->request('GET', '/huni/keepchat/taslak', ['huni' => 'bos', 'anahtar' => $this->huniler->onizlemeAnahtari('keepchat', 'bos', time()), 'yayin' => '1']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testZiyaretGorduguSurumuTasirBilinmeyeniNullYazar(): void
    {
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('X'));
        $this->huniler->yayinla('keepchat', 'genel');

        foreach ([[['genel', 1], ['genel', 1]], [['genel', 7], [null, null]], [['baska', 1], [null, null]], [[null, 1], [null, null]]] as [[$huni, $surum], $beklenen]) {
            $this->client->jsonRequest('POST', '/huni/ziyaretler', array_filter(['app' => 'keepchat', 'huni' => $huni, 'surum' => $surum]));
            self::assertResponseStatusCodeSame(201);
            self::assertSame($beklenen, $this->db->fetchNumeric('SELECT huni, surum FROM huni_ziyaretleri WHERE id = :id', ['id' => $this->yanit()['ziyaret'] ?? '']) ?: []);
        }
        $this->client->jsonRequest('POST', '/huni/ziyaretler', ['app' => 'keepchat', 'surum' => '1']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testHuniUcundaCorsYalnizGetVeHuniKokeni(): void
    {
        $this->client->request('OPTIONS', '/huni/keepchat', [], [], [
            'HTTP_ORIGIN' => 'https://huni.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
        self::assertSame('https://huni.test', $this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));

        $this->client->request('OPTIONS', '/huni/keepchat', [], [], [
            'HTTP_ORIGIN' => 'https://kotu.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
        self::assertNull($this->client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testKomutYayinlarGecersizdeDurur(): void
    {
        $komut = (new Application(self::$kernel ?? self::bootKernel()))->find('app:huni');
        $this->huniler->taslakKaydet('keepchat', 'genel', self::tanim('X'));

        $t = new CommandTester($komut);
        self::assertSame(0, $t->execute(['islem' => 'yayinla', 'app' => 'keepchat']));
        self::assertStringContainsString('keepchat/genel v1 yayında', $t->getDisplay());

        // Taslak sonradan geçersizleşirse (ör. plan yapılandırmadan kalktı) yayın durur.
        $this->db->executeStatement("UPDATE huniler SET taslak = jsonb_set(taslak, '{adimlar,2,plan}', '\"yillik\"')");
        self::assertSame(1, $t->execute(['islem' => 'yayinla', 'app' => 'keepchat']));
        self::assertSame([1], $this->db->fetchFirstColumn('SELECT surum FROM huni_surumleri'));
    }

    /** @return array{sayfaBasligi: string, adimlar: array<int, array<string, mixed>>} */
    private static function tanim(string $baslik): array
    {
        return [
            'sayfaBasligi' => $baslik,
            'adimlar' => [
                ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [['tur' => 'metin', 'metin' => 'Kısa']], 'dugme' => 'Başla'],
                ['id' => 'soru1', 'tur' => 'ekran', 'baslik' => 'Ne?', 'bloklar' => [['tur' => 'soru', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']]]]],
                ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '9,99 €', 'dugme' => 'Abone ol', 'plan' => 'monthly'],
            ],
        ];
    }

    /**
     * T1: ekran adımı (giris'ten sonra konur).
     *
     * @param array<mixed> $bloklar
     * @param array<mixed> $ek      null değer alanı siler
     *
     * @return array<mixed>
     */
    private static function ekran(array $bloklar, array $ek = []): array
    {
        return array_filter(array_merge(['id' => 'neden', 'tur' => 'ekran', 'baslik' => 'Neden?', 'bloklar' => $bloklar, 'dugme' => 'Devam'], $ek), static fn (mixed $d): bool => null !== $d);
    }

    /**
     * T1c: ikinci ekranın soru bloğu değişik (eski soru adımı testleri);
     * null değer alanı siler. Seçenekler yoksa A/B.
     *
     * @param array<mixed>         $blokEk
     * @param array<string, mixed> $ekranEk
     *
     * @return array{sayfaBasligi: string, adimlar: array<int, array<string, mixed>>}
     */
    private static function blok(array $blokEk, array $ekranEk = []): array
    {
        $t = self::tanim('X');
        $blok = array_filter(array_merge(['tur' => 'soru', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']]], $blokEk), static fn (mixed $d): bool => null !== $d);
        $t['adimlar'][1] = array_merge(['id' => 'soru1', 'tur' => 'ekran', 'baslik' => 'Ne?', 'bloklar' => [$blok]], $ekranEk);

        return $t;
    }

    /** @return array{sayfaBasligi: string, adimlar: array<int, array<string, mixed>>} */
    private static function ile(int $adim, string $alan, mixed $deger, bool $sil = false): array
    {
        $t = self::tanim('X');
        if ($sil) {
            unset($t['adimlar'][$adim][$alan]);
        } else {
            $t['adimlar'][$adim][$alan] = $deger;
        }

        return $t;
    }

    private function medyaEkle(string $id): void
    {
        $this->db->executeStatement(
            "INSERT INTO medya (app, id, genislik, yukseklik, bayt, ozgun_ad) VALUES ('keepchat', :id, 1, 1, 1, 'test.webp') ON CONFLICT DO NOTHING",
            ['id' => $id],
        );
    }

    /**
     * @param bool $fiyatsiz Stripe fiyatı (Ç19/Ç21) bu testlerin konusu değil — FiyatTest
     *
     * @return array<mixed>
     */
    private function yanit(bool $fiyatsiz = false): array
    {
        $j = json_decode((string) $this->client->getResponse()->getContent(), true);
        if ($fiyatsiz && \is_array($j)) {
            unset($j['fiyatlar']);
        }

        return \is_array($j) ? $j : [];
    }

    /** @return array<string, mixed> */
    private function jsonYanit(): array
    {
        /** @var array<string, mixed> $g */
        $g = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $g;
    }
}
