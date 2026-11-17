<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * M5d: Meta Ads Insights'ın yerine geçer (SahteRevenueCat yönlendirir).
 * Sayfalar sırayla döner; istekler (adres + sorgu) kaydedilir.
 */
final class SahteMetaInsights
{
    /** @var list<array{int, array<mixed>|string}> durum kodu + gövde (dizi JSON'a çevrilir) */
    public static array $sayfalar = [];
    /** @var list<array<int|string, mixed>> */
    public static array $sorgular = [];
    public static ?string $agHatasi = null;

    public static function sifirla(): void
    {
        self::$sayfalar = [];
        self::$sorgular = [];
        self::$agHatasi = null;
    }

    /** @param array<string, mixed> $secenekler */
    public function __invoke(string $url, array $secenekler): ResponseInterface
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $sorgu);
        self::$sorgular[] = ['yol' => (string) parse_url($url, \PHP_URL_PATH)] + $sorgu;
        if (null !== self::$agHatasi) {
            return new MockResponse('', ['error' => self::$agHatasi]);
        }
        [$durum, $govde] = array_shift(self::$sayfalar) ?? [200, ['data' => []]];

        return new MockResponse(\is_string($govde) ? $govde : (string) json_encode($govde), ['http_code' => $durum]);
    }
}
