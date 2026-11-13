<?php

declare(strict_types=1);

namespace App\Tests\Panel;

use App\Olay\Olaylar;
use PHPUnit\Framework\TestCase;

/**
 * P7d (A25): webhook olaylarının Türkçe adları panelde (olaylar.ts), sözlük
 * çekirdekte (Olaylar::SOZLUK). Kural 7: sözlüğe eklenen her olayın panelde
 * tam bir kez adı var; panelde sözlükte olmayan ad yok (eskimiş ad kalmasın).
 */
final class PanelOlayAdlariTest extends TestCase
{
    public function testHerOlayinTekTurkceAdiVar(): void
    {
        $ts = file_get_contents(\dirname(__DIR__, 2).'/panel/src/uygulama/olaylar.ts');
        self::assertIsString($ts);
        preg_match_all('/\["([a-z]+\.[a-z]+)", "[^"]+"\]/u', $ts, $m);
        $paneldekiler = $m[1];
        sort($paneldekiler);
        $sozluk = array_keys(Olaylar::SOZLUK);
        sort($sozluk);
        self::assertSame($sozluk, $paneldekiler);
    }
}
