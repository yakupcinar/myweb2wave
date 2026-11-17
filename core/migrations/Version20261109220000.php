<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M6b2 (A19): web yolundan müşteri portalı — e-postaya tek kullanımlık,
 * süreli bağlantı. Jeton düz saklanmaz (özet); müşteri kullanım anında
 * e-postadan çözülür (abonelik o arada değişmiş olabilir).
 */
final class Version20261109220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M6b2: portal bağlantısı jetonları';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE portal_jetonlari (
                ozet TEXT PRIMARY KEY,
                app TEXT NOT NULL,
                email TEXT NOT NULL CHECK (email = lower(email)),
                son_kullanma TIMESTAMPTZ NOT NULL,
                kullanildi_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now()
            )
            SQL);
        $this->addSql('CREATE INDEX portal_jetonlari_eposta ON portal_jetonlari (app, email, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE portal_jetonlari');
    }
}
