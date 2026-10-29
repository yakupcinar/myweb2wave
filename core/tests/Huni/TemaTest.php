<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Tanim;
use App\Huni\Tema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Huni teması (E2.2): biçim ve okunurluk yayından önce. */
final class TemaTest extends TestCase
{
    public function testVarsayilanGecerli(): void
    {
        self::assertSame([], Tema::hatalar(Tema::VARSAYILAN));
    }

    /** Ç71: AI'a gösterilen örnek temalar yayından geçmeli ve birbirinden, varsayılandan farklı olmalı. */
    public function testAiOrnekTemalariGecerliVeFarkli(): void
    {
        self::assertGreaterThanOrEqual(4, \count(Tema::AI_ORNEKLERI));
        foreach (Tema::AI_ORNEKLERI as $tur => $tema) {
            self::assertSame([], Tema::hatalar($tema), $tur);
        }
        self::assertGreaterThanOrEqual(3, \count(array_unique(array_column(Tema::AI_ORNEKLERI, 'yaziTipi'))));
        // G3: zemin örneği de gösterilir (G1: görünümü en çok zemin değiştirir).
        self::assertGreaterThanOrEqual(2, \count(array_filter(Tema::AI_ORNEKLERI, static fn (array $t): bool => isset($t['zemin']))));
        // G6: kartlı örnek de (koyu zemin + açık kart — kartsız geçersiz olurdu).
        self::assertGreaterThanOrEqual(1, \count(array_filter(Tema::AI_ORNEKLERI, static fn (array $t): bool => isset($t['kartRengi']))));
        // G7: yerleşim ön ayarı kullanan örnekler de (G1: başlık ve boşluk düzeni en çok değiştiriyor).
        self::assertGreaterThanOrEqual(2, \count(array_filter(Tema::AI_ORNEKLERI, static fn (array $t): bool => [] !== array_intersect_key($t, Tema::ON_AYARLAR))));
        // Ana renkler birbirinden ve varsayılandan farklı.
        self::assertCount(\count(Tema::AI_ORNEKLERI) + 1, array_unique([...array_column(Tema::AI_ORNEKLERI, 'anaRenk'), Tema::VARSAYILAN['anaRenk']]));

        $y = Tema::aiYonergesi();
        // Bağımsız beklenti (§3): alanlar ve sınırlar elle yazıldı.
        foreach (['sistem, serif, yuvarlak, mono', '0–32', '4.5:1', 'tema yoksa', 'varsayılan görünüm', 'ekran_goruntusu', 'zemin', 'gecis', 'karartma', 'kartRengi', 'baslikBoyu', 'yogunluk', 'hizalama'] as $parca) {
            self::assertStringContainsString($parca, $y);
        }
        foreach (Tema::AI_ORNEKLERI as $tema) {
            self::assertStringContainsString($tema['anaRenk'], $y);
        }
    }

    public function testKontrastBilinenDegerler(): void
    {
        // Bağımsız kaynak: WCAG tanımı — siyah/beyaz 21:1; #767676 beyazda AA sınırının hemen üstü (tuzak §3).
        self::assertEqualsWithDelta(21.0, Tema::kontrast('#000000', '#ffffff'), 0.001);
        self::assertEqualsWithDelta(1.0, Tema::kontrast('#2563eb', '#2563eb'), 0.001);
        self::assertEqualsWithDelta(4.54, Tema::kontrast('#767676', '#ffffff'), 0.01);
        self::assertEqualsWithDelta(4.48, Tema::kontrast('#777777', '#ffffff'), 0.01);
    }

    /** Huni sayfası ve panel temayı huni-bilesenleri/tema.ts ile çizer; tema.test.ts aynı örnekleri okur (B4/H1). */
    public function testHuniBilesenleriyleOrtakOrnekler(): void
    {
        $dosya = \dirname(__DIR__, 3).'/huni-bilesenleri/tema-ornekleri.json';
        /** @var array{varsayilan: array<string, mixed>, yaziTipleri: list<string>, kontrast: list<array{a: string, b: string, oran: float}>} $o */
        $o = json_decode((string) file_get_contents($dosya), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame($o['varsayilan'], Tema::VARSAYILAN);
        self::assertSame($o['yaziTipleri'], Tema::YAZI_TIPLERI);
        foreach ($o['kontrast'] as ['a' => $a, 'b' => $b, 'oran' => $oran]) {
            self::assertEqualsWithDelta($oran, Tema::kontrast($a, $b), 0.01, "$a / $b");
        }
    }

    public function testOkunurlukSiniri(): void
    {
        self::assertSame([], Tema::hatalar(['metinRengi' => '#767676'] + Tema::VARSAYILAN));
        self::assertStringContainsString('okunmuyor', implode('', Tema::hatalar(['metinRengi' => '#777777'] + Tema::VARSAYILAN)));
    }

    /** G3: geçişte metin alt renkte de okunmalı (oranlar bağımsız hesaplandı, §3). */
    public function testZeminGecisiAltRenkteDeOkunmali(): void
    {
        self::assertSame([], Tema::hatalar(['zemin' => ['tur' => 'gecis', 'ikinciRenk' => '#e0e7ff']] + Tema::VARSAYILAN));
        $h = implode('', Tema::hatalar(['zemin' => ['tur' => 'gecis', 'ikinciRenk' => '#6b7280']] + Tema::VARSAYILAN));
        self::assertStringContainsString('geçişin alt renginde okunmuyor (kontrast 3.67:1', $h);
    }

    /**
     * G3: görselin içeriği bilinmez — karartma en kötü durumda (tam siyah ya
     * da tam beyaz görsel) metni okutmalı. Eşikler bağımsız hesaplandı (§3):
     * beyaz zemin + #111827 → %51, #0b1120 + #e2e8f0 → %64.
     */
    public function testZeminGorseliKarartmasiEnKotuGorseleGore(): void
    {
        $gorsel = static fn (int $k, array $t = []): array => ['zemin' => ['tur' => 'gorsel', 'gorsel' => str_repeat('a', 64), 'karartma' => $k]] + $t + Tema::VARSAYILAN;
        $koyu = ['arkaPlan' => '#0b1120', 'metinRengi' => '#e2e8f0'];

        self::assertSame([], Tema::hatalar($gorsel(51)));
        self::assertStringContainsString('karartma en az %51 olmalı (şu an %50)', implode('', Tema::hatalar($gorsel(50))));
        self::assertSame([], Tema::hatalar($gorsel(64, $koyu)));
        self::assertStringContainsString('karartma en az %64 olmalı (şu an %63)', implode('', Tema::hatalar($gorsel(63, $koyu))));
    }

    /**
     * G6: kart varsa metin kartın üstünde — okunurluk metin ↔ kart; zemin
     * yalnız çevrede (koyu zemin + açık kart + koyu metin geçerli). Oranlar
     * bağımsız (§3): #0f172a/#1e3a8a 1,72 · #0f172a/#334155 1,72.
     */
    public function testKartVarsaMetinKartUstundeOlculur(): void
    {
        $koyuZemin = ['anaRenk' => '#1d4ed8', 'arkaPlan' => '#1e3a8a', 'metinRengi' => '#0f172a', 'yaziTipi' => 'serif', 'kose' => 8];

        self::assertStringContainsString('arka planda okunmuyor', implode('', Tema::hatalar($koyuZemin)), 'kartsız: metin zeminde');
        self::assertSame([], Tema::hatalar(['kartRengi' => '#ffffff'] + $koyuZemin));
        self::assertStringContainsString('metin kartta okunmuyor (kontrast 1.72:1', implode('', Tema::hatalar(['kartRengi' => '#334155'] + $koyuZemin)));
        // Görsel zeminde karartma eşiği metin içindir; kart varsa metin görselin üstünde değil.
        self::assertSame([], Tema::hatalar(['kartRengi' => '#ffffff', 'zemin' => ['tur' => 'gorsel', 'gorsel' => str_repeat('a', 64), 'karartma' => 0]] + $koyuZemin));
        // Biçim yine denetlenir.
        self::assertStringContainsString('karartma: 0–95', implode('', Tema::hatalar(['kartRengi' => '#ffffff', 'zemin' => ['tur' => 'gorsel', 'gorsel' => str_repeat('a', 64), 'karartma' => 99]] + $koyuZemin)));
    }

    /** G7: ön ayarlar isteğe bağlı; yalnız tanımlı değerler. */
    public function testYerlesimOnAyarlari(): void
    {
        self::assertSame([], Tema::hatalar(['baslikBoyu' => 'buyuk', 'yogunluk' => 'ferah', 'hizalama' => 'orta'] + Tema::VARSAYILAN));
        self::assertSame([], Tema::hatalar(['baslikBoyu' => 'kucuk', 'yogunluk' => 'sik', 'hizalama' => 'sol'] + Tema::VARSAYILAN));
    }

    #[DataProvider('bozuklar')]
    public function testBicimKurallari(string $beklenen, mixed $tema): void
    {
        self::assertStringContainsString($beklenen, implode(' | ', Tema::hatalar($tema)));
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function bozuklar(): iterable
    {
        $v = Tema::VARSAYILAN;
        yield 'büyük harf hex' => ['#rrggbb', ['anaRenk' => '#2563EB'] + $v];
        yield 'başlık boyu bilinmeyen' => ['tema baslikBoyu: kucuk, normal, buyuk', ['baslikBoyu' => '40px'] + $v];
        yield 'yoğunluk bilinmeyen' => ['tema yogunluk: sik, normal, ferah', ['yogunluk' => 'genis'] + $v];
        yield 'hizalama bilinmeyen' => ['tema hizalama: sol, orta', ['hizalama' => 'sag'] + $v];
        yield 'kart rengi büyük harf' => ['tema kartRengi: #rrggbb', ['kartRengi' => '#FFFFFF'] + $v];
        yield 'zemin bilinmeyen tür' => ['tema zemin: tur gecis ya da gorsel', ['zemin' => ['tur' => 'video']] + $v];
        yield 'zemin nesne değil' => ['tema zemin: tur gecis ya da gorsel', ['zemin' => 'gecis'] + $v];
        yield 'geçişte fazla alan' => ['tema zemin (gecis): alanlar tam olarak tur, ikinciRenk', ['zemin' => ['tur' => 'gecis', 'ikinciRenk' => '#ffffff', 'yon' => 'yatay']] + $v];
        yield 'geçiş renk büyük harf' => ['tema zemin ikinciRenk: #rrggbb', ['zemin' => ['tur' => 'gecis', 'ikinciRenk' => '#FFFFFF']] + $v];
        yield 'görselde eksik alan' => ['tema zemin (gorsel): alanlar tam olarak tur, gorsel, karartma', ['zemin' => ['tur' => 'gorsel', 'gorsel' => str_repeat('a', 64)]] + $v];
        yield 'karartma sınır dışı' => ['tema zemin karartma: 0–95 tam sayı', ['zemin' => ['tur' => 'gorsel', 'gorsel' => str_repeat('a', 64), 'karartma' => 96]] + $v];
        yield 'görsel kimliği bozuk' => ['tema zemin gorsel: görsel kimliği geçersiz', ['zemin' => ['tur' => 'gorsel', 'gorsel' => '../x', 'karartma' => 80]] + $v];
        yield 'karartma metin' => ['tema zemin karartma: 0–95 tam sayı', ['zemin' => ['tur' => 'gorsel', 'gorsel' => str_repeat('a', 64), 'karartma' => '60']] + $v];
        yield 'kısa hex' => ['#rrggbb', ['arkaPlan' => '#fff'] + $v];
        yield 'css enjeksiyonu' => ['#rrggbb', ['metinRengi' => 'red;}body{display:none'] + $v];
        yield 'geçerli renkten sonra css' => ['#rrggbb', ['anaRenk' => '#000000;}body{display:none}'] + $v];
        yield 'bilinmeyen yazı tipi' => ['yaziTipi', ['yaziTipi' => 'Comic Sans'] + $v];
        yield 'köşe büyük' => ['kose', ['kose' => 33] + $v];
        yield 'köşe dizge' => ['kose', ['kose' => '10'] + $v];
        yield 'eksik alan' => ['tam olarak', array_diff_key($v, ['kose' => 0])];
        yield 'fazla alan' => ['tam olarak', $v + ['css' => 'x']];
        yield 'nesne değil' => ['nesne', 'mavi'];
    }

    public function testTanimTemaylaGecerliBozukTemaylaGecersiz(): void
    {
        $tanim = ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'a', 'tur' => 'tanitim', 'baslik' => 'A', 'metin' => '', 'dugme' => 'B'],
            ['id' => 'p', 'tur' => 'paywall', 'baslik' => 'P', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly'],
        ]];

        self::assertSame([], Tanim::hatalar($tanim, ['monthly']), 'tema isteğe bağlı');
        self::assertSame([], Tanim::hatalar($tanim + ['tema' => Tema::VARSAYILAN], ['monthly']));
        self::assertNotSame([], Tanim::hatalar($tanim + ['tema' => ['kose' => 99] + Tema::VARSAYILAN], ['monthly']));
    }
}
