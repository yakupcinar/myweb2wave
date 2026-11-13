<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M2b: teklifin bitiş anı (isteğe bağlı, herkes için aynı — A15). Geçince
 * teklif ne paywall'da ne ödemede uygulanır; sayaç buna sayar. Teklifsiz
 * planda bitiş olmaz.
 */
final class Version20261104140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M2b: planlar.teklif_bitis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planlar ADD COLUMN teklif_bitis TIMESTAMPTZ, ADD CONSTRAINT planlar_teklif_bitis_teklifli CHECK (teklif_turu IS NOT NULL OR teklif_bitis IS NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planlar DROP CONSTRAINT planlar_teklif_bitis_teklifli, DROP COLUMN teklif_bitis');
    }
}
