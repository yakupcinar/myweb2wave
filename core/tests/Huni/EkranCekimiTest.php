<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Ekran\EkranCekimi;
use App\Tests\Support\SahteEkranCekici;
use PHPUnit\Framework\TestCase;

/**
 * V1: ölçümden uyarı eşikleri. Boşluk sınırı %35 (uyku-ornek ölçümü:
 * zengin ekranlar 0,21–0,33, seyrek bilgi ekranları 0,37–0,49); kaydırılan
 * ekranda boşluk anlamsız.
 */
final class EkranCekimiTest extends TestCase
{
    public function testTemizOlcumdeUyariYok(): void
    {
        self::assertSame([], (new EkranCekimi('x', SahteEkranCekici::TEMIZ))->uyarilar());
    }

    public function testBoslukSinirdaUyarmazUstundeUyarir(): void
    {
        self::assertSame([], self::cekim(['bosAlt' => 0.35])->uyarilar());
        self::assertCount(1, self::cekim(['bosAlt' => 0.36])->uyarilar());
        self::assertStringContainsString('%36', self::cekim(['bosAlt' => 0.36])->uyarilar()[0]);
    }

    public function testKaydirilanEkrandaBoslukUyarisiYok(): void
    {
        self::assertSame([], self::cekim(['bosAlt' => 0.6, 'kaydirma' => true])->uyarilar());
    }

    public function testDugmesizEkrandaDugmeUyarisiYok(): void
    {
        self::assertSame([], self::cekim(['dugmeGorunur' => null])->uyarilar());
        self::assertCount(1, self::cekim(['dugmeGorunur' => false])->uyarilar());
    }

    /** @param array<string, mixed> $ek */
    private static function cekim(array $ek): EkranCekimi
    {
        /** @var array{bosAlt: float, kaydirma: bool, dugmeGorunur: bool|null, yetimSatirlar: list<string>, kucukHedefler: int, tasanlar: int} $olcum */
        $olcum = array_merge(SahteEkranCekici::TEMIZ, $ek);

        return new EkranCekimi('x', $olcum);
    }
}
