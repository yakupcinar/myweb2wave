<?php

declare(strict_types=1);

namespace App\Billing;

/** Stripe tutarı en küçük birimde (sent); bu para birimlerinde ondalık yok. */
final class ParaBirimi
{
    private const SIFIR_ONDALIKLI = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    /**
     * M1c: desteklenen para birimi → onu RESMÎ ve ilk para birimi olarak
     * kullanan ülkeler (ISO 3166-1; ISO 4217 listesinde ilk sıradaki — web2wave
     * kuralı: birden çok para birimli ülkede ilki). Panama (PAB+USD) gibi ilki
     * desteklenmeyen ülkeler yok → planın ana para birimi. Bulgaristan
     * 2026-01-01'den EUR.
     */
    public const ULKELER = [
        'EUR' => ['AD', 'AT', 'AX', 'BE', 'BG', 'BL', 'CY', 'DE', 'EE', 'ES', 'FI', 'FR', 'GF', 'GP', 'GR', 'HR', 'IE', 'IT', 'LT', 'LU', 'LV', 'MC', 'ME', 'MF', 'MQ', 'MT', 'NL', 'PM', 'PT', 'RE', 'SI', 'SK', 'SM', 'TF', 'VA', 'XK', 'YT'],
        'GBP' => ['GB', 'GG', 'IM', 'JE'],
        'TRY' => ['TR'],
        'USD' => ['AS', 'BQ', 'EC', 'FM', 'GU', 'IO', 'MH', 'MP', 'PR', 'PW', 'SV', 'TC', 'TL', 'UM', 'US', 'VG', 'VI'],
    ];

    /** M1c: ülkenin desteklenen para birimi; yoksa null (plan ana para birimiyle satar). */
    public static function ulkeninki(?string $ulke): ?string
    {
        foreach (self::ULKELER as $para => $ulkeler) {
            if (\in_array($ulke, $ulkeler, true)) {
                return $para;
            }
        }

        return null;
    }

    public static function anaBirim(int $tutar, string $para): int|float
    {
        return \in_array(strtoupper($para), self::SIFIR_ONDALIKLI, true) ? $tutar : $tutar / 100;
    }

    /**
     * M5d: ondalık metin ("5339.5", Meta Insights) → küçük birim, kayan noktasız;
     * fazla basamak yarım yukarı yuvarlanır. Biçim dışıysa null. kucukBirim()
     * burada yetmez: 1.005 × 100 = 100.4999… → 100 (PHP 8.4 round ön yuvarlamaz).
     */
    public static function metindenKucuk(string $tutar, string $para): ?int
    {
        if (1 !== preg_match('/^(\d{1,15})(?:\.(\d{1,9}))?$/', $tutar, $m)) {
            return null;
        }
        $basamak = \in_array(strtoupper($para), self::SIFIR_ONDALIKLI, true) ? 0 : 2;
        $kesir = str_pad($m[2] ?? '', $basamak + 1, '0');

        return (int) $m[1] * 10 ** $basamak + (int) substr($kesir, 0, $basamak) + ((int) $kesir[$basamak] >= 5 ? 1 : 0);
    }

    /** M1a: panelde yazılan ana birim (9.99) → Stripe'ın küçük birimi (999); kayan nokta yuvarlanır. */
    public static function kucukBirim(int|float $tutar, string $para): int
    {
        return (int) round(\in_array(strtoupper($para), self::SIFIR_ONDALIKLI, true) ? $tutar : $tutar * 100);
    }
}
