<?php

declare(strict_types=1);

namespace App\Command;

use App\Posta\AktivasyonDizisi;
use App\Posta\KurtarmaDizisi;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** M8a2 / M8b (A22): 15 dakikalık cron — zamanı gelen dizi e-postalarını gönder. */
#[AsCommand('app:posta:dizi', 'E-posta dizileri: zamanı gelen adımları gönder (aktivasyon, kurtarma)')]
final readonly class PostaDizisiCommand
{
    public function __construct(private AktivasyonDizisi $aktivasyon, private KurtarmaDizisi $kurtarma)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->writeln(sprintf('Aktivasyon: %d e-posta', $this->aktivasyon->gonder()));
        $io->writeln(sprintf('Kurtarma: %d e-posta', $this->kurtarma->gonder()));

        return Command::SUCCESS;
    }
}
