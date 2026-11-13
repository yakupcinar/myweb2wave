<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A2: panel AI istekleri — maliyet ve sonuç kaydı. İstem METNİ tutulmaz
 * (yalnız sayılar); ziyaretçi verisi AI'a hiç gitmez.
 */
final class Version20261027120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Panel AI istek kaydı (ai_istekleri)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE ai_istekleri (
            id BIGSERIAL PRIMARY KEY,
            app TEXT NOT NULL,
            huni TEXT NOT NULL,
            model TEXT NOT NULL,
            tur INT NOT NULL CHECK (tur >= 0),
            girdi_jeton INT NOT NULL DEFAULT 0,
            cikti_jeton INT NOT NULL DEFAULT 0,
            onbellek_okuma INT NOT NULL DEFAULT 0,
            onbellek_yazma INT NOT NULL DEFAULT 0,
            maliyet_mikrodolar BIGINT,
            sonuc TEXT NOT NULL CHECK (sonuc IN ('tamam', 'tur_siniri', 'reddedildi', 'jeton_siniri', 'hata')),
            kaydedildi BOOLEAN NOT NULL DEFAULT false,
            sure_ms INT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )");
        $this->addSql('CREATE INDEX ai_istekleri_zaman ON ai_istekleri (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ai_istekleri');
    }
}
