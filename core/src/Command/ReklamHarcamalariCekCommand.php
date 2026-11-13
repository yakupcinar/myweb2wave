<?php

declare(strict_types=1);

namespace App\Command;

use App\Atif\MetaHarcamalari;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** M5d: günlük cron — Meta kampanya harcaması, son N gün (yeniden yazılır). */
#[AsCommand('app:reklam-harcamalari:cek', 'Meta kampanya harcamasını çek (son N gün)')]
final readonly class ReklamHarcamalariCekCommand
{
    public function __construct(private MetaHarcamalari $meta)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Kaç gün (bugün dahil, 1–90)')] int $gun = 7): int
    {
        if ($gun < 1 || $gun > 90) {
            $io->error('--gun 1 ile 90 arasında olmalı');

            return Command::INVALID;
        }
        $sonuc = $this->meta->cek($gun);
        if (\is_string($sonuc)) {
            $io->error($sonuc);

            return Command::FAILURE;
        }
        $io->writeln(sprintf('Yazılan: %d; atlanan (biçim dışı): %d', $sonuc['satir'], $sonuc['atlanan']));

        return Command::SUCCESS;
    }
}
