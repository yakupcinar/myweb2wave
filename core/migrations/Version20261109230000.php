<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M7a (A20): giden webhook. Hedef başına olay deposu imleci (işlem, id) —
 * Olaylar::BITMIS ile aynı sıra (tuzak §53); yeni hedef geçmişi yeniden
 * göndermez: imleç kuruluş anındaki xmin, ondan sonra biten işlemler gelir.
 * Teslim satırı hedef × olay başına bir (en az bir kez teslim, tekrar yok).
 */
final class Version20261109230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M7a: webhook hedefleri ve teslimleri';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE webhook_hedefleri (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                app TEXT NOT NULL,
                adres TEXT NOT NULL CHECK (adres ~ '^https?://'),
                olaylar TEXT[] NOT NULL CHECK (cardinality(olaylar) > 0),
                sir TEXT NOT NULL,
                aktif BOOLEAN NOT NULL DEFAULT true,
                son_islem XID8 NOT NULL DEFAULT pg_snapshot_xmin(pg_current_snapshot()),
                son_olay_id BIGINT NOT NULL DEFAULT 0,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE webhook_teslimleri (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                hedef_id UUID NOT NULL REFERENCES webhook_hedefleri (id) ON DELETE CASCADE,
                olay_id BIGINT NOT NULL REFERENCES olaylar (id) ON DELETE CASCADE,
                deneme INT NOT NULL DEFAULT 0,
                durum TEXT NOT NULL DEFAULT 'bekliyor' CHECK (durum IN ('bekliyor', 'teslim', 'vazgecildi')),
                son_hata TEXT,
                sonraki_deneme_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                teslim_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (hedef_id, olay_id)
            )
            SQL);
        $this->addSql('CREATE INDEX olaylar_akis ON olaylar (app, islem, id)');
        $this->addSql("CREATE INDEX webhook_teslimleri_bekleyen ON webhook_teslimleri (sonraki_deneme_at) WHERE durum = 'bekliyor'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE webhook_teslimleri');
        $this->addSql('DROP TABLE webhook_hedefleri');
        $this->addSql('DROP INDEX olaylar_akis');
    }
}
