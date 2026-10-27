<?php

declare(strict_types=1);

namespace App\Command;

use App\Huni\Arac\HuniSemasi;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Sözleşmeden AI araç şemasını üretir (A0). Sözleşme değişince
 * `contract npm run uret` ile birlikte koşulur; bin/kontrol `--kontrol` ile
 * dosyanın güncel olduğunu ölçer (TypeScript tipleriyle aynı düzen).
 */
#[AsCommand('app:huni-semasi', 'Sözleşmeden AI araç şemasını üret (config/huni/tanim-semasi.json)')]
final readonly class HuniSemasiCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/config/huni/tanim-semasi.json')]
        private string $hedef,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('yazma; dosya sözleşmeyle aynı değilse başarısız')] bool $kontrol = false,
        #[Option('sözleşme dosyası')] string $sozlesme = '/contract/openapi.yaml',
    ): int {
        $openapi = Yaml::parseFile($sozlesme);
        if (!\is_array($openapi)) {
            $io->error("{$sozlesme} okunamadı");

            return Command::FAILURE;
        }
        /** @var array<string, mixed> $openapi */
        $metin = json_encode(HuniSemasi::uret($openapi), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
        if ($kontrol) {
            if (!is_file($this->hedef) || file_get_contents($this->hedef) !== $metin) {
                $io->error('config/huni/tanim-semasi.json sözleşmeyle aynı değil: bin/console app:huni-semasi');

                return Command::FAILURE;
            }
            $io->writeln('güncel');

            return Command::SUCCESS;
        }
        file_put_contents($this->hedef, $metin);
        $io->writeln($this->hedef);

        return Command::SUCCESS;
    }
}
