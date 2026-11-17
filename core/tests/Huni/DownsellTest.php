<?php

declare(strict_types=1);

namespace App\Tests\Huni;

use App\Huni\Tanim;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M3a (A16): paywall downsell'i — plan katalogdan, başlık + düğme zorunlu,
 * metin isteğe bağlı; başka alan yok (fiyat/koşul pencereyi Stripe'tan yazar).
 */
final class DownsellTest extends TestCase
{
    private const PLANLAR = ['monthly', 'yearly'];

    public function testGecerli(): void
    {
        self::assertSame([], Tanim::hatalar(self::tanim(['plan' => 'yearly', 'baslik' => 'Bir saniye', 'dugme' => 'Yıllığı al']), self::PLANLAR));
        self::assertSame([], Tanim::hatalar(self::tanim(['plan' => 'monthly', 'baslik' => 'B', 'metin' => 'Açıklama', 'dugme' => 'D']), self::PLANLAR));
    }

    /** @return iterable<string, array{mixed, non-empty-string}> */
    public static function gecersizler(): iterable
    {
        yield 'düğme yok' => [['plan' => 'yearly', 'baslik' => 'B'], 'alanlar tam olarak plan, baslik, dugme'];
        yield 'fazla alan' => [['plan' => 'yearly', 'baslik' => 'B', 'dugme' => 'D', 'fiyat' => '9,99'], 'alanlar tam olarak'];
        yield 'nesne değil' => ['yearly', 'alanlar tam olarak'];
        yield 'bilinmeyen plan' => [['plan' => 'gold', 'baslik' => 'B', 'dugme' => 'D'], "downsell: plan 'gold' bu uygulamada yok (planlar: monthly, yearly)"];
        yield 'boş başlık' => [['plan' => 'yearly', 'baslik' => '  ', 'dugme' => 'D'], 'downsell baslik'];
        yield 'uzun metin' => [['plan' => 'yearly', 'baslik' => 'B', 'metin' => str_repeat('a', 1001), 'dugme' => 'D'], 'downsell metin'];
    }

    #[DataProvider('gecersizler')]
    public function testGecersiz(mixed $downsell, string $hata): void
    {
        self::assertStringContainsString($hata, implode(' | ', Tanim::hatalar(self::tanim($downsell), self::PLANLAR)));
    }

    /** @return array<string, mixed> */
    private static function tanim(mixed $downsell): array
    {
        return ['sayfaBasligi' => 'X', 'adimlar' => [
            ['id' => 'giris', 'tur' => 'ekran', 'baslik' => 'Merhaba', 'bloklar' => [], 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => 'Pro', 'metin' => '', 'dugme' => 'Al', 'plan' => 'monthly', 'downsell' => $downsell],
        ]];
    }
}
