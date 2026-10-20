<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Hak kaynağı RevenueCat (A9): giden kutusu. Stripe aboneliği RevenueCat'e
 * yollanacaksa satır, abonelik kaydıyla AYNI işlemde yazılır; gönderim
 * işlemden sonra denenir, başarısızlar komutla yeniden. Her durum
 * değişikliği yeni satır (RevenueCat iptali yeniden gönderimle hemen görür).
 */
final class Version20261020220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'revenuecat_gidenler';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE revenuecat_gidenler (
            id BIGSERIAL PRIMARY KEY,
            app TEXT NOT NULL,
            stripe_subscription_id TEXT NOT NULL REFERENCES subscriptions(stripe_subscription_id),
            kisi_id UUID NOT NULL REFERENCES kisiler(id),
            created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
            gonderildi_at TIMESTAMPTZ,
            deneme INT NOT NULL DEFAULT 0,
            son_hata TEXT
        )');
        $this->addSql('CREATE INDEX revenuecat_gidenler_bekleyen ON revenuecat_gidenler (id) WHERE gonderildi_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE revenuecat_gidenler');
    }
}
