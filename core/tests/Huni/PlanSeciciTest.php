<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Tanim;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M2a: paywall plan seçici. Karar: tek plan (`plan`) ya da 2–3 planlık
 * seçici (`planlar`, varsayılan seçili `plan` içinde), `onerilen` yalnız
 * seçicide; plan hatası uygulamanın planlarını sayar.
 */
final class PlanSeciciTest extends TestCase
{
    private const PLANLAR = ['monthly', 'yearly', 'weekly', 'pro'];

    public function testTekPlanVeSeciciGecerli(): void
    {
        self::assertSame([], Tanim::hatalar(self::tanim([]), self::PLANLAR));
        self::assertSame([], Tanim::hatalar(self::tanim(['planlar' => ['monthly', 'yearly']]), self::PLANLAR));
        self::assertSame([], Tanim::hatalar(self::tanim(['planlar' => ['weekly', 'monthly', 'yearly'], 'onerilen' => 'yearly']), self::PLANLAR));
    }

    /** @return iterable<string, array{array<string, mixed>, non-empty-string}> */
    public static function gecersizler(): iterable
    {
        yield 'tek planlı seçici' => [['planlar' => ['monthly']], 'planlar 2–3 farklı plan'];
        yield 'dört plan' => [['planlar' => ['monthly', 'yearly', 'weekly', 'pro']], 'planlar 2–3 farklı plan'];
        yield 'tekrar' => [['planlar' => ['monthly', 'monthly']], 'planlar 2–3 farklı plan'];
        yield 'liste değil' => [['planlar' => ['a' => 'monthly', 'b' => 'yearly']], 'planlar 2–3 farklı plan'];
        yield 'varsayılan dışarıda' => [['plan' => 'weekly', 'planlar' => ['monthly', 'yearly']], 'planlar içinde olmalı'];
        yield 'seçicide bilinmeyen' => [['planlar' => ['monthly', 'gold']], "plan 'gold' bu uygulamada yok (planlar: monthly, yearly, weekly, pro)"];
        yield 'seçicide metin değil' => [['planlar' => ['monthly', 5]], "plan '?' bu uygulamada yok"];
        yield 'önerilen seçicisiz' => [['onerilen' => 'monthly'], 'onerilen planlar içinden'];
        yield 'önerilen dışarıda' => [['planlar' => ['monthly', 'yearly'], 'onerilen' => 'weekly'], 'onerilen planlar içinden'];
    }

    /** @param array<string, mixed> $ek */
    #[DataProvider('gecersizler')]
    public function testGecersiz(array $ek, string $hata): void
    {
        self::assertStringContainsString($hata, implode(' | ', Tanim::hatalar(self::tanim($ek), self::PLANLAR)));
    }

    public function testPlanYoksaPaneleYonlendirir(): void
    {
        self::assertContains("adım 2: plan 'monthly' bu uygulamada yok (hiç plan yok — panel → Planlar)", Tanim::hatalar(self::tanim([]), []));
    }

    /**
     * @param array<string, mixed> $ek
     *
     * @return array<string, mixed>
     */
    private static function tanim(array $ek): array
    {
        return ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [], 'dugme' => 'Başla'],
            array_merge(['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Abone ol', 'plan' => 'monthly'], $ek),
        ]];
    }
}
