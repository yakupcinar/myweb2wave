<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M9b1 (A23): yasal veriler (uygulama_ayarlari) + yasal metinler. Metin
 * yalnız yönetici kaydedince yayında (satır yoksa sayfa 404); taslak kodda.
 */
final class Version20261110220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M9b1: yasal veriler ve metinler';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE uygulama_ayarlari
            ADD unvan TEXT NOT NULL DEFAULT '' CHECK (char_length(unvan) <= 200),
            ADD adres TEXT NOT NULL DEFAULT '' CHECK (char_length(adres) <= 500),
            ADD kayit_no TEXT NOT NULL DEFAULT '' CHECK (char_length(kayit_no) <= 100)");
        $this->addSql("CREATE TABLE yasal_metinler (
            app TEXT NOT NULL,
            belge TEXT NOT NULL CHECK (belge IN ('sartlar', 'gizlilik', 'kvkk', 'cerez')),
            dil TEXT NOT NULL CHECK (dil IN ('tr', 'en')),
            metin TEXT NOT NULL CHECK (char_length(metin) BETWEEN 1 AND 50000),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (app, belge, dil)
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yasal_metinler');
        $this->addSql('ALTER TABLE uygulama_ayarlari DROP unvan, DROP adres, DROP kayit_no');
    }
}
