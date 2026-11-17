<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A31 (TB3): reklam harcaması TikTok'tan da çekilir — sağlayıcı kısıtı genişler.
 */
final class Version20261117140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A31: TikTok reklam harcaması';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reklam_harcamalari DROP CONSTRAINT reklam_harcamalari_saglayici_check');
        $this->addSql("ALTER TABLE reklam_harcamalari ADD CONSTRAINT reklam_harcamalari_saglayici_check CHECK (saglayici IN ('meta', 'tiktok'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM reklam_harcamalari WHERE saglayici = 'tiktok'");
        $this->addSql('ALTER TABLE reklam_harcamalari DROP CONSTRAINT reklam_harcamalari_saglayici_check');
        $this->addSql("ALTER TABLE reklam_harcamalari ADD CONSTRAINT reklam_harcamalari_saglayici_check CHECK (saglayici = 'meta')");
    }
}
