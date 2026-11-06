<?php

declare(strict_types=1);

namespace App\Huni\Simge;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * V3a: emoji simgesinin cihazdan bağımsız görseli — Microsoft Fluent Emoji
 * (MIT, lisans ölçüldü; THIRD_PARTY bildirimi `config/huni/fluent-simgeler.json`
 * yanında). Depoda yalnız DİZİN var (emoji → dosya yolu, sabit commit); görsel
 * ilk istekte o commit'ten alınır, küçültülür, ortamın önbellek dizininde saklanır (cache:clear siler, yeniden alınır).
 * Alınamazsa null — huni cihaz emojisine döner.
 *
 * Stiller: renkli = 3B PNG → 128 px WebP (özgün ~35 KB, ölçüldü); tekrenk =
 * yüksek kontrast SVG (tek renk; sayfada maske, ana renkte).
 */
final readonly class Simgeler
{
    public const STILLER = ['renkli' => 'webp', 'tekrenk' => 'svg'];
    public const KOD = '/^[0-9a-f]{2,6}(-[0-9a-f]{2,6}){0,9}$/';
    private const KAYNAK = 'https://raw.githubusercontent.com/microsoft/fluentui-emoji/';
    private const BOYUT = 128;

    public function __construct(
        private HttpClientInterface $http,
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/config/huni/fluent-simgeler.json')]
        private string $dizinDosyasi,
        #[Autowire('%kernel.cache_dir%/simge')]
        private string $onbellek,
    ) {
    }

    /** Emojinin dizin anahtarı: kod noktaları, FE0F (biçim seçici) atılır — huni-bilesenleri/simge.ts ile aynı kural. */
    public static function kod(string $emoji): string
    {
        $noktalar = [];
        foreach (mb_str_split($emoji) as $karakter) {
            $nokta = mb_ord($karakter);
            if (0xFE0F !== $nokta) {
                $noktalar[] = dechex($nokta);
            }
        }

        return implode('-', $noktalar);
    }

    /** @return array{icerik: string, tur: string}|null */
    public function getir(string $stil, string $kod): ?array
    {
        $uzanti = self::STILLER[$stil] ?? null;
        if (null === $uzanti || 1 !== preg_match(self::KOD, $kod)) {
            return null;
        }
        $tur = 'webp' === $uzanti ? 'image/webp' : 'image/svg+xml';
        $yol = "{$this->onbellek}/{$stil}/{$kod}.{$uzanti}";
        if (is_file($yol)) {
            return ['icerik' => (string) file_get_contents($yol), 'tur' => $tur];
        }
        $dizin = $this->dizin();
        $kaynak = $dizin['simgeler'][$kod][$stil] ?? null;
        if (null === $kaynak) {
            return null;
        }
        try {
            $ham = $this->http->request('GET', self::KAYNAK.$dizin['sha'].'/'.str_replace('%2F', '/', rawurlencode($kaynak)), ['timeout' => 5])->getContent();
        } catch (\Throwable $e) {
            $this->logger->warning('Fluent simgesi alınamadı', ['kod' => $kod, 'stil' => $stil, 'exception' => $e]);

            return null;
        }
        $icerik = 'webp' === $uzanti ? self::kucult($ham) : self::svgTemizMi($ham);
        if (null === $icerik) {
            $this->logger->warning('Fluent simgesi işlenemedi', ['kod' => $kod, 'stil' => $stil]);

            return null;
        }
        (new Filesystem())->dumpFile($yol, $icerik);

        return ['icerik' => $icerik, 'tur' => $tur];
    }

    /** @return array{sha: string, simgeler: array<string, array<string, string>>} */
    private function dizin(): array
    {
        /** @var array{sha: string, simgeler: array<string, array<string, string>>} $d */
        $d = json_decode((string) file_get_contents($this->dizinDosyasi), true, flags: \JSON_THROW_ON_ERROR);

        return $d;
    }

    private static function kucult(string $png): ?string
    {
        $kaynak = @imagecreatefromstring($png);
        if (false === $kaynak) {
            return null;
        }
        $hedef = imagecreatetruecolor(self::BOYUT, self::BOYUT);
        imagealphablending($hedef, false);
        imagesavealpha($hedef, true);
        imagefill($hedef, 0, 0, (int) imagecolorallocatealpha($hedef, 0, 0, 0, 127));
        imagecopyresampled($hedef, $kaynak, 0, 0, 0, 0, self::BOYUT, self::BOYUT, imagesx($kaynak), imagesy($kaynak));
        ob_start();
        imagewebp($hedef, null, 82);

        return (string) ob_get_clean() ?: null;
    }

    /** Sabit commit'ten gelse de: betik, olay özniteliği, dış başvuru taşıyan SVG sunulmaz. */
    private static function svgTemizMi(string $svg): ?string
    {
        return str_starts_with(ltrim($svg), '<svg') && 0 === preg_match('/<script|<foreignObject|\son[a-z]+\s*=|(?:xlink:)?href\s*=\s*["\']\s*(?!#)/i', $svg)
            ? $svg
            : null;
    }
}
