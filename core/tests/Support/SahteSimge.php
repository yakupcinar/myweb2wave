<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/** V3a: testte GitHub'daki Fluent dosyalarının yerine — istenen adresler kaydedilir. */
final class SahteSimge
{
    /** @var list<string> */
    public static array $adresler = [];
    public static string $govde = '';
    public static int $durum = 200;
    public static ?string $agHatasi = null;

    public static function sifirla(): void
    {
        self::$adresler = [];
        self::$govde = '';
        self::$durum = 200;
        self::$agHatasi = null;
    }

    /** İstek sayısı — dizi iddiadan sonra daraltılmış görünmesin (PHPStan). */
    public static function sayi(): int
    {
        return \count(self::$adresler);
    }

    public function __invoke(string $url): ResponseInterface
    {
        self::$adresler[] = $url;

        return null !== self::$agHatasi
            ? new MockResponse('', ['error' => self::$agHatasi])
            : new MockResponse(self::$govde, ['http_code' => self::$durum]);
    }
}
