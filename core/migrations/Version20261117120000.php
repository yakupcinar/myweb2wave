<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A31: TikTok bağlantısı uygulama başına, panelden (A30'un TikTok karşılığı).
 * Anahtarlar şifreli (Sifreleyici). Pixel kodu TikTok'ta büyük harf + rakam,
 * reklamveren kimliği yalnız rakam.
 */
final class Version20261117120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'A31: TikTok bağlantısı panelden';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE tiktok_baglantilari (
            app TEXT PRIMARY KEY,
            pixel_kodu TEXT CHECK (pixel_kodu ~ '^[A-Z0-9]{10,40}$'),
            events_anahtari TEXT,
            test_kodu TEXT CHECK (test_kodu ~ '^[A-Za-z0-9_]{1,40}$'),
            reklamveren TEXT CHECK (reklamveren ~ '^[0-9]{5,25}$'),
            rapor_anahtari TEXT,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tiktok_baglantilari');
    }
}
