<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * E1a: e-posta şablonları uygulama × şablon × dil. Kayıt yoksa koddaki
 * varsayılan gider (EpostaSablonlari::VARSAYILANLAR); kayıt panelden.
 */
final class Version20261112090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E1a: e-posta şablonları';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE eposta_sablonlari (
            app TEXT NOT NULL,
            sablon TEXT NOT NULL CHECK (sablon IN ('dogrulama_kodu', 'giris_baglantisi', 'portal_baglantisi', 'aktivasyon_1', 'aktivasyon_2', 'kurtarma_1', 'kurtarma_2')),
            dil TEXT NOT NULL CHECK (dil IN ('tr', 'en')),
            konu TEXT NOT NULL CHECK (char_length(konu) BETWEEN 1 AND 200),
            metin TEXT NOT NULL CHECK (char_length(metin) BETWEEN 1 AND 5000),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (app, sablon, dil)
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE eposta_sablonlari');
    }
}
