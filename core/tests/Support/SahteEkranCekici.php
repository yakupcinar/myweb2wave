<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Huni\Ekran\EkranCekici;

/**
 * Testte başsız tarayıcının yerine geçer (Ç72): istenen adresleri kaydeder,
 * sabit baytlar döner ya da hazır hatayı fırlatır. Gerçek Chrome yolu
 * çalışan sistemde ölçülür (ai-ekran-goruntusu.md).
 */
final class SahteEkranCekici implements EkranCekici
{
    public const BAYTLAR = "\xFF\xD8\xFFsahte-jpeg";

    /** @var list<string> */
    public static array $adresler = [];
    public static ?string $hata = null;

    public static function sifirla(): void
    {
        self::$adresler = [];
        self::$hata = null;
    }

    public function cek(string $adres): string
    {
        self::$adresler[] = $adres;
        if (null !== self::$hata) {
            throw new \RuntimeException(self::$hata);
        }

        return self::BAYTLAR;
    }
}
