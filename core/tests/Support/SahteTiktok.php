<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * A31: TikTok Marketing API okumaları ("Bağlantıyı dene": reklamveren
 * bilgisi; harcama raporu) — SahteRevenueCat business-api GET isteklerini
 * buraya yönlendirir. Yol → durum + gövde; istekler (yol + sorgu + başlıklar)
 * kaydedilir. TikTok gövdesi her zaman {code, message, data}.
 */
final class SahteTiktok
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

    /** @param array<string, mixed> $secenekler */
    public function __invoke(string $url, array $secenekler): ResponseInterface
    {
        $yol = (string) parse_url($url, \PHP_URL_PATH);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $sorgu);
        $basliklar = $secenekler['headers'] ?? [];
        self::$sorgular[] = ['yol' => $yol, 'basliklar' => \is_array($basliklar) ? array_values($basliklar) : []] + $sorgu;
        [$durum, $govde] = self::$yanitlar[$yol] ?? [200, ['code' => 40002, 'message' => 'No permission to operate advertiser']];

        return new MockResponse((string) json_encode($govde), ['http_code' => $durum]);
    }
}
