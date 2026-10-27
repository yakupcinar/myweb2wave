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
        // Ana renkler birbirinden ve varsayılandan farklı.
        self::assertCount(\count(Tema::AI_ORNEKLERI) + 1, array_unique([...array_column(Tema::AI_ORNEKLERI, 'anaRenk'), Tema::VARSAYILAN['anaRenk']]));

        $y = Tema::aiYonergesi();
        // Bağımsız beklenti (§3): alanlar ve sınırlar elle yazıldı.
        foreach (['sistem, serif, yuvarlak, mono', '0–32', '4.5:1', 'tema yoksa', 'varsayılan görünüm'] as $parca) {
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
