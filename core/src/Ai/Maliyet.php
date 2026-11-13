<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * İstek maliyeti, mikrodolar (1 $ = 1.000.000). Fiyatlar Anthropic birinci
 * taraf API, claude-opus-5-5, milyon jeton başına: girdi 4 $, çıktı 20 $,
 * önbellekten okuma 0,20 $, önbelleğe yazma (5 dk) girdinin 1,25 katı = 5 $.
 * Kaynak: claude-api rehberi model tablosu (önbellek 2026-10-06). Model
 * değişirse bu tablo da değişir — maliyet kaydı yanlış olur, hata vermez.
 */
final class Maliyet
{
    /** @var array<string, array{girdi: float, cikti: float, okuma: float, yazma: float}> $/MTok */
    private const FIYAT = [
        'claude-opus-5-5' => ['girdi' => 4.0, 'cikti' => 20.0, 'okuma' => 0.20, 'yazma' => 5.0],
    ];

    public static function mikrodolar(string $model, int $girdi, int $cikti, int $okuma, int $yazma): ?int
    {
        $f = self::FIYAT[$model] ?? null;
        if (null === $f) {
            return null;
        }

        // $/MTok × jeton = mikrodolar (1e6'lar sadeleşir).
        return (int) round($girdi * $f['girdi'] + $cikti * $f['cikti'] + $okuma * $f['okuma'] + $yazma * $f['yazma']);
    }
}
