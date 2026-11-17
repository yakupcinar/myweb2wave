<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T2: özellik deposu — ziyaretin bütün verisi tek ad uzayında
 * (ziyaret_id, ad, deger, zaman). Görünüm: kaynak tablolar tek doğru,
 * ikinci yazım yok (eş tutulacak kopya yok). Sözlük: App\Ozellik\Ozellikler.
 * Tıklama kimlikleri (fbclid/ttclid) ve IP/tarayıcı bilerek yok — kişisel
 * eşleme verisi, koşul ve CRM için gerekmiyor.
 */
final class Version20261102130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T2: ziyaret_ozellikleri görünümü (özellik deposu)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE VIEW ziyaret_ozellikleri (ziyaret_id, ad, deger, zaman) AS '.self::sorgu(''));
    }

    /**
     * Görünümün sorgusu; $siralama seçenek listesinin sıralaması (T2b:
     * Version20261102140000 COLLATE "C" ile değiştirir — bu göç aynı kalır).
     */
    public static function sorgu(string $siralama): string
    {
        $utm = '';
        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $alan) {
            $utm .= "
            UNION ALL SELECT id, 'utm.{$alan}', to_jsonb(utm_{$alan}), created_at FROM huni_ziyaretleri WHERE utm_{$alan} IS NOT NULL";
        }

        return "
            SELECT id, 'huni', to_jsonb(huni), created_at FROM huni_ziyaretleri WHERE huni IS NOT NULL
            UNION ALL SELECT id, 'huni.surum', to_jsonb(surum), created_at FROM huni_ziyaretleri WHERE surum IS NOT NULL{$utm}
            -- Son karar geçerli: kabul ve red ikisi de yazılıysa sonraki.
            UNION ALL SELECT id, 'izin.reklam',
                   to_jsonb(CASE WHEN izin_at IS NOT NULL AND (izin_red_at IS NULL OR izin_at > izin_red_at) THEN 'evet' ELSE 'hayir' END),
                   greatest(izin_at, izin_red_at)
              FROM huni_ziyaretleri WHERE izin_at IS NOT NULL OR izin_red_at IS NOT NULL
            -- Seçmeli cevap her zaman liste (tekli de) — koşul 'içerir' tek biçimle çalışsın.
            UNION ALL SELECT ziyaret_id, 'cevap.' || soru, jsonb_agg(secenek ORDER BY secenek{$siralama}), max(updated_at)
              FROM huni_cevaplari GROUP BY ziyaret_id, soru
            UNION ALL SELECT ziyaret_id, 'cevap.' || soru, to_jsonb(cevap), updated_at FROM huni_metin_cevaplari
            -- Ulaşılan en ileri adım (geri dönüş sırayı düşürmez).
            UNION ALL SELECT * FROM (SELECT DISTINCT ON (ziyaret_id) ziyaret_id, 'adim.son', to_jsonb(adim), created_at
              FROM huni_adimlari ORDER BY ziyaret_id, sira DESC) son
            UNION ALL SELECT ziyaret_id, 'odeme.durum',
                   to_jsonb(CASE WHEN bool_or(tamamlandi_at IS NOT NULL) THEN 'tamamlandi' ELSE 'baslatildi' END),
                   max(coalesce(tamamlandi_at, created_at))
              FROM odeme_oturumlari GROUP BY ziyaret_id
            UNION ALL SELECT * FROM (SELECT DISTINCT ON (o.ziyaret_id) o.ziyaret_id, 'abonelik.durum', to_jsonb(s.status), s.updated_at
              FROM odeme_oturumlari o JOIN subscriptions s ON s.stripe_subscription_id = o.stripe_subscription_id
              ORDER BY o.ziyaret_id, s.updated_at DESC) abonelik";
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP VIEW ziyaret_ozellikleri');
    }
}
