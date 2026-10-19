<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Abonelik kaydı ve işlenmiş Stripe olayları.
 *
 * - processed_webhook_events.event_id PK: aynı olay iki kez gelirse ikincisi
 *   bu kısıtta düşer (R3). Uygulamadaki kontrol değil, KISIT asıl kapı.
 * - subscriptions.status_event_created: Stripe olay sırasını garanti etmiyor;
 *   daha eski olay daha yeni durumu ezmesin diye son uygulanan olayın zamanı.
 */
final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'subscriptions + processed_webhook_events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE processed_webhook_events (
            event_id TEXT PRIMARY KEY,
            type TEXT NOT NULL,
            processed_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $this->addSql('CREATE TABLE subscriptions (
            stripe_subscription_id TEXT PRIMARY KEY,
            stripe_customer_id TEXT NOT NULL,
            status TEXT,
            status_event_created BIGINT,
            email TEXT,
            updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE processed_webhook_events');
    }
}
