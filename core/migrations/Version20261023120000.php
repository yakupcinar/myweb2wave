<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Serbest yanıtlar seçenek sayımlarından ayrı, ziyaret bazında tutulur. */
final class Version20261023120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'huni serbest yanıtları';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE huni_metin_cevaplari (
            ziyaret_id UUID NOT NULL REFERENCES huni_ziyaretleri(id) ON DELETE CASCADE,
            soru TEXT NOT NULL,
            cevap TEXT NOT NULL,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (ziyaret_id, soru),
            CHECK (char_length(cevap) BETWEEN 1 AND 500)
        )');
        $this->addSql('CREATE INDEX huni_metin_cevaplari_soru ON huni_metin_cevaplari (soru)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE huni_metin_cevaplari');
    }
}
