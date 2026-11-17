<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T4: olay deposu görünümü — kaynak tablolardan türetilir, ikinci yazım yok
 * (T2'deki gibi; eş tutulacak kopya olmadığı için tutarsızlık olamaz).
 */
final class Version20261103100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T4: ziyaret_olaylari görünümü (olay deposu)';
    }

    public function up(Schema $schema): void
    {
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

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW ziyaret_olaylari');
    }
}
