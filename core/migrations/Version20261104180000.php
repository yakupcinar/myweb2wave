<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M4a (A17): deneyler ve ziyaretlere atanan varyantlar. Atama sunucuda
 * saklanmaz — tohum + deney adının özetinden yeniden üretilir; ziyarete yazılan
 * varyant sonuçlar (M4b) ve `deney.<ad>` özelliği (T2) içindir.
 */
final class Version20261104180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M4a: deneyler, ziyaret_deneyleri, deney.* özelliği';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE deneyler (
                app TEXT NOT NULL,
                ad TEXT NOT NULL CHECK (ad ~ '^[a-z0-9_-]{1,64}$'),
                tur TEXT NOT NULL CHECK (tur IN ('yonlendirme', 'icerik')),
                huni TEXT NOT NULL,
                varyantlar JSONB NOT NULL,
                durum TEXT NOT NULL DEFAULT 'taslak' CHECK (durum IN ('taslak', 'aktif', 'durdu', 'bitti')),
                baslangic_at TIMESTAMPTZ,
                bitis_at TIMESTAMPTZ,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (app, ad)
            )
            SQL);
        // Bir hunide aynı anda en çok bir aktif yönlendirme deneyi (trafik iki kez bölünmesin).
        $this->addSql("CREATE UNIQUE INDEX deneyler_tek_yonlendirme ON deneyler (app, huni) WHERE tur = 'yonlendirme' AND durum = 'aktif'");
        $this->addSql(<<<'SQL'
            CREATE TABLE ziyaret_deneyleri (
                ziyaret_id UUID NOT NULL REFERENCES huni_ziyaretleri (id) ON DELETE CASCADE,
                deney TEXT NOT NULL,
                varyant TEXT NOT NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                PRIMARY KEY (ziyaret_id, deney)
            )
            SQL);
        $this->addSql('CREATE OR REPLACE VIEW ziyaret_ozellikleri (ziyaret_id, ad, deger, zaman) AS '.self::sorgu());
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE OR REPLACE VIEW ziyaret_ozellikleri (ziyaret_id, ad, deger, zaman) AS '.Version20261102130000::sorgu(' COLLATE "C"'));
        $this->addSql('DROP TABLE ziyaret_deneyleri');
        $this->addSql('DROP TABLE deneyler');
    }

    /** T2 + M4a: özellik görünümü — önceki tanım + atanan deney varyantları. */
    public static function sorgu(): string
    {
        return Version20261102130000::sorgu(' COLLATE "C"')."
            UNION ALL SELECT ziyaret_id, 'deney.' || deney, to_jsonb(varyant), created_at FROM ziyaret_deneyleri";
    }
}
