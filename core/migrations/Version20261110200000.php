<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M9a (A23): uygulama kimliği panelde — görünen ad, URL şeması, mağaza
 * adresi, destek e-postası. Uygulama LİSTESİ yaml'da kalır (A4, sözleşme
 * enum'u); ayarları burada. KeepChat'in şeması yaml'dan taşınır.
 */
final class Version20261110200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M9a: uygulama ayarları';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE uygulama_ayarlari (
            app TEXT PRIMARY KEY,
            ad TEXT NOT NULL CHECK (char_length(ad) BETWEEN 1 AND 60),
            sema TEXT NOT NULL CHECK (sema ~ '^[a-z][a-z0-9+.-]{1,31}$'),
            magaza_adresi TEXT NOT NULL DEFAULT '' CHECK (magaza_adresi = '' OR (magaza_adresi LIKE 'https://%' AND char_length(magaza_adresi) <= 300)),
            destek_epostasi TEXT NOT NULL DEFAULT '' CHECK (char_length(destek_epostasi) <= 254),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )");
        $this->addSql("INSERT INTO uygulama_ayarlari (app, ad, sema) VALUES ('keepchat', 'KeepChat', 'keepchat')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE uygulama_ayarlari');
    }
}
