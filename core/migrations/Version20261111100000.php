<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * P7c: zamanlı komutların son başarılı çalışması — panelin "Canlıya geçiş"
 * listesi cron'un gerçekten çalıştığını buradan okur (GorevKaydi).
 */
final class Version20261111100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'P7c: zamanlı görevlerin son çalışması';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE zamanli_gorevler (komut TEXT PRIMARY KEY, son_calisma TIMESTAMPTZ NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE zamanli_gorevler');
    }
}
