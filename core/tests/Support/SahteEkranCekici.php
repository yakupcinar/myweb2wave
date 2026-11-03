<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Huni\Ekran\EkranCekici;
use App\Huni\Ekran\EkranCekimi;

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

    /** @var array{bosAlt: float, kaydirma: bool, dugmeGorunur: bool|null, yetimSatirlar: list<string>, kucukHedefler: int, tasanlar: int} */
    public static array $olcum = self::TEMIZ;

    public const TEMIZ = ['bosAlt' => 0.1, 'kaydirma' => false, 'dugmeGorunur' => true, 'yetimSatirlar' => [], 'kucukHedefler' => 0, 'tasanlar' => 0];

    public static function sifirla(): void
    {
        self::$adresler = [];
        self::$hata = null;
        self::$olcum = self::TEMIZ;
    }

    public function cek(string $adres): EkranCekimi
    {
        self::$adresler[] = $adres;
        if (null !== self::$hata) {
            throw new \RuntimeException(self::$hata);
        }

        return new EkranCekimi(self::BAYTLAR, self::$olcum);
    }
}
