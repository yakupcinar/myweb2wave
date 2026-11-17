<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M1a: fiyat kataloğu — uygulamanın planları yapılandırmadan (uygulamalar.yaml
 * + ortam değişkeni) veritabanına. Tutar burada YOK: fiyatın tek kaynağı yine
 * Stripe fiyat nesnesi (L10); satır yalnız plan anahtarını Stripe'a eşler.
 */
final class Version20261103120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1a: planlar (fiyat kataloğu)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE planlar (
                app TEXT NOT NULL,
                anahtar TEXT NOT NULL CHECK (anahtar ~ '^[a-z][a-z0-9_]{0,31}$'),
                ad TEXT NOT NULL CHECK (length(ad) BETWEEN 1 AND 100),
                stripe_urun TEXT NOT NULL,
                stripe_fiyat TEXT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (app, anahtar)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE planlar');
    }
}
