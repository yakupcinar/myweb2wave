<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Kimlik (A4/A5):
 * - kisiler: kalıcı UUID (ileride StoreKit appAccountToken, Ç29) + DOĞRULANMIŞ e-posta.
 * - baglantilar: (uygulama, uygulamadaki kullanıcı) → kişi. PK kısıtı asıl kapı.
 * - dogrulama_kodlari: kod düz değil HMAC özeti; süre, deneme sayısı, tek kullanım.
 * E-posta küçük harfle saklanır; abonelik eşlemesi lower(email) üzerinden.
 */
final class Version20261019180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'kisiler, baglantilar, dogrulama_kodlari';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE kisiler (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            email TEXT NOT NULL UNIQUE CHECK (email = lower(email)),
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $this->addSql('CREATE TABLE baglantilar (
            app TEXT NOT NULL,
            harici_kullanici TEXT NOT NULL,
            kisi_id UUID NOT NULL REFERENCES kisiler(id),
            created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            PRIMARY KEY (app, harici_kullanici)
        )');
        $this->addSql('CREATE TABLE dogrulama_kodlari (
            id BIGSERIAL PRIMARY KEY,
            app TEXT NOT NULL,
            email TEXT NOT NULL CHECK (email = lower(email)),
            kod_ozeti TEXT NOT NULL,
            son_kullanma TIMESTAMPTZ NOT NULL,
            deneme INT NOT NULL DEFAULT 0,
            kullanildi_at TIMESTAMPTZ,
            gecersiz_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $this->addSql('CREATE INDEX dogrulama_kodlari_app_email ON dogrulama_kodlari (app, email, created_at)');
        $this->addSql('CREATE INDEX subscriptions_app_email ON subscriptions (app, lower(email))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX subscriptions_app_email');
        $this->addSql('DROP TABLE dogrulama_kodlari');
        $this->addSql('DROP TABLE baglantilar');
        $this->addSql('DROP TABLE kisiler');
    }
}
