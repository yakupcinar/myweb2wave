<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Testte Claude Messages API'nin yerine geçer (A2): SahteRevenueCat
 * api.anthropic.com isteklerini buraya yollar. Sıradaki hazır yanıtı API'nin
 * kendi JSON biçiminde (snake_case) döner; istek gövdelerini kaydeder — SDK'nın
 * gerçekte ne gönderdiği ölçülür. Ağa çıkılmaz.
 */
final class SahteClaude
{
    /** @var list<array{durum: int, govde: array<string, mixed>}> */
    public static array $yanitlar = [];
    /** @var list<array<mixed>> gönderilen istek gövdeleri */
    public static array $istekler = [];
    /** @var list<list<string>> istek başlıkları */
    public static array $basliklar = [];

    public static function sifirla(): void
    {
        self::$yanitlar = [];
        self::$istekler = [];
        self::$basliklar = [];
    }

    /**
     * @param list<array<string, mixed>> $bloklar
     */
    public static function sirala(array $bloklar, string $dur = 'end_turn', int $girdi = 1000, int $cikti = 200, int $okuma = 0, int $yazma = 0): void
    {
        self::$yanitlar[] = ['durum' => 200, 'govde' => [
            'id' => 'msg_sahte_'.\count(self::$yanitlar),
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => $bloklar,
            'stop_reason' => $dur,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => $girdi, 'output_tokens' => $cikti, 'cache_read_input_tokens' => $okuma, 'cache_creation_input_tokens' => $yazma],
        ]];
    }

    /** @param array<string, mixed> $govde */
    public static function hataSirala(int $durum, array $govde): void
    {
        self::$yanitlar[] = ['durum' => $durum, 'govde' => $govde];
    }

    /** @return array<string, mixed> */
    public static function metin(string $metin): array
    {
        return ['type' => 'text', 'text' => $metin];
    }

    /**
     * @param array<string, mixed> $girdi
     *
     * @return array<string, mixed>
     */
    public static function arac(string $id, string $ad, array $girdi): array
    {
        return ['type' => 'tool_use', 'id' => $id, 'name' => $ad, 'input' => (object) $girdi];
    }

    /** @param array<string, mixed> $secenekler */
    public function __invoke(string $method, string $url, array $secenekler = []): ResponseInterface
    {
        $govde = $secenekler['body'] ?? '';
        $istek = json_decode(\is_string($govde) ? $govde : '', true);
        self::$istekler[] = \is_array($istek) ? $istek : [];
        $b = $secenekler['headers'] ?? [];
        self::$basliklar[] = \is_array($b) ? array_values(array_filter($b, \is_string(...))) : [];
        $yanit = array_shift(self::$yanitlar) ?? ['durum' => 500, 'govde' => ['type' => 'error', 'error' => ['type' => 'api_error', 'message' => 'SahteClaude: sırada yanıt yok']]];

        return new MockResponse(
            json_encode($yanit['govde'], \JSON_THROW_ON_ERROR),
            ['http_code' => $yanit['durum'], 'response_headers' => ['content-type' => 'application/json']],
        );
    }
}
