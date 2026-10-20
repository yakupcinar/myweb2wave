<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Huni görselleri (A8 / E2.3). Kimlik = yeniden kodlanmış WebP'nin SHA-256'sı:
 * aynı görsel bir kez durur, dosya değişmez → sonsuz önbellek. Dosya
 * `var/medya/<id>.webp`; bu tablo panel listesi ve "bu görsel bu uygulamanın
 * mı" denetimi için.
 */
final class Version20261021090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'medya';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE medya (
            app TEXT NOT NULL,
            id TEXT NOT NULL CHECK (id ~ '^[0-9a-f]{64}$'),
            genislik INT NOT NULL CHECK (genislik > 0),
            yukseklik INT NOT NULL CHECK (yukseklik > 0),
            bayt INT NOT NULL CHECK (bayt > 0),
            ozgun_ad TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (app, id)
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE medya');
    }
}
