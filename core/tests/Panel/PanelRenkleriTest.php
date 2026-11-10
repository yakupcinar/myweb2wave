<?php

declare(strict_types=1);

namespace App\Tests\Panel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * P6: panelin renk değişkenleri iki yerde — React panel (stil.css) ve Twig
 * sayfaları (base.html.twig: giriş, kabuk). Kural 7: aynı değerler. Ayrıca
 * her metin rengi her zeminde WCAG AA (≥ 4.5:1); renk değişince okunabilirlik
 * sessizce düşmesin (hesap WCAG 2 bağıl parlaklık formülü, koddan bağımsız).
 */
final class PanelRenkleriTest extends TestCase
{
    private const METINLER = ['metin', 'soluk', 'vurgu', 'iyi', 'tehlike', 'uyari'];
    private const ZEMINLER = ['zemin', 'yuzey', 'yuzey-2'];
    /** Twig kopyasında "uyari" yerine "reklam" adı. */
    private const ORTAK = ['zemin', 'yuzey', 'yuzey-2', 'kenar', 'metin', 'soluk', 'vurgu', 'vurgu-yazi', 'iyi', 'iyi-zemin', 'tehlike', 'tehlike-zemin'];

    /** @return iterable<string, array{string}> */
    public static function modlar(): iterable
    {
        yield 'açık' => ['acik'];
        yield 'koyu' => ['koyu'];
    }

    #[DataProvider('modlar')]
    public function testIkiKopyaAyni(string $mod): void
    {
        $panel = self::degiskenler(self::oku('/panel/src/uygulama/stil.css'), $mod);
        $twig = self::degiskenler(self::oku('/templates/base.html.twig'), $mod);
        foreach (self::ORTAK as $ad) {
            self::assertArrayHasKey($ad, $panel, "stil.css --{$ad}");
            self::assertSame($panel[$ad], $twig[$ad] ?? null, "--{$ad} ({$mod})");
        }
        self::assertSame($panel['uyari'] ?? null, $twig['reklam'] ?? null, "--uyari / --reklam ({$mod})");
    }

    #[DataProvider('modlar')]
    public function testMetinRenkleriOkunur(string $mod): void
    {
        $r = self::degiskenler(self::oku('/panel/src/uygulama/stil.css'), $mod);
        foreach (self::METINLER as $metin) {
            foreach (self::ZEMINLER as $zemin) {
                $oran = self::oran($r[$metin] ?? '', $r[$zemin] ?? '');
                self::assertGreaterThanOrEqual(4.5, $oran, "--{$metin} / --{$zemin} ({$mod}): {$oran}");
            }
        }
        self::assertGreaterThanOrEqual(4.5, self::oran($r['vurgu-yazi'] ?? '', $r['vurgu'] ?? ''), "düğme yazısı / vurgu ({$mod})");
        // P7a: onay satırındaki dolu kırmızı düğme ("İade et").
        self::assertGreaterThanOrEqual(4.5, self::oran($r['vurgu-yazi'] ?? '', $r['tehlike'] ?? ''), "düğme yazısı / tehlike ({$mod})");
    }

    /**
     * P7a: tema seçimi boyamadan ÖNCE uygulanır (§40) — iki kabukta da tema
     * betiği stilden önce; anahtar React tarafıyla (tema.ts) aynı (kural 7).
     */
    public function testTemaBetigiStildenOnceVeAnahtarAyni(): void
    {
        foreach (['/templates/base.html.twig' => '<style>', '/templates/panel/uygulama.html.twig' => 'stiller'] as $yol => $stil) {
            $sablon = self::oku($yol);
            $betik = strpos($sablon, "include('panel/tema.html.twig')");
            self::assertNotFalse($betik, $yol);
            self::assertLessThan(strpos($sablon, $stil), $betik, $yol);
        }
        self::assertStringContainsString("localStorage.getItem('panel-tema')", self::oku('/templates/panel/tema.html.twig'));
        self::assertStringContainsString('const ANAHTAR = "panel-tema";', self::oku('/panel/src/uygulama/tema.ts'));
    }

    private static function oku(string $yol): string
    {
        $icerik = file_get_contents(\dirname(__DIR__, 2).$yol);
        self::assertIsString($icerik, $yol);

        return $icerik;
    }

    /** @return array<string, string> ad → #rrggbb ya da #rrggbbaa (küçük harf) */
    private static function degiskenler(string $css, string $mod): array
    {
        // Açık: ilk :root bloğundan koyu bloğa kadar; koyu: :root[data-tema="koyu"]'dan sonrası.
        $kok = strpos($css, ':root');
        $koyu = strpos($css, ':root[data-tema="koyu"]');
        self::assertNotFalse($kok);
        self::assertNotFalse($koyu);
        $parca = 'koyu' === $mod ? substr($css, $koyu) : substr($css, $kok, $koyu - $kok);
        preg_match_all('/--([a-z0-9-]+):\s*(#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?)\b/', $parca, $m, \PREG_SET_ORDER);
        $d = [];
        foreach ($m as $eslesme) {
            $d[$eslesme[1]] ??= strtolower($eslesme[2]);
        }

        return $d;
    }

    private static function oran(string $a, string $b): float
    {
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $a);
        self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $b);
        $l = static function (string $h): float {
            $t = 0.0;
            foreach ([[1, 0.2126], [3, 0.7152], [5, 0.0722]] as [$i, $k]) {
                $c = hexdec(substr($h, $i, 2)) / 255;
                $t += $k * ($c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4);
            }

            return $t;
        };
        [$x, $y] = [$l($a), $l($b)];

        return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
    }
}
