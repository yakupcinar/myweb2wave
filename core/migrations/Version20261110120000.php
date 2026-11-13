<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M8a1 (A22): Postmark'ın bastırma listesinin yerel kopyası (akış başına).
 * Satır silinmez: yeniden etkinleştirme bastirildi = false yazar, böylece
 * sırası karışmış eski bildirim yenisini ezemez (degisti_at karşılaştırması).
 */
final class Version20261110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M8a1: posta bastırma listesi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE posta_bastirma (
            email TEXT NOT NULL CHECK (email = lower(email)),
            akis TEXT NOT NULL,
            bastirildi BOOLEAN NOT NULL,
            neden TEXT,
            degisti_at TIMESTAMPTZ NOT NULL,
            PRIMARY KEY (email, akis)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE posta_bastirma');
    }
}
