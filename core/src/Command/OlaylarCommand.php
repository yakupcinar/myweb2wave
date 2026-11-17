<?php

declare(strict_types=1);

namespace App\Command;

use App\Olay\Olaylar;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * T4: olay deposunu okur — ziyaretin zaman çizelgesi JSON. Ölçüm ve hata
 * ayıklama için. Örnek: bin/console app:olaylar <ziyaret-uuid>.
 */
#[AsCommand('app:olaylar', 'Olay deposu: <ziyaret> · --sozluk')]
final readonly class OlaylarCommand
{
    public function __construct(private Olaylar $olaylar)
    {
    }

    public function __invoke(
        OutputInterface $cikti,
        #[Argument('ziyaret kimliği')] string $ziyaret = '',
        #[Option('olay sözlüğünü yaz')] bool $sozluk = false,
    ): int {
        if ($sozluk) {
            $cikti->writeln(self::json(array_map(static fn (array $t): array => ['veri' => $t[0], 'aciklama' => $t[1]], Olaylar::SOZLUK)));

            return Command::SUCCESS;
        }
        if (1 !== preg_match('/^[0-9a-f-]{36}$/', $ziyaret)) {
            $cikti->writeln(self::json(['hatalar' => ['kimlik UUID olmalı']]));

            return Command::INVALID;
        }
        $cikti->writeln(self::json($this->olaylar->ziyaretin($ziyaret)));

        return Command::SUCCESS;
    }

    private static function json(mixed $deger): string
    {
        return json_encode($deger, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }
}
