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

    /**
     * P7a2: panelde sunucu sıralaması (sayfalı listede istemci yalnız bir
     * sayfayı görür). Boş değer küçük sayılır: artanda başta, azalanda sonda.
     * Durum, paneldeki Türkçe sırayla: Aktif, Denemede, İptal edildi, Ödeme
     * sorunlu, Bitti, Bekliyor.
     */
    public const SIRALAR = [
        'email' => 's.email COLLATE "C"',
        'durum' => "CASE WHEN s.iptal_donem_sonu AND s.status IN ('active', 'trialing', 'past_due') THEN 2
                         WHEN s.status = 'active' THEN 0 WHEN s.status = 'trialing' THEN 1
                         WHEN s.status IN ('past_due', 'unpaid') THEN 3 WHEN s.status IN ('canceled', 'incomplete_expired') THEN 4 ELSE 5 END",
        'kaynak' => 'z.utm_source COLLATE "C"',
        'sonOdeme' => 'f.odendi_at',
    ];

    /** liste ve say aynı süzgeç (iki kopya ayrışmasın). */
    private const GOVDE = <<<'SQL'
        FROM subscriptions s
        LEFT JOIN LATERAL (
            SELECT para_birimi, tutar_kucuk, iade_kucuk, odendi_at FROM odeme_faturalari
            WHERE stripe_subscription_id = s.stripe_subscription_id ORDER BY odendi_at DESC, stripe_invoice_id DESC LIMIT 1
        ) f ON true
        LEFT JOIN LATERAL (
            SELECT z.* FROM odeme_oturumlari o JOIN huni_ziyaretleri z ON z.id = o.ziyaret_id
            WHERE o.stripe_subscription_id = s.stripe_subscription_id
               OR o.stripe_checkout_session_id = s.stripe_checkout_session_id
            ORDER BY o.created_at LIMIT 1
        ) z ON true
        LEFT JOIN kisiler k ON lower(k.email) = lower(s.email)
        WHERE (:durum = '' OR s.status = :durum) AND (CAST(:app AS text) IS NULL OR s.app = CAST(:app AS text))
          AND (:ara = '' OR lower(s.email) LIKE :desen OR lower(z.utm_source) LIKE :desen OR lower(z.utm_campaign) LIKE :desen)
        SQL;

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
     *                    iptalDonemSonu: bool, donemSonu: ?string, duraklatildi: bool, duraklatmaBitis: ?string, sonFatura: array{paraBirimi: string, tutar: int|float, iade: int|float, odendi: string|null}|null}>
     */
    public function liste(string $ara, string $durum, ?string $app = null, int $sayfa = 1, int $boyut = self::AZAMI_SATIR, string $sirala = '', bool $artan = true): array
    {
        $sira = isset(self::SIRALAR[$sirala])
            ? self::SIRALAR[$sirala].($artan ? ' ASC NULLS FIRST' : ' DESC NULLS LAST').', '
            : '';
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
            // M6c: bitişi geçmiş duraklatma sürmüş sayılır (Stripe sürdürür; hak ile aynı kural).
            'duraklatildi' => true === $s['durakli'],
            'duraklatmaBitis' => true === $s['durakli'] ? self::dizgeYaDaNull($s['duraklatma_bitis']) : null,
            'sonFatura' => \is_string($s['f_para']) ? ['paraBirimi' => strtoupper($s['f_para']), 'tutar' => ParaBirimi::anaBirim(self::sayi($s['f_tutar']), $s['f_para']),
                'iade' => ParaBirimi::anaBirim(self::sayi($s['f_iade']), $s['f_para']),
                // P7a: panel "€6,99 · 3 Kas" ve iade onayında "3 Kasım faturası" diye yazar.
                'odendi' => self::dizgeYaDaNull($s['f_odendi'])] : null,
        ], $this->db->fetchAllAssociative(
            'SELECT s.stripe_subscription_id AS abonelik, s.app, s.status AS durum, s.email,
                    '.$zaman('s.updated_at').' AS guncelleme, '.$zaman('s.odeme_tamamlandi_at').' AS odeme,
                    z.utm_source, z.utm_medium, z.utm_campaign, z.huni, COALESCE('.Rapor::REKLAM.', false) AS reklam,
                    k.id IS NOT NULL AS kisi,
                    EXISTS (SELECT 1 FROM baglantilar b WHERE b.kisi_id = k.id AND b.app = s.app) AS bagli,
                    s.iptal_donem_sonu, '.$zaman('s.donem_sonu').' AS donem_sonu,
                    s.duraklatildi AND (s.duraklatma_bitis IS NULL OR s.duraklatma_bitis > now()) AS durakli, '.$zaman('s.duraklatma_bitis').' AS duraklatma_bitis,
                    f.para_birimi AS f_para, f.tutar_kucuk AS f_tutar, f.iade_kucuk AS f_iade, '.$zaman('f.odendi_at').' AS f_odendi
             '.self::GOVDE.'
             ORDER BY '.$sira.'s.updated_at DESC, s.stripe_subscription_id
             LIMIT :boyut OFFSET :atla',
            [...self::parametreler($ara, $durum, $app), 'boyut' => $boyut, 'atla' => ($sayfa - 1) * $boyut],
        ));
    }

    /** P7a2: süzgece uyan abonelik sayısı (sayfalamanın "/ 312"si). */
    public function say(string $ara, string $durum, ?string $app = null): int
    {
        return self::sayi($this->db->fetchOne('SELECT count(*) '.self::GOVDE, self::parametreler($ara, $durum, $app)));
    }

    /** @return array{durum: string, ara: string, desen: string, app: ?string} */
    private static function parametreler(string $ara, string $durum, ?string $app): array
    {
        // ILIKE jokerleri yazı olarak aranır (% her şeyi bulmasın).
        return ['durum' => $durum, 'ara' => trim($ara), 'desen' => '%'.addcslashes(mb_strtolower(trim($ara)), '\\%_').'%', 'app' => $app];
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
