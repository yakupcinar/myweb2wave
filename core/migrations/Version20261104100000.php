<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M1b: planın giriş teklifi (en çok bir). deneme = kartlı ücretsiz gün;
 * giris = gün + tek seferlik Stripe fiyatı (ücretli giriş, deneme olarak
 * kurulur); indirim = ilk dönem yüzde, Stripe kuponu. Giriş tutarı burada
 * YOK — fiyat gibi Stripe'tan okunur (L10). Kısıt türün alanlarını birlikte
 * zorlar: yarım teklif (ör. kuponsuz indirim) yazılamaz.
 */
final class Version20261104100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M1b: planlar teklif sütunları';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE planlar
                ADD COLUMN teklif_turu TEXT,
                ADD COLUMN teklif_gun INTEGER CHECK (teklif_gun BETWEEN 1 AND 90),
                ADD COLUMN teklif_yuzde INTEGER CHECK (teklif_yuzde BETWEEN 1 AND 99),
                ADD COLUMN stripe_teklif TEXT,
                ADD CONSTRAINT planlar_teklif_tutarli CHECK (
                    (teklif_turu IS NULL AND teklif_gun IS NULL AND teklif_yuzde IS NULL AND stripe_teklif IS NULL)
                    OR (teklif_turu = 'deneme' AND teklif_gun IS NOT NULL AND teklif_yuzde IS NULL AND stripe_teklif IS NULL)
                    OR (teklif_turu = 'giris' AND teklif_gun IS NOT NULL AND teklif_yuzde IS NULL AND stripe_teklif IS NOT NULL)
                    OR (teklif_turu = 'indirim' AND teklif_gun IS NULL AND teklif_yuzde IS NOT NULL AND stripe_teklif IS NOT NULL)
                )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planlar DROP CONSTRAINT planlar_teklif_tutarli, DROP COLUMN teklif_turu, DROP COLUMN teklif_gun, DROP COLUMN teklif_yuzde, DROP COLUMN stripe_teklif');
    }
}
