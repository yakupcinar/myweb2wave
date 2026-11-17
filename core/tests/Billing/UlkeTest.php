<?php

declare(strict_types=1);

namespace App\Tests\Billing;

use App\Billing\ParaBirimi;
use App\Billing\UlkeBulucu;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * M1c: ülke → para birimi. Beklenenler elle (ISO 4217; tuzak §3): ülkenin
 * ilk resmî para birimi destekleniyorsa o, değilse null (planın ana birimi).
 */
final class UlkeTest extends TestCase
{
    public function testUlkeninParaBirimi(): void
    {
        foreach (['TR' => 'TRY', 'DE' => 'EUR', 'BG' => 'EUR', 'HR' => 'EUR', 'GB' => 'GBP', 'JE' => 'GBP', 'US' => 'USD', 'EC' => 'USD', 'PA' => null, 'JP' => null, 'CH' => null] as $ulke => $para) {
            self::assertSame($para, ParaBirimi::ulkeninki($ulke), $ulke);
        }
        self::assertNull(ParaBirimi::ulkeninki(null));
    }

    /** Bir ülke iki para birimine eşlenmez (ilk eşleşme sessizce kazanırdı). */
    public function testUlkeTekParaBiriminde(): void
    {
        $hepsi = array_merge(...array_values(ParaBirimi::ULKELER));

        self::assertCount(\count($hepsi), array_unique($hepsi));
    }

    /** Test parametresi IP'den önce; biçimsizse yok sayılır. Veri dosyası yoksa null (ana para birimi). */
    public function testTestParametresiVeDosyasizIp(): void
    {
        $b = new UlkeBulucu('/olmayan/dosya.mmdb', new NullLogger());

        self::assertSame('DE', $b->ulke('8.8.8.8', 'DE'));
        self::assertNull($b->ulke('8.8.8.8', 'de'));
        self::assertNull($b->ulke('8.8.8.8', 'DEU'));
        self::assertNull($b->ulke('8.8.8.8', ['DE']));
        self::assertNull($b->ulke('bozuk', null));
        self::assertNull($b->ulke(null, null));
    }
}
