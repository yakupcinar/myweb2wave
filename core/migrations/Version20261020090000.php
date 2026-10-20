<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reklam atfı (A6), ham veri bizde:
 * - huni_ziyaretleri: huni sayfası açıldı; hangi reklamdan (UTM, fbclid, ttclid).
 * - odeme_oturumlari: ziyaretten açılan Stripe Checkout oturumu; ödeme
 *   tamamlanınca abonelikle eşleşir. Oturum ayrı tablo: aynı ziyaretten
 *   iptal edip yeniden denenen her oturum ayrı satır, hiçbiri ezilmez.
 */
final class Version20261020090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'huni_ziyaretleri, odeme_oturumlari';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE huni_ziyaretleri (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            app TEXT NOT NULL,
            utm_source TEXT,
            utm_medium TEXT,
            utm_campaign TEXT,
            utm_content TEXT,
            utm_term TEXT,
            fbclid TEXT,
            ttclid TEXT,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $this->addSql('CREATE INDEX huni_ziyaretleri_app_zaman ON huni_ziyaretleri (app, created_at)');
        $this->addSql('CREATE TABLE odeme_oturumlari (
            stripe_checkout_session_id TEXT PRIMARY KEY,
            ziyaret_id UUID NOT NULL REFERENCES huni_ziyaretleri(id),
            stripe_subscription_id TEXT,
            tamamlandi_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $this->addSql('CREATE INDEX odeme_oturumlari_ziyaret ON odeme_oturumlari (ziyaret_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE odeme_oturumlari');
        $this->addSql('DROP TABLE huni_ziyaretleri');
    }
}
