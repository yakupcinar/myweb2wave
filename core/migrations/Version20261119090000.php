<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ç152: paneldeki her yazmanın denetim izi — kim, ne zaman, hangi rota, hangi
 * kaynak (rota parametreleri), sonuç (HTTP durumu). İstek gövdesi tutulmaz
 * (anahtar, sır, metin). Güncelleme ve satır silme reddedilir (Ç151 gibi).
 */
final class Version20261119090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ç152: panel yazma denetimi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE panel_denetim (
            id BIGSERIAL PRIMARY KEY,
            zaman TIMESTAMPTZ NOT NULL DEFAULT now(),
            kullanici TEXT NOT NULL,
            yontem TEXT NOT NULL CHECK (yontem IN ('POST', 'PUT', 'PATCH', 'DELETE')),
            rota TEXT NOT NULL,
            kaynak JSONB NOT NULL,
            durum SMALLINT NOT NULL
        )");
        $this->addSql(<<<'SQL'
            CREATE FUNCTION panel_denetim_degismez() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'panel_denetim yalnız ekleme alır';
            END $$
            SQL);
        $this->addSql('CREATE TRIGGER panel_denetim_degismez BEFORE UPDATE OR DELETE ON panel_denetim FOR EACH ROW EXECUTE FUNCTION panel_denetim_degismez()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE panel_denetim');
        $this->addSql('DROP FUNCTION panel_denetim_degismez()');
    }
}
