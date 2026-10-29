<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A4: uygulama başına ürün bağlamı — AI asistanının sistem istemine girer
 * (FunnelFox'un "project context"i). Uzunluk sınırları uygulamayla aynı
 * (UrunBaglami::ALANLAR); kısıt ayrıca ölçülür (tuzak §4).
 */
final class Version20261027130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Uygulama ürün bağlamı (uygulama_baglamlari)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE uygulama_baglamlari (
            app TEXT PRIMARY KEY,
            urun TEXT NOT NULL DEFAULT '' CHECK (char_length(urun) <= 1000),
            kategori TEXT NOT NULL DEFAULT '' CHECK (char_length(kategori) <= 100),
            kitle TEXT NOT NULL DEFAULT '' CHECK (char_length(kitle) <= 500),
            ton TEXT NOT NULL DEFAULT '' CHECK (char_length(ton) <= 200),
            yasaklar TEXT NOT NULL DEFAULT '' CHECK (char_length(yasaklar) <= 1000),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE uygulama_baglamlari');
    }
}
