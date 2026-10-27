<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Huni\Huniler;
use App\Huni\Tanim;
use App\Tests\Support\Sozlesme;
use Doctrine\DBAL\Connection;
use DoctrineMigrations\Version20261020180000;
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
        $this->db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, medya');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));
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
        self::assertResponseHeaderSame('Cache-Control', 'max-age=60, public');
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

    /** Ç44: tanıtım kapağı ve seçenek görseli isteğe bağlı alt metin taşır. */
    public function testGorselMetniGecerliYayindaDoner(): void
    {
        $g = str_repeat('c', 64);
        $tanim = self::ile(0, 'gorsel', $g);
        $tanim['adimlar'][0]['gorselMetni'] = 'Uygulamanın sohbet ekranı';
        $tanim['adimlar'][1]['secenekler'] = [
            ['id' => 'a', 'metin' => 'A', 'gorsel' => $g, 'gorselMetni' => 'Aile fotoğrafı'],
            ['id' => 'b', 'metin' => 'B'],
        ];
        self::assertSame([], Tanim::hatalar($tanim, ['monthly']));
        $this->medyaEkle($g);
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $tanim));
        $this->huniler->yayinla('keepchat', 'genel');

        $this->client->request('GET', '/huni/keepchat');

        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
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
        yield 'bilinmeyen tür' => ['tür tanitim, soru, yorum ya da paywall', self::ile(0, 'tur', 'html')];
        yield 'fazla alan' => ['alanlar tam olarak', self::ile(0, 'html', '<b>x</b>')];
        yield 'ilerleme göstergesi boolean değil' => ['ilerlemeGoster true ya da false', self::ile(0, 'ilerlemeGoster', 'evet')];
        yield 'soru yanıt biçimi geçersiz' => ['secimTuru tekli, coklu ya da metin olmalı', self::ile(1, 'secimTuru', 'belirsiz')];
        yield 'eksik alan' => ['alanlar tam olarak', self::ile(0, 'dugme', null, sil: true)];
        yield 'uzun metin' => ['1–200 karakter', self::ile(0, 'baslik', str_repeat('a', 201))];
        yield 'boş başlık' => ['1–200 karakter', self::ile(0, 'baslik', '   ')];
        yield 'paywall sonda değil' => ['EN SONDA', ['sayfaBasligi' => 'X', 'adimlar' => array_reverse($t['adimlar'])]];
        yield 'paywall yok' => ['EN SONDA', ['sayfaBasligi' => 'X', 'adimlar' => [$t['adimlar'][0], $t['adimlar'][1]]]];
        yield 'bilinmeyen plan' => ['plan bu uygulamada yok', self::ile(2, 'plan', 'yillik')];
        yield 'tek seçenek' => ['2–8 seçenek', self::ile(1, 'secenekler', [['id' => 'a', 'metin' => 'A']])];
        yield 'tekrar eden seçenek' => ['geçersiz ya da tekrar ediyor', self::ile(1, 'secenekler', [['id' => 'a', 'metin' => 'A'], ['id' => 'a', 'metin' => 'B']])];
        yield 'tek adım' => ['2–50', ['sayfaBasligi' => 'X', 'adimlar' => [$t['adimlar'][2]]]];
        yield 'fazla üst alan' => ['yalnız sayfaBasligi, adimlar', $t + ['css' => 'x']];
        // H5: hedef yalnız ileri.
        $sec = static fn (mixed $hedef): array => [['id' => 'a', 'metin' => 'A', 'hedef' => $hedef], ['id' => 'b', 'metin' => 'B']];
        yield 'seçenek hedefi geriye' => ['hedef sonraki adımlardan', self::ile(1, 'secenekler', $sec('giris'))];
        yield 'seçenek hedefi kendisi' => ['hedef sonraki adımlardan', self::ile(1, 'secenekler', $sec('soru1'))];
        yield 'seçenek hedefi yok' => ['hedef sonraki adımlardan', self::ile(1, 'secenekler', $sec('yok'))];
        yield 'seçenek hedefi dizge değil' => ['hedef sonraki adımlardan', self::ile(1, 'secenekler', $sec(2))];
        yield 'tanıtım hedefi kendisi' => ['hedef sonraki adımlardan', self::ile(0, 'hedef', 'giris')];
        // Ç44: görsel alt metni yalnız görselle, 1–200.
        yield 'görselsiz görsel metni' => ['gorselMetni yalnız gorsel ile', self::ile(0, 'gorselMetni', 'Ekran')];
        $uzun = self::ile(0, 'gorsel', str_repeat('c', 64));
        $uzun['adimlar'][0]['gorselMetni'] = str_repeat('a', 201);
        yield 'uzun görsel metni' => ['1–200 karakter', $uzun];
        yield 'seçenekte görselsiz görsel metni' => ['gorselMetni yalnız gorsel ile', self::ile(1, 'secenekler', [['id' => 'a', 'metin' => 'A', 'gorselMetni' => 'x'], ['id' => 'b', 'metin' => 'B']])];
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
        yield 'seçenekte fazla alan' => ['yalnız id, metin', self::ile(1, 'secenekler', [['id' => 'a', 'metin' => 'A', 'sonraki' => 'paywall'], ['id' => 'b', 'metin' => 'B']])];
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

    /** H5: seçenek ve tanıtım düğmesi ileriki bir adıma atlayabilir. */
    public function testIleriHedefliDallanmaGecerli(): void
    {
        $t = self::ile(1, 'secenekler', [['id' => 'a', 'metin' => 'A', 'hedef' => 'paywall'], ['id' => 'b', 'metin' => 'B']]);
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
        $t = self::ile(1, 'secimTuru', 'coklu');
        $t['adimlar'][1]['devamDugmesi'] = 'Seçtiklerimle devam et';

        self::assertSame([], Tanim::hatalar($t, ['monthly']));
        $t['adimlar'][1]['devamDugmesi'] = str_repeat('x', 61);
        self::assertNotSame([], Tanim::hatalar($t, ['monthly']));
    }

    public function testCokluSecimdeSecenekHedefiKullanilamaz(): void
    {
        $t = self::tanim('Çoklu');
        $t['adimlar'][1]['secimTuru'] = 'coklu';
        $t['adimlar'][1]['secenekler'] = [
            ['id' => 'a', 'metin' => 'A', 'hedef' => 'paywall'],
            ['id' => 'b', 'metin' => 'B'],
        ];

        self::assertStringContainsString(
            'çoklu seçimde seçenek hedefi kullanılamaz',
            implode(' | ', Tanim::hatalar($t, ['monthly'])),
        );
    }

    public function testYorumAdimiYayindaSaklanirVeAlaniSinirlanir(): void
    {
        $t = self::tanim('Yorum');
        $t['adimlar'][1] = [
            'id' => 'yorum', 'tur' => 'yorum', 'baslik' => 'Deneyim',
            'metin' => 'Çok faydalı', 'yazar' => 'Ayşe', 'unvan' => 'Kullanıcı', 'dugme' => 'Devam',
        ];
        self::assertSame([], Tanim::hatalar($t, ['monthly']));
        self::assertNotSame([], Tanim::hatalar(self::ile(1, 'yazar', str_repeat('a', 81)), ['monthly']));
        self::assertNotSame([], Tanim::hatalar(self::ile(1, 'fazla', 'alan'), ['monthly']));

        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', $t));
        $this->huniler->yayinla('keepchat', 'genel');
        $this->client->request('GET', '/huni/keepchat');
        Sozlesme::yanitiDogrula('GET', '/huni/{app}', $this->client->getResponse());
        self::assertEquals($t, $this->yanit()['tanim'] ?? null);
    }

    public function testSoruAciklamasiIstegeBagliVeUzunluguSinirli(): void
    {
        self::assertSame([], Tanim::hatalar(self::tanim('Açıklamasız'), ['monthly']));
        self::assertSame([], Tanim::hatalar(self::ile(1, 'aciklama', 'Kısa bir açıklama'), ['monthly']));
        self::assertNotSame([], Tanim::hatalar(self::ile(1, 'aciklama', str_repeat('a', 501)), ['monthly']));
        self::assertNotSame([], Tanim::hatalar(self::ile(1, 'aciklama', 123), ['monthly']));
    }

    public function testSerbestYanittaSecenekYokVeZorunlulukBoolOlarakSecilebilir(): void
    {
        $t = self::ile(1, 'secimTuru', 'metin');
        unset($t['adimlar'][1]['secenekler']);
        $t['adimlar'][1]['yanitZorunlu'] = false;
        self::assertSame([], Tanim::hatalar($t, ['monthly']));

        $t['adimlar'][1]['yanitZorunlu'] = 'evet';
        self::assertStringContainsString('yanitZorunlu true ya da false', implode(' | ', Tanim::hatalar($t, ['monthly'])));

        $t['adimlar'][1]['yanitZorunlu'] = false;
        $t['adimlar'][1]['secenekler'] = [['id' => 'a', 'metin' => 'A']];
        self::assertNotSame([], Tanim::hatalar($t, ['monthly']));
    }

    public function testTohumdakiKeepchatHunisiGecerli(): void
    {
        // Göç tohumu (v1) doğrulamadan geçmeli — yoksa ilk yayın hiç açılmazdı.
        // Göçler otomatik yüklemede değil (DoctrineMigrations bundle'ın yolu).
        require_once \dirname(__DIR__, 2).'/migrations/Version20261020180000.php';
        $tohum = (new \ReflectionMethod(Version20261020180000::class, 'keepchat'))->invoke(null);

        self::assertSame([], Tanim::hatalar($tohum, ['monthly']));
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
                ['id' => 'giris', 'tur' => 'tanitim', 'baslik' => 'Merhaba', 'metin' => 'Kısa', 'dugme' => 'Başla'],
                ['id' => 'soru1', 'tur' => 'soru', 'soru' => 'Ne?', 'secenekler' => [['id' => 'a', 'metin' => 'A'], ['id' => 'b', 'metin' => 'B']]],
                ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '9,99 €', 'dugme' => 'Abone ol', 'plan' => 'monthly'],
            ],
        ];
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
}
