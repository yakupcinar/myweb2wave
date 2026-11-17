<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ç41: ziyareti olan huni silinmez (rapor sürüme bağlı) — arşivlenir.
 * Varsayılan huni arşivlenemez: hiçbir kural tutmayınca açılacak huni yok olurdu.
 */
final class Version20261026130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Huni arşivi (arsiv_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huniler ADD arsiv_at TIMESTAMPTZ');
        $this->addSql('ALTER TABLE huniler ADD CONSTRAINT huniler_varsayilan_arsivde_olamaz CHECK (NOT (varsayilan AND arsiv_at IS NOT NULL))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huniler DROP CONSTRAINT huniler_varsayilan_arsivde_olamaz, DROP arsiv_at');
    }
}
