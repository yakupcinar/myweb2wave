<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M7c2 (A21): MCP yazma araçları (abonelik iptal / iade, deney) yalnız
 * izinli anahtarla. Var olan anahtarlar izinsiz kalır (varsayılan false).
 */
final class Version20261109235000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M7c2: API anahtarına yönetim izni';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_anahtarlari ADD yonetim BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_anahtarlari DROP yonetim');
    }
}
