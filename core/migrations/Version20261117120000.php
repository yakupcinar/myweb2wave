<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ç151: panelden abonelik işlemlerinin denetim izi — kim, ne zaman, hangi
 * abonelik, ne yaptı, sonuç. E-posta tutulmaz (yalnız abonelik kimliği).
 * Güncelleme ve satır silme reddedilir: iz sonradan değiştirilemez.
 */
final class Version20261117120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ç151: panel işlem geçmişi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE panel_islemleri (
            id BIGSERIAL PRIMARY KEY,
            zaman TIMESTAMPTZ NOT NULL DEFAULT now(),
            kullanici TEXT NOT NULL,
            abonelik TEXT NOT NULL,
            islem TEXT NOT NULL CHECK (islem IN ('iptal', 'iptal_geri_al', 'duraklat', 'surdur', 'iade', 'eposta_duzelt')),
            basarili BOOLEAN NOT NULL,
            ayrinti TEXT
        )");
        $this->addSql('CREATE INDEX panel_islemleri_abonelik ON panel_islemleri (abonelik, id DESC)');
        $this->addSql(<<<'SQL'
            CREATE FUNCTION panel_islemleri_degismez() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'panel_islemleri yalnız ekleme alır';
            END $$
            SQL);
        $this->addSql('CREATE TRIGGER panel_islemleri_degismez BEFORE UPDATE OR DELETE ON panel_islemleri FOR EACH ROW EXECUTE FUNCTION panel_islemleri_degismez()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE panel_islemleri');
        $this->addSql('DROP FUNCTION panel_islemleri_degismez()');
    }
}
