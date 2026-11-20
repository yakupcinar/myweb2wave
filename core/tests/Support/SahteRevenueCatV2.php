<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;

/** G2: RevenueCat REST v2 abonelik listesi — müşteri kimliğine göre ayarlanır. */
final class SahteRevenueCatV2
{
    /** @var array<string, list<array<string, mixed>>> müşteri → abonelikler; listede olmayan müşteri 404 */
    public static array $abonelikler = [];
    public static int $durum = 200;
    /** @var list<array{url: string, yetki: string}> */
    public static array $istekler = [];

    public static function sifirla(): void
    {
        self::$abonelikler = [];
        self::$durum = 200;
        self::$istekler = [];
    }

    /** @param array<string, mixed> $secenekler */
    public static function yanit(string $url, array $secenekler): MockResponse
    {
        $yetki = '';
        foreach (\is_array($secenekler['headers'] ?? null) ? $secenekler['headers'] : [] as $b) {
            if (\is_string($b) && str_starts_with(strtolower($b), 'authorization:')) {
                $yetki = trim(substr($b, 14));
            }
        }
        self::$istekler[] = ['url' => $url, 'yetki' => $yetki];
        if (200 !== self::$durum) {
            return new MockResponse('{"type":"server_error"}', ['http_code' => self::$durum]);
        }
        $musteri = 1 === preg_match('#/customers/([^/]+)/subscriptions#', $url, $m) ? rawurldecode($m[1]) : '';

        return isset(self::$abonelikler[$musteri])
            ? new MockResponse((string) json_encode(['object' => 'list', 'items' => self::$abonelikler[$musteri]]))
            : new MockResponse('{"type":"resource_missing"}', ['http_code' => 404]);
    }
}
