<?php

declare(strict_types=1);

namespace App\Command;

use App\Panel\PanelDenetimi;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Ç152: panel yazma denetimini okur — son kayıtlar, yeniden eskiye, satır
 * başına "zaman UTC · kullanıcı · yöntem rota kaynak → durum".
 * Örnek: bin/console app:panel-denetim --son 100.
 */
#[AsCommand('app:panel-denetim', 'Panel yazma denetimi: son kayıtlar · --son N')]
final readonly class PanelDenetimCommand
{
    public function __construct(private PanelDenetimi $denetim)
    {
    }

    public function __invoke(OutputInterface $cikti, #[Option('kaç kayıt (1–1000)')] int $son = 50): int
    {
        if ($son < 1 || $son > 1000) {
            $cikti->writeln('--son 1 ile 1000 arasında olmalı');

            return Command::INVALID;
        }
        foreach ($this->denetim->son($son) as $s) {
            $cikti->writeln("{$s['zaman']} · {$s['kullanici']} · {$s['yontem']} {$s['rota']} {$s['kaynak']} → {$s['durum']}");
        }

        return Command::SUCCESS;
    }
}
