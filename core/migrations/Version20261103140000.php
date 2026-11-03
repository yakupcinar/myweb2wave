<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * E1: huninin e-posta adımı. Serbest yanıtlardan AYRI tablo — rapor, AI ve
 * koşullar serbest yanıtı okur, e-postayı okumaz. Ziyaret başına tek adres
 * (son yazılan); pazarlama izni yalnız ziyaretçi kutuyu işaretlediyse.
 */
final class Version20261103140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'E1: huni_epostalari (huninin e-posta adımı)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE huni_epostalari (
                ziyaret_id UUID PRIMARY KEY REFERENCES huni_ziyaretleri (id) ON DELETE CASCADE,
                eposta TEXT NOT NULL CHECK (length(eposta) BETWEEN 3 AND 254 AND eposta = lower(eposta)),
                pazarlama_izni_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE huni_epostalari');
    }
}
