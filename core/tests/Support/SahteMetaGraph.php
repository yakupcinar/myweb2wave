<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A30: Meta Graph nesne okuması ("Bağlantıyı dene": Pixel, reklam hesabı) —
 * SahteRevenueCat GET isteklerini buraya yönlendirir. Yol → durum + gövde;
 * istekler (yol + sorgu) kaydedilir.
 */
final class SahteMetaGraph
{
    /** @var array<string, array{int, array<mixed>}> */
    public static array $yanitlar = [];
    /** @var list<array<int|string, mixed>> */
    public static array $sorgular = [];

    public static function sifirla(): void
    {
        self::$yanitlar = [];
        self::$sorgular = [];
    }

    public function __invoke(string $url): ResponseInterface
    {
        $yol = (string) parse_url($url, \PHP_URL_PATH);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $sorgu);
        self::$sorgular[] = ['yol' => $yol] + $sorgu;
        [$durum, $govde] = self::$yanitlar[$yol] ?? [404, ['error' => ['message' => 'Unsupported get request.', 'code' => 100]]];

        return new MockResponse((string) json_encode($govde), ['http_code' => $durum]);
    }
}
