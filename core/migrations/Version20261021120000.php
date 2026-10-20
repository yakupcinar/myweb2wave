<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ödeme sonrası giriş bağlantısı (Ç46). Jeton düz saklanmaz (SHA-256
 * özeti), tek kullanımlık, süreli. Abonelik hangi ödeme oturumundan
 * doğduğunu ve ödemenin ne zaman tamamlandığını tutar: başarı sayfası
 * jetonu yalnız ödemeden sonraki kısa pencerede alabilir; giriş e-postası
 * bir kez gider (giris_epostasi_at = giden kutusu).
 */
final class Version20261021120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'giris_jetonlari';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions ADD stripe_checkout_session_id TEXT UNIQUE');
        $this->addSql('ALTER TABLE subscriptions ADD odeme_tamamlandi_at TIMESTAMPTZ');
        $this->addSql('ALTER TABLE subscriptions ADD giris_epostasi_at TIMESTAMPTZ');
        $this->addSql('CREATE TABLE giris_jetonlari (
            id BIGSERIAL PRIMARY KEY,
            app TEXT NOT NULL,
            email TEXT NOT NULL CHECK (email = lower(email)),
            stripe_checkout_session_id TEXT NOT NULL,
            ozet TEXT NOT NULL UNIQUE,
            son_kullanma TIMESTAMPTZ NOT NULL,
            kullanildi_at TIMESTAMPTZ,
            created_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $this->addSql('CREATE INDEX giris_jetonlari_oturum ON giris_jetonlari (stripe_checkout_session_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE giris_jetonlari');
        $this->addSql('ALTER TABLE subscriptions DROP giris_epostasi_at');
        $this->addSql('ALTER TABLE subscriptions DROP odeme_tamamlandi_at');
        $this->addSql('ALTER TABLE subscriptions DROP stripe_checkout_session_id');
    }
}
