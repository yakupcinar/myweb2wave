<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M6a (A19): dönem sonunda iptal ve iade. Dönem sonu aboneliğin kaleminde
 * (API 2026-09-30, ölçüldü); fatura ↔ ödeme bağı invoice_payment.paid'den
 * (fatura ödeme kimliği taşımıyor, iade ödemeye gelir). İade toplamı Stripe'ın
 * birikimli amount_refunded'ı — tekrar ya da sıra dışı olay iki kez düşmez.
 */
final class Version20261109200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M6a: abonelik iptal planı + dönem sonu, fatura ödemeleri, iade tutarı';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions ADD iptal_donem_sonu BOOLEAN NOT NULL DEFAULT false, ADD donem_sonu TIMESTAMPTZ');
        $this->addSql('CREATE TABLE fatura_odemeleri (stripe_payment_intent_id TEXT PRIMARY KEY, stripe_invoice_id TEXT NOT NULL)');
        $this->addSql('CREATE INDEX fatura_odemeleri_fatura ON fatura_odemeleri (stripe_invoice_id)');
        $this->addSql('ALTER TABLE odeme_faturalari ADD iade_kucuk BIGINT NOT NULL DEFAULT 0, ADD CONSTRAINT odeme_faturalari_iade CHECK (iade_kucuk >= 0 AND iade_kucuk <= tutar_kucuk)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE odeme_faturalari DROP CONSTRAINT odeme_faturalari_iade, DROP iade_kucuk');
        $this->addSql('DROP TABLE fatura_odemeleri');
        $this->addSql('ALTER TABLE subscriptions DROP iptal_donem_sonu, DROP donem_sonu');
    }
}
