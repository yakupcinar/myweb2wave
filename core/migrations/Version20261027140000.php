<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A5a (B6): kişisel API anahtarları (MCP) ve araç çağrısı kaydı. Anahtarın
 * kendisi tutulmaz — yalnız SHA-256 özeti (bir kez gösterilir, web2wave gibi).
 */
final class Version20261027140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'MCP API anahtarları (api_anahtarlari) ve çağrı kaydı (mcp_cagrilari)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE api_anahtarlari (
            id BIGSERIAL PRIMARY KEY,
            app TEXT NOT NULL,
            ad TEXT NOT NULL CHECK (char_length(ad) BETWEEN 1 AND 100),
            ozet CHAR(64) NOT NULL UNIQUE CHECK (ozet ~ '^[0-9a-f]{64}$'),
            onek TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            son_kullanim_at TIMESTAMPTZ,
            iptal_at TIMESTAMPTZ
        )");
        $this->addSql('CREATE TABLE mcp_cagrilari (
            id BIGSERIAL PRIMARY KEY,
            anahtar_id BIGINT NOT NULL REFERENCES api_anahtarlari (id),
            arac TEXT NOT NULL,
            hata BOOLEAN NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $this->addSql('CREATE INDEX mcp_cagrilari_zaman ON mcp_cagrilari (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mcp_cagrilari');
        $this->addSql('DROP TABLE api_anahtarlari');
    }
}
