<?php

declare(strict_types=1);

namespace App\Command;

use App\Atif\DonusumAktarimi;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:reklam-donusumleri:gonder', 'Bekleyen reklam dönüşüm olaylarını gönder')]
final readonly class ReklamDonusumleriGonderCommand
{
    public function __construct(private DonusumAktarimi $aktarim)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $sonuc = $this->aktarim->gonder();
        $io->writeln(sprintf('Gönderilen: %d; başarısız: %d', $sonuc['gonderilen'], $sonuc['basarisiz']));

        return Command::SUCCESS;
    }
}
