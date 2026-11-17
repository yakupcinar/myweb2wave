<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M5a (Ç92, A18): olay deposu yalnız ekleme yapılan tabloya taşınır. T4
 * görünümü kaynak tabloların son hâlinden türetiyordu — cevap değişimi,
 * izin geri alma, abonelik durum geçmişi kayboluyordu. Yazma yolları olayı
 * aynı işlemde ekler; sıra id (neden → sonuç), akış okuyucusu için hangi
 * işlemde yazıldığı (islem) — bitmemiş işlemin olayı atlanmasın.
 *
 * Eski veri görünümden bir kez kopyalanır (son hâl; geçmiş zaten yoktu).
 * ziyaret_olaylari aynı adla tablonun görünümü olarak kalır (Rapor P4).
 */
final class Version20261109120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'M5a: yalnız ekleme olay deposu (olaylar), eski görünümden doldurma';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE olaylar (
                id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                ziyaret_id UUID NOT NULL REFERENCES huni_ziyaretleri (id) ON DELETE CASCADE,
                app TEXT NOT NULL,
                ad TEXT NOT NULL,
                veri JSONB NOT NULL DEFAULT '{}',
                zaman TIMESTAMPTZ NOT NULL DEFAULT now(),
                islem XID8 NOT NULL DEFAULT pg_current_xact_id()
            )
            SQL);
        $this->addSql('CREATE INDEX olaylar_ziyaret ON olaylar (ziyaret_id, id)');
        $this->addSql('CREATE INDEX olaylar_app ON olaylar (app, id)');
        // Yalnız ekleme: geçmiş yeniden yazılmaz. Silme açık — ziyaret silinince (CASCADE).
        $this->addSql(<<<'SQL'
            CREATE FUNCTION olaylar_degismez() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'olaylar yalnız ekleme alır (güncelleme yok)';
            END $$
            SQL);
        $this->addSql('CREATE TRIGGER olaylar_degismez BEFORE UPDATE ON olaylar FOR EACH ROW EXECUTE FUNCTION olaylar_degismez()');
        $this->addSql(<<<'SQL'
            INSERT INTO olaylar (ziyaret_id, app, ad, veri, zaman)
            SELECT ziyaret_id, app, ad, veri, zaman FROM ziyaret_olaylari
            ORDER BY zaman, ziyaret_id::text COLLATE "C", ad COLLATE "C", veri::text COLLATE "C"
            SQL);
        $this->addSql('DROP VIEW ziyaret_olaylari');
        $this->addSql('CREATE VIEW ziyaret_olaylari (ziyaret_id, app, ad, veri, zaman) AS SELECT ziyaret_id, app, ad, veri, zaman FROM olaylar');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW ziyaret_olaylari');
        $this->addSql('DROP TABLE olaylar');
        $this->addSql('DROP FUNCTION olaylar_degismez()');
        // T4 görünümü (Version20261103100000) aynen.
        $this->addSql(<<<'SQL'
            CREATE VIEW ziyaret_olaylari (ziyaret_id, app, ad, veri, zaman) AS
            SELECT id, app, 'huni.ziyaret', jsonb_strip_nulls(jsonb_build_object('huni', huni, 'surum', surum)), created_at FROM huni_ziyaretleri
            UNION ALL SELECT id, app, 'izin.verildi', '{}'::jsonb, izin_at FROM huni_ziyaretleri WHERE izin_at IS NOT NULL
            UNION ALL SELECT id, app, 'izin.reddedildi', '{}'::jsonb, izin_red_at FROM huni_ziyaretleri WHERE izin_red_at IS NOT NULL
            UNION ALL SELECT a.ziyaret_id, z.app, 'adim.goruldu', jsonb_build_object('adim', a.adim, 'sira', a.sira), a.created_at
              FROM huni_adimlari a JOIN huni_ziyaretleri z ON z.id = a.ziyaret_id
            UNION ALL SELECT c.ziyaret_id, z.app, 'cevap.verildi', jsonb_build_object('soru', c.soru, 'secenekler', jsonb_agg(c.secenek ORDER BY c.secenek COLLATE "C")), max(c.updated_at)
              FROM huni_cevaplari c JOIN huni_ziyaretleri z ON z.id = c.ziyaret_id GROUP BY c.ziyaret_id, z.app, c.soru
            UNION ALL SELECT m.ziyaret_id, z.app, 'cevap.verildi', jsonb_build_object('soru', m.soru), m.updated_at
              FROM huni_metin_cevaplari m JOIN huni_ziyaretleri z ON z.id = m.ziyaret_id
            UNION ALL SELECT o.ziyaret_id, z.app, 'odeme.baslatildi', '{}'::jsonb, o.created_at
              FROM odeme_oturumlari o JOIN huni_ziyaretleri z ON z.id = o.ziyaret_id
            UNION ALL SELECT o.ziyaret_id, z.app, 'odeme.tamamlandi', '{}'::jsonb, o.tamamlandi_at
              FROM odeme_oturumlari o JOIN huni_ziyaretleri z ON z.id = o.ziyaret_id WHERE o.tamamlandi_at IS NOT NULL
            UNION ALL SELECT DISTINCT o.ziyaret_id, z.app, 'abonelik.durum', jsonb_build_object('durum', s.status), s.updated_at
              FROM odeme_oturumlari o JOIN huni_ziyaretleri z ON z.id = o.ziyaret_id
              JOIN subscriptions s ON s.stripe_subscription_id = o.stripe_subscription_id
            SQL);
    }
}
