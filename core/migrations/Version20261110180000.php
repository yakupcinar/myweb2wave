<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M8b (A22): terk edilen ödeme kurtarma. Stripe süresi dolan oturuma kurtarma
 * adresi verir (30 gün; açılınca aynı satırların kopyası yeni oturum). Kopya
 * tamamlanınca asıl oturumun ziyaretine bağlanır (kurtarildigi_oturum).
 * posta_dizisi'nin anahtarı artık genel "kaynak" (abonelik ya da ödeme oturumu).
 */
final class Version20261110180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M8b: terk edilen ödeme kurtarma';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE posta_dizisi RENAME COLUMN stripe_subscription_id TO kaynak');
        $this->addSql('ALTER TABLE odeme_oturumlari ADD kurtarma_url TEXT, ADD kurtarildigi_oturum TEXT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE odeme_oturumlari DROP kurtarma_url, DROP kurtarildigi_oturum');
        $this->addSql('ALTER TABLE posta_dizisi RENAME COLUMN kaynak TO stripe_subscription_id');
    }
}
