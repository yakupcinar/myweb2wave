<?php

declare(strict_types=1);

namespace App\Command;

use App\Olay\GidenWebhook;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** M7a (A20): dakikalık cron — yeni olayları hedeflerin kuyruğuna al, zamanı gelenleri gönder. */
#[AsCommand('app:webhook:gonder', 'Giden webhook: kuyruğa al ve gönder')]
final readonly class WebhookGonderCommand
{
    public function __construct(private GidenWebhook $webhook)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $kuyruk = $this->webhook->kuyruga();
        $sonuc = $this->webhook->gonder();
        $io->writeln(sprintf('Kuyruğa: %d; teslim: %d; başarısız: %d', $kuyruk, $sonuc['teslim'], $sonuc['basarisiz']));

        return Command::SUCCESS;
    }
}
