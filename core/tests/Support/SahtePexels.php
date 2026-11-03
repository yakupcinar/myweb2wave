<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * V3b: testte Pexels API'sinin ve görsel sunucusunun yerine. Yanıt biçimi
 * Pexels belgesindeki foto nesnesi (id, width, height, url, photographer,
 * photographer_url, alt, src.*). İstenen adres ve başlıklar kaydedilir.
 */
final class SahtePexels
{
    /** @var list<array{url: string, headers: list<string>}> */
    public static array $istekler = [];
    public static int $durum = 200;
    /** Doluysa foto nesnesinin bu alanları değişir (ör. yabancı görsel adresi). */
    /** @var array<string, mixed> */
    public static array $fotoEki = [];

    public static function sifirla(): void
    {
        self::$istekler = [];
        self::$durum = 200;
        self::$fotoEki = [];
    }

    /** @return array<string, mixed> */
    public static function foto(int $id): array
    {
        /** @var array<string, mixed> $foto */
        $foto = array_replace_recursive([
            'id' => $id, 'width' => 4000, 'height' => 6000,
            'url' => "https://www.pexels.com/photo/uyuyan-kadin-{$id}/",
            'photographer' => 'Ayşe Fotoğrafçı', 'photographer_url' => 'https://www.pexels.com/@ayse',
            'alt' => 'Yatakta uyuyan kadın',
            'src' => ['medium' => "https://images.pexels.com/photos/{$id}/k.jpeg?h=350", 'large2x' => "https://images.pexels.com/photos/{$id}/b.jpeg?w=940&dpr=2"],
        ], self::$fotoEki);

        return $foto;
    }

    /** @param array<string, mixed> $secenekler */
    public function __invoke(string $url, array $secenekler): ResponseInterface
    {
        /** @var list<string> $basliklar */
        $basliklar = \is_array($secenekler['normalized_headers'] ?? null) ? ($secenekler['normalized_headers']['authorization'] ?? []) : [];
        self::$istekler[] = ['url' => $url, 'headers' => $basliklar];
        if (self::$durum >= 400) {
            return new MockResponse('{"error":"x"}', ['http_code' => self::$durum]);
        }
        if (str_starts_with($url, 'https://images.pexels.com/')) {
            $g = imagecreatetruecolor(1880, 2820);
            imagefill($g, 0, 0, (int) imagecolorallocate($g, 20, 30, 70));
            ob_start();
            imagejpeg($g, null, 80);

            return new MockResponse((string) ob_get_clean());
        }
        if (1 === preg_match('#/v1/photos/(\d+)#', $url, $m)) {
            return new MockResponse((string) json_encode(self::foto((int) $m[1])));
        }

        return new MockResponse((string) json_encode(['photos' => [self::foto(101), self::foto(102)], 'total_results' => 2]));
    }
}
