<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M6c (A28): Stripe pause_collection — duraklatıldı mı, ne zaman kendiliğinden
 * sürer. Bitiş geçince Stripe sürdürür; hak bitişe de bakar (olay gecikse de).
 */
final class Version20261112120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M6c: abonelik duraklatma';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions
            ADD COLUMN duraklatildi BOOLEAN NOT NULL DEFAULT false,
            ADD COLUMN duraklatma_bitis TIMESTAMPTZ,
            ADD CONSTRAINT subscriptions_duraklatma_bitis CHECK (duraklatma_bitis IS NULL OR duraklatildi)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT subscriptions_duraklatma_bitis, DROP COLUMN duraklatma_bitis, DROP COLUMN duraklatildi');
    }
}
