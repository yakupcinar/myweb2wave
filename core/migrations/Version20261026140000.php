<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ç56 (3): izin oranı raporu — reddeden ziyaret ayrıca işaretlenir
 * (seçmeyenden ayrılsın). Kişisel veri değil, yalnız an.
 */
final class Version20261026140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Huni ziyaretinde izin reddi (izin_red_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huni_ziyaretleri ADD izin_red_at TIMESTAMPTZ');
        $this->addSql('ALTER TABLE huni_ziyaretleri ADD CONSTRAINT huni_ziyaretleri_izin_tek_durum CHECK (izin_at IS NULL OR izin_red_at IS NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huni_ziyaretleri DROP CONSTRAINT huni_ziyaretleri_izin_tek_durum, DROP izin_red_at');
    }
}
