<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M3c (A16): ek ürün kataloğu (tek seferlik Stripe fiyatı; tutar Stripe'ta,
 * L10) ve ödeme sonrası tek tık satışları. Satış oturum + ürün başına bir kez
 * (tekil kısıt — iki tık iki çekim olmasın).
 */
final class Version20261104160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M3c: ek_urunler, ek_satislar';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE ek_urunler (
                app TEXT NOT NULL,
                anahtar TEXT NOT NULL CHECK (anahtar ~ '^[a-z][a-z0-9_]{0,31}$'),
                ad TEXT NOT NULL CHECK (length(ad) BETWEEN 1 AND 100),
                stripe_urun TEXT NOT NULL,
                stripe_fiyat TEXT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (app, anahtar)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE ek_satislar (
                app TEXT NOT NULL,
                oturum TEXT NOT NULL,
                urun TEXT NOT NULL,
                payment_intent TEXT,
                durum TEXT NOT NULL CHECK (durum IN ('baslatildi', 'odendi', 'dogrulama', 'basarisiz')),
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (oturum, urun)
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ek_satislar');
        $this->addSql('DROP TABLE ek_urunler');
    }
}
