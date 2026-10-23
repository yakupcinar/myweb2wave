<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** H6b: izinli checkout sinyalleri ve ağ dışı reklam dönüşüm kuyruğu. */
final class Version20261023140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'H6b reklam dönüşüm kuyruğu ve izinli checkout bağlamı';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE odeme_oturumlari ADD ip_adresi INET, ADD user_agent TEXT');
        $this->addSql('ALTER TABLE odeme_oturumlari ADD CONSTRAINT odeme_oturumlari_ua_boyut CHECK (user_agent IS NULL OR char_length(user_agent) <= 1000)');
        $this->addSql('CREATE TABLE reklam_donusumleri (
            id BIGSERIAL PRIMARY KEY,
            saglayici TEXT NOT NULL CHECK (saglayici IN (\'meta\', \'tiktok\')),
            event_id TEXT NOT NULL,
            payload JSONB,
            deneme SMALLINT NOT NULL DEFAULT 0 CHECK (deneme BETWEEN 0 AND 10),
            sonraki_deneme_at TIMESTAMPTZ,
            son_hata TEXT,
            gonderildi_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            UNIQUE (saglayici, event_id)
        )');
        $this->addSql('CREATE INDEX reklam_donusumleri_bekleyen ON reklam_donusumleri (id) WHERE gonderildi_at IS NULL AND payload IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reklam_donusumleri');
        $this->addSql('ALTER TABLE odeme_oturumlari DROP CONSTRAINT odeme_oturumlari_ua_boyut, DROP ip_adresi, DROP user_agent');
    }
}
