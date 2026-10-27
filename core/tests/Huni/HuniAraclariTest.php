<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Arac\AracKapsami;
use App\Huni\Arac\HuniAraclari;
use App\Huni\Huniler;
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
        $db->executeStatement('TRUNCATE huniler, huni_surumleri, huni_kurallari, huni_ziyaretleri, odeme_oturumlari, huni_adimlari, huni_cevaplari, huni_metin_cevaplari, medya');
        $huniler = static::getContainer()->get(Huniler::class);
        self::assertInstanceOf(Huniler::class, $huniler);
        $this->huniler = $huniler;
        self::assertSame([], $this->huniler->yeniHuni('keepchat', 'genel', null));
        // Henüz tüketicisi yok (A1 komutu, A2 sohbet) → kapta yok; aynı bağımlılıklarla kurulur.
        $kok = static::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($kok);
        $this->araclar = new HuniAraclari($huniler, $kok.'/config/huni/tanim-semasi.json', 'https://huni.test');
    }

    /** Yayınlama, silme, kural aracı YOK; açık huni üzerindeki sohbette yeni huni de yok. */
    public function testAracListesiYalnizTaslakVeKapsamaGore(): void
    {
        $adlar = fn (?string $huni): array => array_column($this->araclarTanimi($huni), 'name');

        self::assertSame(['huniler_listele', 'huni_oku', 'tanim_dogrula', 'taslak_kaydet', 'onizleme_adresi', 'yeni_huni'], $adlar(null));
        self::assertSame(['huniler_listele', 'huni_oku', 'tanim_dogrula', 'taslak_kaydet', 'onizleme_adresi'], $adlar('genel'));
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
                ['id' => 'karsilama', 'tur' => 'tanitim', 'baslik' => 'Sana özel antrenman planı', 'metin' => '60 saniyede hedefini belirleyelim.', 'dugme' => 'Başla'],
                ['id' => 'hedef', 'tur' => 'soru', 'soru' => 'Ana hedefin ne?', 'secenekler' => [
                    ['id' => 'kilo', 'metin' => 'Kilo vermek', 'hedef' => $ilkHedef],
                    ['id' => 'kas', 'metin' => 'Kas yapmak', 'hedef' => 'kas-sorusu'],
                ]],
                ['id' => 'kilo-sorusu', 'tur' => 'soru', 'soru' => 'Kaç kilo vermek istiyorsun?', 'secenekler' => [['id' => 'az', 'metin' => '1-5 kg', 'hedef' => 'sure'], ['id' => 'cok', 'metin' => '5+ kg', 'hedef' => 'sure']]],
                ['id' => 'kas-sorusu', 'tur' => 'soru', 'soru' => 'Haftada kaç gün antrenman?', 'secenekler' => [['id' => '2', 'metin' => '2 gün'], ['id' => '4', 'metin' => '4+ gün']]],
                ['id' => 'sure', 'tur' => 'soru', 'soru' => 'Günde ne kadar vaktin var?', 'secimTuru' => 'coklu', 'secenekler' => [['id' => '15', 'metin' => '15 dk'], ['id' => '30', 'metin' => '30 dk']]],
                ['id' => 'paywall', 'tur' => 'paywall', 'plan' => 'monthly', 'baslik' => 'Planın hazır', 'metin' => 'Tüm antrenmanlara eriş.', 'dugme' => 'Abone ol', 'faydalar' => ['Kişisel program', 'Video dersler']],
            ],
        ];
    }
}
