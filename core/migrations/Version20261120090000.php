<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * BR1: uygulamanın sunucusu bize geçerli anahtarla en son ne zaman istek attı —
 * panelin "Uygulama bağlantısı" adımı. Uygulama başına tek satır; dakikada en
 * çok bir yazma (UygulamaYetkisi).
 */
final class Version20261120090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'BR1: uygulama sunucusunun son isteği';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE uygulama_istekleri (app TEXT PRIMARY KEY, son_istek_at TIMESTAMPTZ NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE uygulama_istekleri');
    }
}
