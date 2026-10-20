<?php

declare(strict_types=1);

namespace App\Command;

use App\RevenueCat\Aktarim;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RevenueCat giden kutusunda bekleyenleri yeniden dener (A9). Sunucuda
 * cron'la (ör. 5 dakikada bir) koşar; elle de çağrılabilir.
 */
#[AsCommand('app:revenuecat:gonder', 'RevenueCat giden kutusundaki bekleyenleri gönder')]
final readonly class RevenueCatGonderCommand
{
    public function __construct(
        private Aktarim $aktarim,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $sonuc = $this->aktarim->gonder(500);
        $io->writeln("gönderilen={$sonuc['gonderilen']} başarısız={$sonuc['basarisiz']}");

        return 0 === $sonuc['basarisiz'] ? Command::SUCCESS : Command::FAILURE;
    }
}
