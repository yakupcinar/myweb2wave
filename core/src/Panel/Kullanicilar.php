<?php

declare(strict_types=1);

namespace App\Panel;

use App\Billing\ParaBirimi;
use Doctrine\DBAL\Connection;

/**
 * P5 Kullanıcılar (CRM): abonelik başına satır — durum, ödemeyi başlatan
 * ziyaretin kaynağı (reklam tanımı Rapor ile aynı), kişi kaydı ve uygulama
 * bağlantısı. E-posta maskeli (kullanıcı kararı, panel.md); arama tam adresle
 * de bulur ama yanıtta adres yok.
 */
final class Kullanicilar
{
    public const AZAMI_SATIR = 200;

    public function __construct(private readonly Connection $db)
    {
    }

    /** @return list<string> veride geçen durumlar (süzgeç için) */
    public function durumlar(): array
    {
        return array_map(static fn (mixed $d): string => \is_string($d) ? $d : '', $this->db->fetchFirstColumn(
            "SELECT DISTINCT status COLLATE \"C\" AS durum FROM subscriptions WHERE status <> '' ORDER BY 1",
        ));
    }

    /**
     * @phpstan-impure
     *
     * @return list<array{abonelik: string, app: ?string, durum: string, email: ?string, guncelleme: string, odeme: ?string,
     *                    reklam: bool, kaynak: ?string, ortam: ?string, kampanya: ?string, huni: ?string, kisi: bool, bagli: bool,
     *                    iptalDonemSonu: bool, donemSonu: ?string, sonFatura: array{paraBirimi: string, tutar: int|float, iade: int|float}|null}>
     */
    public function liste(string $ara, string $durum, ?string $app = null): array
    {
        // ILIKE jokerleri yazı olarak aranır (% her şeyi bulmasın).
        $desen = '%'.addcslashes(mb_strtolower(trim($ara)), '\\%_').'%';
        $zaman = static fn (string $s): string => "to_char({$s} AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"+00:00\"')";

        return array_map(static fn (array $s): array => [
            'abonelik' => self::dizgeYaDaNull($s['abonelik']) ?? '',
            'app' => self::dizgeYaDaNull($s['app']),
            'durum' => \is_string($s['durum']) ? $s['durum'] : '',
            'email' => Rapor::maskele(self::dizgeYaDaNull($s['email'])),
            'guncelleme' => self::dizgeYaDaNull($s['guncelleme']) ?? '',
            'odeme' => self::dizgeYaDaNull($s['odeme']),
            'reklam' => true === $s['reklam'],
            'kaynak' => self::dizgeYaDaNull($s['utm_source']),
            'ortam' => self::dizgeYaDaNull($s['utm_medium']),
            'kampanya' => self::dizgeYaDaNull($s['utm_campaign']),
            'huni' => self::dizgeYaDaNull($s['huni']),
            'kisi' => true === $s['kisi'],
            'bagli' => true === $s['bagli'],
            // M6a: iptal planı ve iade düğmesi için son tahsil edilen fatura.
            'iptalDonemSonu' => true === $s['iptal_donem_sonu'],
            'donemSonu' => self::dizgeYaDaNull($s['donem_sonu']),
            'sonFatura' => \is_string($s['f_para']) ? ['paraBirimi' => strtoupper($s['f_para']), 'tutar' => ParaBirimi::anaBirim(self::sayi($s['f_tutar']), $s['f_para']),
                'iade' => ParaBirimi::anaBirim(self::sayi($s['f_iade']), $s['f_para'])] : null,
        ], $this->db->fetchAllAssociative(
            'SELECT s.stripe_subscription_id AS abonelik, s.app, s.status AS durum, s.email,
                    '.$zaman('s.updated_at').' AS guncelleme, '.$zaman('s.odeme_tamamlandi_at').' AS odeme,
                    z.utm_source, z.utm_medium, z.utm_campaign, z.huni, COALESCE('.Rapor::REKLAM.', false) AS reklam,
                    k.id IS NOT NULL AS kisi,
                    EXISTS (SELECT 1 FROM baglantilar b WHERE b.kisi_id = k.id AND b.app = s.app) AS bagli,
                    s.iptal_donem_sonu, '.$zaman('s.donem_sonu').' AS donem_sonu, f.para_birimi AS f_para, f.tutar_kucuk AS f_tutar, f.iade_kucuk AS f_iade
             FROM subscriptions s
             LEFT JOIN LATERAL (
                 SELECT para_birimi, tutar_kucuk, iade_kucuk FROM odeme_faturalari
                 WHERE stripe_subscription_id = s.stripe_subscription_id ORDER BY odendi_at DESC, stripe_invoice_id DESC LIMIT 1
             ) f ON true
             LEFT JOIN LATERAL (
                 SELECT z.* FROM odeme_oturumlari o JOIN huni_ziyaretleri z ON z.id = o.ziyaret_id
                 WHERE o.stripe_subscription_id = s.stripe_subscription_id
                    OR o.stripe_checkout_session_id = s.stripe_checkout_session_id
                 ORDER BY o.created_at LIMIT 1
             ) z ON true
             LEFT JOIN kisiler k ON lower(k.email) = lower(s.email)
             WHERE (:durum = \'\' OR s.status = :durum) AND (CAST(:app AS text) IS NULL OR s.app = CAST(:app AS text))
               AND (:ara = \'\' OR lower(s.email) LIKE :desen OR lower(z.utm_source) LIKE :desen OR lower(z.utm_campaign) LIKE :desen)
             ORDER BY s.updated_at DESC, s.stripe_subscription_id
             LIMIT '.self::AZAMI_SATIR,
            ['durum' => $durum, 'ara' => trim($ara), 'desen' => $desen, 'app' => $app],
        ));
    }

    private static function sayi(mixed $d): int
    {
        return is_numeric($d) ? (int) $d : 0;
    }

    private static function dizgeYaDaNull(mixed $d): ?string
    {
        return \is_string($d) && '' !== $d ? $d : null;
    }
}
