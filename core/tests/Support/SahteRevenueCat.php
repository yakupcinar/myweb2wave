<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Testte HTTP istemcisinin yerine geçer (framework.http_client.mock_response_factory):
 * giden her isteği kaydeder, ayarlanan durum koduyla yanıtlar. Gerçek
 * RevenueCat'e hiçbir şey gitmez.
 */
final class SahteRevenueCat
{
    /** @var list<array{method: string, url: string, headers: list<string>, body: string}> */
    public static array $istekler = [];
    public static int $durum = 200;
    /** Doluysa yanıt yerine ağ hatası (bağlantı kurulamadı). */
    public static ?string $agHatasi = null;

    public static function sifirla(): void
    {
        self::$istekler = [];
        self::$durum = 200;
        self::$agHatasi = null;
    }

    public static function sayi(): int
    {
        return \count(self::$istekler);
    }

    /** @return array{method: string, url: string, headers: list<string>, body: string} */
    public static function istek(int $i): array
    {
        return self::$istekler[$i] ?? ['method' => '', 'url' => '', 'headers' => [], 'body' => ''];
    }

    /** @return array<mixed> istek gövdesi (JSON) */
    public static function govde(int $i): array
    {
        $j = json_decode(self::istek($i)['body'], true);

        return \is_array($j) ? $j : [];
    }

    /** @param array<string, mixed> $secenekler */
    public function __invoke(string $method, string $url, array $secenekler = []): ResponseInterface
    {
        // Tek sahte fabrika (mock_response_factory); Claude istekleri ayrı kayıtta (A2).
        if (str_starts_with($url, 'https://api.anthropic.com/')) {
            return (new SahteClaude())($method, $url, $secenekler);
        }
        $basliklar = $secenekler['headers'] ?? [];
        $govde = $secenekler['body'] ?? '';
        self::$istekler[] = [
            'method' => $method,
            'url' => $url,
            'headers' => \is_array($basliklar) ? array_values(array_filter($basliklar, \is_string(...))) : [],
            'body' => \is_string($govde) ? $govde : '',
        ];

        return null !== self::$agHatasi
            ? new MockResponse('', ['error' => self::$agHatasi])
            : new MockResponse('{"code":0}', ['http_code' => self::$durum]);
    }
}
