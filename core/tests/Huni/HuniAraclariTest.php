<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Arac\AracKapsami;
use App\Huni\Arac\HuniAraclari;
use App\Huni\Huniler;
use App\Tests\Support\SahteEkranCekici;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/** AI araç katmanı (A0): sözleşmeden şema, yalnız taslak, imza, kapsam. */
final class HuniAraclariTest extends KernelTestCase
{
    private HuniAraclari $araclar;
    private Huniler $huniler;

    protected function setUp(): void
    {
        self::bootKernel();
        $db = static::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $db);
        $db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, ziyaret_deneyleri, olaylar, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, huni_epostalari, medya');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));
        $araclar = static::getContainer()->get(HuniAraclari::class);
        self::assertInstanceOf(HuniAraclari::class, $araclar);
        $this->araclar = $araclar;
        SahteEkranCekici::sifirla();
    }

    /** Yayınlama, silme, kural aracı YOK; açık huni üzerindeki sohbette yeni huni de yok. */
    public function testAracListesiYalnizTaslakVeKapsamaGore(): void
    {
        $adlar = fn (?string $huni): array => array_column($this->araclarTanimi($huni), 'name');

        self::assertSame(['huniler_listele', 'huni_oku', 'tanim_dogrula', 'taslak_kaydet', 'onizleme_adresi', 'ekran_goruntusu', 'stok_gorsel_ara', 'stok_gorsel_al', 'yeni_huni'], $adlar(null));
        self::assertSame(['huniler_listele', 'huni_oku', 'tanim_dogrula', 'taslak_kaydet', 'onizleme_adresi', 'ekran_goruntusu', 'stok_gorsel_ara', 'stok_gorsel_al'], $adlar('genel'));
    }

    /** Tanım şeması sözleşmedeki HuniTanimi'nin kendisi (bağımsız okuma: YAML doğrudan) ve her $ref çözülür. */
    public function testTanimSemasiSozlesmedenVeKendiKendineYeter(): void
    {
        $kaydet = array_column($this->araclarTanimi(null), 'input_schema', 'name')['taslak_kaydet'];
        /** @var array{components: array{schemas: array<string, array<string, mixed>>}} $sozlesme */
        $sozlesme = Yaml::parseFile('/contract/openapi.yaml');
        $beklenen = $sozlesme['components']['schemas']['HuniTanimi'];

        self::assertIsArray($kaydet['$defs']);
        $kok = $kaydet['$defs']['HuniTanimi'];
        self::assertIsArray($kok);
        self::assertSame($beklenen['required'], $kok['required']);
        self::assertIsArray($beklenen['properties']);
        self::assertIsArray($kok['properties']);
        self::assertSame(array_keys($beklenen['properties']), array_keys($kok['properties']));
        $json = (string) json_encode($kaydet);
        self::assertStringNotContainsString('#\/components', $json);
        preg_match_all('~"\$ref":"#\\\/\$defs\\\/([A-Za-z]+)"~', $json, $m);
        self::assertNotEmpty($m[1]);
        foreach ($m[1] as $ad) {
            self::assertArrayHasKey($ad, $kaydet['$defs'], "çözülmeyen \$ref: {$ad}");
        }
    }

    public function testSemaDosyasiSozlesmeyleGuncel(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $komut = new CommandTester((new Application($kernel))->find('app:huni-semasi'));

        self::assertSame(0, $komut->execute(['--kontrol' => true]), $komut->getDisplay());
    }

    /** Promptla yazılmış dallanmalı huni (Ç67 ölçümü): oku → kaydet → yeniden oku aynı tanım. */
    public function testOkuKaydetPromptlaYazilmisHuni(): void
    {
        $k = new AracKapsami('keepchat', 'genel');
        $oku = $this->araclar->calistir('huni_oku', ['huni' => 'genel'], $k);
        self::assertFalse($oku->hata);

        $kayit = $this->araclar->calistir('taslak_kaydet', ['huni' => 'genel', 'tanim' => self::fitness(), 'imza' => $oku->icerik['imza']], $k);

        self::assertFalse($kayit->hata, json_encode($kayit->icerik, \JSON_UNESCAPED_UNICODE) ?: '');
        // assertEquals: JSONB nesne anahtarlarının sırasını değiştirir (tuzak §28); liste sırası yine ölçülür.
        self::assertEquals(self::fitness(), $this->huniler->taslak('keepchat', 'genel'));
        self::assertSame($this->huniler->taslakImzasi('keepchat', 'genel'), $kayit->icerik['imza']);
        self::assertNull($this->huniler->huniler('keepchat')[0]['yayinda'], 'yayınlanmadı');
    }

    public function testGecersizTanimYazilmazHatalarModeleDoner(): void
    {
        $k = new AracKapsami('keepchat');
        $imza = $this->huniler->taslakImzasi('keepchat', 'genel');
        $bozuk = self::fitness(ilkHedef: 'olmayan-adim');

        $dogrula = $this->araclar->calistir('tanim_dogrula', ['tanim' => $bozuk], $k);
        $kayit = $this->araclar->calistir('taslak_kaydet', ['huni' => 'genel', 'tanim' => $bozuk, 'imza' => $imza], $k);

        self::assertFalse($dogrula->icerik['gecerli']);
        self::assertTrue($kayit->hata);
        self::assertSame($dogrula->icerik['hatalar'], $kayit->icerik['hatalar']);
        self::assertStringContainsString('yalnız ileri', (string) json_encode($kayit->icerik['hatalar'], \JSON_UNESCAPED_UNICODE));
        self::assertSame($imza, $this->huniler->taslakImzasi('keepchat', 'genel'), 'taslak değişmedi');
    }

    /** Ç39: model eski imzayla yazarsa paneldeki değişikliği ezmez; imzasız yazamaz. */
    public function testEskiImzaPaneldekiDegisikligiEzmez(): void
    {
        $k = new AracKapsami('keepchat', 'genel');
        $eski = $this->araclar->calistir('huni_oku', ['huni' => 'genel'], $k)->icerik['imza'];
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::fitness('Panelden')));

        $kayit = $this->araclar->calistir('taslak_kaydet', ['huni' => 'genel', 'tanim' => self::fitness('AI'), 'imza' => $eski], $k);
        // null: çekirdekte "imza denetleme" demek (panelin eski yolu) — araç bunu kabul etmemeli.
        $imzasiz = $this->araclar->calistir('taslak_kaydet', ['huni' => 'genel', 'tanim' => self::fitness('AI'), 'imza' => null], $k);

        self::assertSame([Huniler::CAKISMA], $kayit->icerik['hatalar']);
        self::assertTrue($imzasiz->hata);
        self::assertSame('Panelden', $this->huniler->taslak('keepchat', 'genel')['sayfaBasligi'] ?? null);
    }

    /** Uygulama yapı gereği sabit; açık huni dışına, fazla alana, kapsamda olmayan araca izin yok. */
    public function testKapsamDisiReddedilir(): void
    {
        $k = new AracKapsami('keepchat', 'genel');
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'baska', null));

        self::assertSame(["bu oturum yalnız 'genel' hunisinde çalışır"], $this->araclar->calistir('huni_oku', ['huni' => 'baska'], $k)->icerik['hatalar']);
        self::assertSame(['bilinmeyen alan: app'], $this->araclar->calistir('huni_oku', ['huni' => 'genel', 'app' => 'baska'], $k)->icerik['hatalar']);
        self::assertSame(['bilinmeyen araç: yeni_huni'], $this->araclar->calistir('yeni_huni', ['huni' => 'x'], $k)->icerik['hatalar']);
        self::assertSame(['bilinmeyen araç: yayinla'], $this->araclar->calistir('yayinla', ['huni' => 'genel'], $k)->icerik['hatalar']);
        self::assertSame(['huni: küçük harf, rakam, - ve _ (1–64)'], $this->araclar->calistir('huni_oku', ['huni' => '../x'], new AracKapsami('keepchat'))->icerik['hatalar']);
        self::assertSame(['eksik alan: huni'], $this->araclar->calistir('huni_oku', [], $k)->icerik['hatalar']);
    }

    public function testYeniHuniListeVeOnizleme(): void
    {
        $k = new AracKapsami('keepchat');

        $yeni = $this->araclar->calistir('yeni_huni', ['huni' => 'fitness', 'kaynak' => 'genel'], $k);
        $liste = $this->araclar->calistir('huniler_listele', [], $k);
        $onizleme = $this->araclar->calistir('onizleme_adresi', ['huni' => 'fitness'], $k);

        self::assertFalse($yeni->hata);
        self::assertSame(['genel', 'fitness'], array_column((array) $liste->icerik['huniler'], 'ad'));
        $adres = $onizleme->icerik['adres'];
        self::assertIsString($adres);
        self::assertMatchesRegularExpression('~/keepchat/\?huni=fitness&onizleme=~', $adres);
        parse_str((string) parse_url($adres, \PHP_URL_QUERY), $q);
        self::assertIsString($q['onizleme'] ?? null);
        self::assertTrue($this->huniler->anahtarGecerli('keepchat', 'fitness', $q['onizleme'], time()));
    }

    /** V6: şablonlar aracın şemasında (enum + açıklama); şablondan açılan huni okunabilir, kaynakla birlikte reddedilir. */
    public function testYeniHuniSablondan(): void
    {
        $k = new AracKapsami('keepchat');
        $sema = array_column($this->araclarTanimi(null), 'input_schema', 'name')['yeni_huni'];
        $sablon = ((array) $sema['properties'])['sablon'];
        self::assertIsArray($sablon);
        self::assertSame(['dil', 'finans', 'fitness', 'uyku'], $sablon['enum']);
        self::assertIsString($sablon['description']);
        self::assertStringContainsString('uyku — Uyku düzeni testi', $sablon['description']);

        self::assertFalse($this->araclar->calistir('yeni_huni', ['huni' => 'ders', 'sablon' => 'dil'], $k)->hata);
        $oku = $this->araclar->calistir('huni_oku', ['huni' => 'ders'], $k);
        self::assertIsArray($oku->icerik['tanim']);
        self::assertSame('Sana özel dil planı', $oku->icerik['tanim']['sayfaBasligi']);
        self::assertSame(['kaynak huni ya da şablon — ikisi birden olmaz'], $this->araclar->calistir('yeni_huni', ['huni' => 'iki', 'kaynak' => 'genel', 'sablon' => 'dil'], $k)->icerik['hatalar']);
        self::assertSame(['sablon: küçük harf, rakam, - ve _ (1–64)'], $this->araclar->calistir('yeni_huni', ['huni' => 'uc', 'sablon' => '../x'], $k)->icerik['hatalar']);
    }

    /** Ç72: adımın önizlemesi tarayıcıda çekilir; adım ve sürüm adrese, görüntü sonuca. */
    public function testEkranGoruntusuAdimVeSurumleOnizlemeyiCeker(): void
    {
        $k = new AracKapsami('keepchat');
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::fitness('Yayındaki')));
        self::assertIsInt($this->huniler->yayinla('keepchat', 'genel'));
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::fitness('Taslak')));

        $ilk = $this->araclar->calistir('ekran_goruntusu', ['huni' => 'genel'], $k);
        self::assertFalse($ilk->hata);
        self::assertSame(SahteEkranCekici::BAYTLAR, $ilk->gorsel);
        self::assertSame(['huni' => 'genel', 'adim' => 'karsilama', 'surum' => 'taslak', 'adimlar' => ['karsilama', 'hedef', 'kilo-sorusu', 'kas-sorusu', 'sure', 'paywall'], 'olcum' => SahteEkranCekici::TEMIZ, 'uyarilar' => []], $ilk->icerik);
        $q = self::sorgu(SahteEkranCekici::$adresler[0], 'https://huni.test/keepchat/');
        self::assertSame(['huni', 'adim', 'ekran', 'onizleme'], array_keys($q));
        // Ç75: ekran=1 — şeritsiz, ödeme düğmesi ziyaretçideki gibi çizilir.
        self::assertSame(['genel', 'karsilama', '1'], [$q['huni'], $q['adim'], $q['ekran']]);
        self::assertTrue($this->huniler->anahtarGecerli('keepchat', 'genel', $q['onizleme'], time()));

        $yayin = $this->araclar->calistir('ekran_goruntusu', ['huni' => 'genel', 'adim' => 'paywall', 'surum' => 'yayin'], $k);
        self::assertFalse($yayin->hata);
        $q = self::sorgu(SahteEkranCekici::$adresler[1], 'https://huni.test/keepchat/');
        self::assertSame(['genel', '1', 'paywall'], [$q['huni'], $q['yayin'] ?? null, $q['adim']]);
    }

    /** V1: tarayıcının ölçtüğü yerleşim kusuru modele Türkçe uyarı olarak gider. */
    public function testEkranGoruntusuOlcumUyarilariSonucta(): void
    {
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::fitness()));
        SahteEkranCekici::$olcum = ['bosAlt' => 0.42, 'kaydirma' => false, 'dugmeGorunur' => false, 'yetimSatirlar' => ['Uzun bir cümle aç'], 'kucukHedefler' => 2, 'tasanlar' => 1];

        $s = $this->araclar->calistir('ekran_goruntusu', ['huni' => 'genel'], new AracKapsami('keepchat'));

        self::assertFalse($s->hata);
        self::assertSame([
            'içerikle düğme (ya da ekranın altı) arasında ekranın %42 kadar boşluk: içerik üstte toplanmış — blok ekle (görsel, istatistik, kutu, yorum) ya da var olanı zenginleştir',
            'birincil düğme ilk ekranda görünmüyor (kaydırmak gerekiyor): blokları azalt ya da metni kısalt',
            'son satırda tek kısa kelime kalmış: "Uzun bir cümle aç" — metni yeniden yaz',
            '2 dokunma hedefi 44 pikselden alçak',
            '1 öğenin metni yatayda taşıyor (bölünmeyen uzun kelime?)',
        ], $s->icerik['uyarilar'] ?? null);
    }

    public function testEkranGoruntusuHatalariTarayiciyaGitmeden(): void
    {
        $k = new AracKapsami('keepchat');
        self::assertSame([], $this->huniler->taslakKaydet('keepchat', 'genel', self::fitness()));

        $yokAdim = $this->araclar->calistir('ekran_goruntusu', ['huni' => 'genel', 'adim' => 'yok'], $k);
        $yayinYok = $this->araclar->calistir('ekran_goruntusu', ['huni' => 'genel', 'surum' => 'yayin'], $k);
        $yokHuni = $this->araclar->calistir('ekran_goruntusu', ['huni' => 'bos'], $k);
        $bozukAdim = $this->araclar->calistir('ekran_goruntusu', ['huni' => 'genel', 'adim' => '../x'], $k);

        self::assertSame(['hatalar' => ["adım 'yok' yok; adımlar: karsilama, hedef, kilo-sorusu, kas-sorusu, sure, paywall"]], $yokAdim->icerik);
        self::assertSame(['hatalar' => ["'genel' henüz yayınlanmadı — surum: taslak kullan"]], $yayinYok->icerik);
        self::assertSame(['hatalar' => ["'bos' adında huni yok"]], $yokHuni->icerik);
        self::assertTrue($bozukAdim->hata);
        self::assertSame([], SahteEkranCekici::$adresler, 'hatalı istek tarayıcıya gitmedi');
        foreach ([$yokAdim, $yayinYok, $yokHuni, $bozukAdim] as $s) {
            self::assertNull($s->gorsel);
        }

        SahteEkranCekici::$hata = 'tarayıcı servisine ulaşılamadı (ekran:9222)';
        $kapali = $this->araclar->calistir('ekran_goruntusu', ['huni' => 'genel'], $k);
        self::assertSame(['hatalar' => ['ekran görüntüsü alınamadı: tarayıcı servisine ulaşılamadı (ekran:9222)']], $kapali->icerik);
    }

    /** @return array<array-key, string> */
    private static function sorgu(string $adres, string $onEk): array
    {
        self::assertStringStartsWith($onEk.'?', $adres);
        parse_str((string) parse_url($adres, \PHP_URL_QUERY), $q);

        return array_map(static fn (mixed $d): string => \is_string($d) ? $d : '', $q);
    }

    /** @return list<array{name: string, description: string, input_schema: array<string, mixed>}> */
    private function araclarTanimi(?string $huni): array
    {
        return $this->araclar->tanimlar(new AracKapsami('keepchat', $huni));
    }

    /**
     * Ç67'de "fitness için dallanmalı quiz" promptuyla yazılan tanım.
     *
     * @return array<string, mixed>
     */
    private static function fitness(string $baslik = 'FitPlan', string $ilkHedef = 'kilo-sorusu'): array
    {
        return [
            'sayfaBasligi' => $baslik,
            'adimlar' => [
                ['id' => 'karsilama', 'tur' => 'ekran', 'baslik' => 'Sana özel antrenman planı', 'bloklar' => [['tur' => 'metin', 'metin' => '60 saniyede hedefini belirleyelim.']], 'dugme' => 'Başla'],
                ['id' => 'hedef', 'tur' => 'ekran', 'baslik' => 'Ana hedefin ne?', 'bloklar' => [['tur' => 'soru', 'secenekler' => [
                    ['id' => 'kilo', 'metin' => 'Kilo vermek', 'hedef' => $ilkHedef],
                    ['id' => 'kas', 'metin' => 'Kas yapmak', 'hedef' => 'kas-sorusu'],
                ]]]],
                ['id' => 'kilo-sorusu', 'tur' => 'ekran', 'baslik' => 'Kaç kilo vermek istiyorsun?', 'bloklar' => [['tur' => 'soru', 'secenekler' => [['id' => 'az', 'metin' => '1-5 kg', 'hedef' => 'sure'], ['id' => 'cok', 'metin' => '5+ kg', 'hedef' => 'sure']]]]],
                ['id' => 'kas-sorusu', 'tur' => 'ekran', 'baslik' => 'Haftada kaç gün antrenman?', 'bloklar' => [['tur' => 'soru', 'secenekler' => [['id' => '2', 'metin' => '2 gün'], ['id' => '4', 'metin' => '4+ gün']]]]],
                ['id' => 'sure', 'tur' => 'ekran', 'baslik' => 'Günde ne kadar vaktin var?', 'bloklar' => [['tur' => 'soru', 'secimTuru' => 'coklu', 'secenekler' => [['id' => '15', 'metin' => '15 dk'], ['id' => '30', 'metin' => '30 dk']]]], 'dugme' => 'Devam'],
                ['id' => 'paywall', 'tur' => 'paywall', 'plan' => 'monthly', 'baslik' => 'Planın hazır', 'metin' => 'Tüm antrenmanlara eriş.', 'dugme' => 'Abone ol', 'faydalar' => ['Kişisel program', 'Video dersler']],
            ],
        ];
    }
}
