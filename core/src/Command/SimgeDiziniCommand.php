<?php

declare(strict_types=1);

namespace App\Command;

use App\Huni\Simge\Simgeler;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * V3a: Fluent Emoji dizinini SABİT bir commit'ten üretir (emoji anahtarı →
 * 3B PNG ve yüksek kontrast SVG yolu). Elle koşulur, çıktı depoya girer;
 * commit değişmedikçe görseller değişmez (önbellek kalıcı).
 */
#[AsCommand('app:simge-dizini', 'Fluent Emoji dizinini sabit commit\'ten üret')]
final readonly class SimgeDiziniCommand
{
    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%kernel.project_dir%/config/huni/fluent-simgeler.json')]
        private string $dizinDosyasi,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('microsoft/fluentui-emoji commit SHA')] string $sha): int
    {
        if (1 !== preg_match('/^[0-9a-f]{40}$/', $sha)) {
            $io->error('40 haneli commit SHA verin');

            return Command::FAILURE;
        }
        /** @var array{tree: list<array{path: string}>, truncated: bool} $agac */
        $agac = $this->http->request('GET', "https://api.github.com/repos/microsoft/fluentui-emoji/git/trees/{$sha}?recursive=1")->toArray();
        if ($agac['truncated']) {
            $io->error('ağaç kesik geldi');

            return Command::FAILURE;
        }
        $dosyalar = [];
        foreach ($agac['tree'] as $d) {
            if (1 === preg_match('#^assets/([^/]+)/(?:Default/)?(3D|High Contrast)/[^/]+\.(png|svg)$#', $d['path'], $m)) {
                $dosyalar[$m[1]]['3D' === $m[2] ? 'renkli' : 'tekrenk'] = $d['path'];
            }
        }
        $yanitlar = [];
        foreach (array_keys($dosyalar) as $klasor) {
            $yanitlar[$klasor] = $this->http->request('GET', "https://raw.githubusercontent.com/microsoft/fluentui-emoji/{$sha}/assets/".rawurlencode((string) $klasor).'/metadata.json');
        }
        $simgeler = [];
        foreach ($yanitlar as $klasor => $yanit) {
            $meta = $yanit->toArray(false);
            $glif = $meta['glyph'] ?? null;
            if (\is_string($glif) && isset($dosyalar[$klasor]['renkli'], $dosyalar[$klasor]['tekrenk'])) {
                $simgeler[Simgeler::kod($glif)] = $dosyalar[$klasor];
            }
        }
        ksort($simgeler);
        (new Filesystem())->dumpFile($this->dizinDosyasi, json_encode(['sha' => $sha, 'simgeler' => $simgeler], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
        $io->writeln(\count($simgeler).' simge ('.\count($dosyalar).' klasör) → '.$this->dizinDosyasi);

        return Command::SUCCESS;
    }
}
