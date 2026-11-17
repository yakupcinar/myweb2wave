<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * R1a: ziyaretçinin ülkesi (iki harf, IP'den — fiyatla aynı kaynak, M1c).
 * Rapor ülkeye göre süzülür. IP saklanmaz; eski ziyaretlerde boş.
 */
final class Version20261111140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'R1a: ziyarette ülke';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE huni_ziyaretleri ADD ulke CHAR(2) CHECK (ulke ~ '^[A-Z]{2}$')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE huni_ziyaretleri DROP ulke');
    }
}
