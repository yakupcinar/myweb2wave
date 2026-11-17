<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M5b (A18): tahsil edilen Stripe faturaları — kohort geliri deneme sonrası
 * ilk ödemeyi ve yenilemeleri de saysın (oturum tutarı yalnız ilk ödemeyi
 * bilir, denemede 0). Yalnız tutarı > 0 olanlar; küçük birim, çevrilmez.
 * Ziyarete bağ aboneliğin ödeme oturumundan (odeme_oturumlari).
 */
final class Version20261109150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M5b: tahsil edilen Stripe faturaları (odeme_faturalari)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE odeme_faturalari (
                stripe_invoice_id TEXT PRIMARY KEY,
                stripe_subscription_id TEXT NOT NULL,
                app TEXT NOT NULL,
                tutar_kucuk BIGINT NOT NULL CHECK (tutar_kucuk > 0),
                para_birimi TEXT NOT NULL CHECK (para_birimi ~ '^[a-z]{3}$'),
                neden TEXT,
                odendi_at TIMESTAMPTZ NOT NULL
            )
            SQL);
        $this->addSql('CREATE INDEX odeme_faturalari_abonelik ON odeme_faturalari (stripe_subscription_id, odendi_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE odeme_faturalari');
    }
}
