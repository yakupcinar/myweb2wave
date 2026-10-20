<?php

declare(strict_types=1);

namespace App\Huni;

/**
 * Huni başına görünüm (A8 / E2.2): üç renk, yazı tipi, köşe. Yazı tipleri
 * yalnız CİHAZIN kendi yazı tipleri — dışarıdan font yüklemek ziyaretçinin
 * IP'sini üçüncü tarafa yollar (KVKK/çerez onayı, Ç12). Okunurluk
 * yayından önce ölçülür: metin/arka plan kontrastı WCAG AA (4,5:1).
 * Düğme yazısı huni sayfasında siyah/beyazdan kontrastı yüksek olanı seçilir.
 */
final class Tema
{
    public const VARSAYILAN = [
        'anaRenk' => '#2563eb', 'arkaPlan' => '#ffffff', 'metinRengi' => '#111827',
        'yaziTipi' => 'sistem', 'kose' => 10,
    ];

    /** Seçilebilir yazı tipleri — huni sayfası bunları cihaz yazı tipi yığınına çevirir. */
    public const YAZI_TIPLERI = ['sistem', 'serif', 'yuvarlak', 'mono'];

    public const AZAMI_KOSE = 32;

    /** WCAG AA, normal metin. */
    public const ASGARI_KONTRAST = 4.5;

    /** @return list<string> */
    public static function hatalar(mixed $tema): array
    {
        if (!\is_array($tema)) {
            return ['tema bir nesne olmalı'];
        }
        $beklenen = array_keys(self::VARSAYILAN);
        $var = array_map(strval(...), array_keys($tema));
        sort($var);
        sort($beklenen);
        if ($var !== $beklenen) {
            return ['tema alanları tam olarak '.implode(', ', array_keys(self::VARSAYILAN)).' olmalı'];
        }
        $h = [];
        foreach (['anaRenk', 'arkaPlan', 'metinRengi'] as $alan) {
            if (!\is_string($tema[$alan]) || 1 !== preg_match('/^#[0-9a-f]{6}$/', $tema[$alan])) {
                $h[] = "tema {$alan}: #rrggbb biçiminde (küçük harf) renk olmalı";
            }
        }
        if (!\in_array($tema['yaziTipi'], self::YAZI_TIPLERI, true)) {
            $h[] = 'tema yaziTipi: '.implode(', ', self::YAZI_TIPLERI).' olmalı';
        }
        if (!\is_int($tema['kose']) || $tema['kose'] < 0 || $tema['kose'] > self::AZAMI_KOSE) {
            $h[] = 'tema kose: 0–'.self::AZAMI_KOSE.' tam sayı olmalı';
        }
        if ([] === $h && \is_string($tema['metinRengi']) && \is_string($tema['arkaPlan'])) {
            $oran = self::kontrast($tema['metinRengi'], $tema['arkaPlan']);
            if ($oran < self::ASGARI_KONTRAST) {
                $h[] = \sprintf('tema: metin arka planda okunmuyor (kontrast %.2f:1, en az %.1f:1)', $oran, self::ASGARI_KONTRAST);
            }
        }

        return $h;
    }

    /** WCAG kontrast oranı (1–21). */
    public static function kontrast(string $a, string $b): float
    {
        [$la, $lb] = [self::parlaklik($a), self::parlaklik($b)];

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** WCAG göreli parlaklık; $hex #rrggbb. */
    private static function parlaklik(string $hex): float
    {
        $kanal = static function (string $iki): float {
            $c = hexdec($iki) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $kanal(substr($hex, 1, 2)) + 0.7152 * $kanal(substr($hex, 3, 2)) + 0.0722 * $kanal(substr($hex, 5, 2));
    }
}
