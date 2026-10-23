<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** 90 günden eski serbest yanıt metinlerini siler; huni ölçüm kayıtları kalır. */
#[AsCommand('app:huni-metin-cevaplari-sil', 'Süresi dolan huni serbest yanıtlarını sil')]
final readonly class MetinCevaplariSilCommand
{
    public const SAKLAMA_GUN = 90;

    public function __construct(private Connection $db)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $silinen = $this->db->executeStatement(
            "DELETE FROM huni_metin_cevaplari c USING huni_ziyaretleri z
             WHERE z.id = c.ziyaret_id AND z.created_at < now() - INTERVAL '".self::SAKLAMA_GUN." days'",
        );
        $io->writeln("Silinen serbest yanıt: {$silinen} (".self::SAKLAMA_GUN.' günden eski ziyaret)');

        return Command::SUCCESS;
    }
}
