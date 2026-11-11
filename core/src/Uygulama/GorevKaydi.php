<?php

declare(strict_types=1);

namespace App\Uygulama;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * P7c: zamanlı komut (CanliyaGecis::GOREVLER) başarıyla bitince zamanı
 * yazılır. Hatayla biten çalışma yazılmaz — "cron kurulu ama iş düşüyor"
 * da eksik görünsün.
 */
#[AsEventListener]
final readonly class GorevKaydi
{
    public function __construct(private Connection $db)
    {
    }

    public function __invoke(ConsoleTerminateEvent $olay): void
    {
        $komut = $olay->getCommand()?->getName();
        if (null === $komut || !isset(CanliyaGecis::GOREVLER[$komut]) || 0 !== $olay->getExitCode()) {
            return;
        }
        $this->db->executeStatement(
            'INSERT INTO zamanli_gorevler (komut, son_calisma) VALUES (?, now())
             ON CONFLICT (komut) DO UPDATE SET son_calisma = EXCLUDED.son_calisma',
            [$komut],
        );
    }
}
