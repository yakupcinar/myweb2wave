<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A4: abonelik hangi uygulamaya ait. Var olan satırlar (hepsi KeepChat
 * paywall'undan) 'keepchat' olur; sonra NOT NULL — uygulamasız abonelik olmaz.
 * Doğrudan NOT NULL eklemek dolu tabloda düşer; üç adım bu yüzden.
 */
final class Version20261019150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'subscriptions.app (A4)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions ADD COLUMN app TEXT');
        $this->addSql("UPDATE subscriptions SET app = 'keepchat' WHERE app IS NULL");
        $this->addSql('ALTER TABLE subscriptions ALTER COLUMN app SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions DROP COLUMN app');
    }
}
