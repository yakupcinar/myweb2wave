<?php

declare(strict_types=1);

namespace App\Command;

use App\Posta\AktivasyonDizisi;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** M8a2 (A22): 15 dakikalık cron — zamanı gelen dizi e-postalarını gönder. */
#[AsCommand('app:posta:dizi', 'E-posta dizileri: zamanı gelen adımları gönder (aktivasyon)')]
final readonly class PostaDizisiCommand
{
    public function __construct(private AktivasyonDizisi $aktivasyon)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->writeln(sprintf('Aktivasyon: %d e-posta', $this->aktivasyon->gonder()));

        return Command::SUCCESS;
    }
}
