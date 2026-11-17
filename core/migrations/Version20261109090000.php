<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M4b (A17): deney dönüşümü = abonelik başladı (kartlı deneme dahil);
 * "ödedi" = oturumda gerçekten tutar çekildi. Stripe'ın payment_status'u
 * bunu ayırmaz: kartlı denemede de "paid" der, tutar 0 (ölçüldü 2026-11-09,
 * m4b-deney-sonuclari.md). Tutar küçük birimde, para birimiyle (çevrilmez).
 * Eski oturumların abonelik anı subscriptions'tan; tutarı bilinmez (boş).
 */
final class Version20261109090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M4b: ödeme oturumunda abonelik başlama anı (deneme dahil) ve çekilen tutar';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE odeme_oturumlari ADD abonelik_at TIMESTAMPTZ, ADD tutar_kucuk BIGINT, ADD para_birimi TEXT');
        $this->addSql("ALTER TABLE odeme_oturumlari ADD CONSTRAINT odeme_oturumlari_tutar CHECK ((tutar_kucuk IS NULL) = (para_birimi IS NULL) AND (tutar_kucuk IS NULL OR (tutar_kucuk >= 0 AND para_birimi ~ '^[a-z]{3}$')))");
        $this->addSql('UPDATE odeme_oturumlari o SET abonelik_at = s.odeme_tamamlandi_at, stripe_subscription_id = coalesce(o.stripe_subscription_id, s.stripe_subscription_id)
                       FROM subscriptions s WHERE s.stripe_checkout_session_id = o.stripe_checkout_session_id AND s.odeme_tamamlandi_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE odeme_oturumlari DROP CONSTRAINT odeme_oturumlari_tutar, DROP abonelik_at, DROP tutar_kucuk, DROP para_birimi');
    }
}
