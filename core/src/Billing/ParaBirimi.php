<?php

declare(strict_types=1);

namespace App\Billing;

/** Stripe tutarı en küçük birimde (sent); bu para birimlerinde ondalık yok. */
final class ParaBirimi
{
    private const SIFIR_ONDALIKLI = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public static function anaBirim(int $tutar, string $para): int|float
    {
        return \in_array(strtoupper($para), self::SIFIR_ONDALIKLI, true) ? $tutar : $tutar / 100;
    }

    /** M1a: panelde yazılan ana birim (9.99) → Stripe'ın küçük birimi (999); kayan nokta yuvarlanır. */
    public static function kucukBirim(int|float $tutar, string $para): int
    {
        return (int) round(\in_array(strtoupper($para), self::SIFIR_ONDALIKLI, true) ? $tutar : $tutar * 100);
    }
}
