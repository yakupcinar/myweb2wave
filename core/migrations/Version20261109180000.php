<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M5d (A18): Meta kampanya harcaması, gün başına (Insights). Meta yakın
 * günleri sonradan düzeltir — aynı gün yeniden çekilince üzerine yazılır.
 * Tutar küçük birimde, hesabın para biriminde (çevrilmez).
 */
final class Version20261109180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M5d: reklam harcamaları (Meta Insights, kampanya × gün)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE reklam_harcamalari (
                saglayici TEXT NOT NULL CHECK (saglayici IN ('meta')),
                kampanya_id TEXT NOT NULL,
                kampanya_adi TEXT NOT NULL,
                gun DATE NOT NULL,
                harcama_kucuk BIGINT NOT NULL CHECK (harcama_kucuk >= 0),
                para_birimi TEXT NOT NULL CHECK (para_birimi ~ '^[a-z]{3}$'),
                guncellendi_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (saglayici, kampanya_id, gun)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reklam_harcamalari');
    }
}
