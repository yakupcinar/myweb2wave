<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A30: Meta bağlantısı uygulama başına, panelden. Anahtarlar şifreli
 * (Sifreleyici). Dönüşüm kuyruğu hangi uygulamanın anahtarıyla gideceğini
 * bilsin diye app taşır.
 */
final class Version20261114090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A30: Meta bağlantısı panelden';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE meta_baglantilari (
            app TEXT PRIMARY KEY,
            pixel_id TEXT CHECK (pixel_id ~ '^[0-9]{5,20}$'),
            capi_anahtari TEXT,
            test_kodu TEXT CHECK (test_kodu ~ '^[A-Za-z0-9_]{1,40}$'),
            reklam_hesabi TEXT CHECK (reklam_hesabi ~ '^act_[0-9]{5,25}$'),
            insights_anahtari TEXT,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )");
        $this->addSql('ALTER TABLE reklam_donusumleri ADD COLUMN app TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reklam_donusumleri DROP COLUMN app');
        $this->addSql('DROP TABLE meta_baglantilari');
    }
}
