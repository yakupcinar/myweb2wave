<?php

declare(strict_types=1);

namespace App\Panel;

use App\Billing\ParaBirimi;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Panelin verisi (A6/2): huni ziyareti → ödeme oturumu → abonelik.
 * Satırlar ve sayımlar aynı "reklam mı" tanımını kullanır (tek yer: REKLAM).
 * Ç118: "ödedi" = para çekildi (ODEDI); abone olup para çekilmeyen (kartlı
 * deneme — Stripe bunu da "paid" der, tutar 0) "deneme başladı". Deneyler
 * (M4b) aynı tanımı kullanır: abone = abonelik_at, ödedi = ODEDI.
 * M5c: gelir tahsil edilen faturalardan (M5b), para birimi başına, çevrilmez;
 * M6a: iade düşülür (kohortta faturanın ödeme anında).
 */
final readonly class Rapor
{
    /** Tıklama kimliği ya da ücretli UTM ortamı varsa reklam; yoksa organik. */
    public const REKLAM = "(z.fbclid IS NOT NULL OR z.ttclid IS NOT NULL
        OR lower(z.utm_medium) IN ('cpc', 'ppc', 'paid', 'paid_social', 'paidsocial', 'display', 'ads'))";

    /**
     * M5c: oturum ($o = odeme_oturumlari) para çekti mi — ilk ödeme tutarı ya da
     * aboneliğin tahsil edilmiş faturası (deneme sonrası ilk ödeme dahil).
     * Faturalar tabloya yalnız tutar > 0 ile girer.
     */
    public const ODEDI = '(o.tutar_kucuk > 0 OR EXISTS (SELECT 1 FROM odeme_faturalari f WHERE f.stripe_subscription_id = o.stripe_subscription_id))';

    /** Satırlar ve UTM kırılımı aynı "kaynak" tanımını kullanır. */
    public const KAYNAK = "coalesce(z.utm_source, CASE WHEN z.fbclid IS NOT NULL THEN 'meta (fbclid)' WHEN z.ttclid IS NOT NULL THEN 'tiktok (ttclid)' ELSE 'doğrudan' END)";

    /**
     * M5d: ziyaret Meta reklamından mı — tıklama kimliği ya da Meta kaynağı.
     * Aynı adlı başka ağ kampanyası Meta harcamasına yazılmasın.
     */
    public const META = "(z.fbclid IS NOT NULL OR lower(z.utm_source) IN ('meta', 'facebook', 'fb', 'instagram', 'ig'))";

    /** Kohort pencereleri (gün); 0 = ilk 24 saat. */
    public const KOHORT_GUNLERI = [0, 7, 30, 60];

    public const GUNLER = [7, 30, 90];
    public const AZAMI_SATIR = 200;

    public function __construct(
        private Connection $db,
    ) {
    }

    /**
     * @return list<array{zaman: string, app: string, huni: string|null, kaynak: string, kampanya: string|null, reklam: bool,
     *                    asama: string, durum: string|null, email: string|null}>
     */
    public function satirlar(int $gun): array
    {
        $satirlar = $this->db->fetchAllAssociative(
            'SELECT z.created_at, z.app, z.huni, '.self::KAYNAK.' AS kaynak, z.utm_campaign, '.self::REKLAM.' AS reklam,
                    EXISTS (SELECT 1 FROM odeme_oturumlari o WHERE o.ziyaret_id = z.id) AS odemeye_gecti,
                    od.ziyaret_id IS NOT NULL AS abone, coalesce(od.odedi, false) AS odedi, od.status, od.email
             FROM huni_ziyaretleri z
             LEFT JOIN LATERAL (
                 SELECT o.ziyaret_id, '.self::ODEDI.' AS odedi, s.status, s.email FROM odeme_oturumlari o
                 LEFT JOIN subscriptions s ON s.stripe_subscription_id = o.stripe_subscription_id
                 WHERE o.ziyaret_id = z.id AND o.abonelik_at IS NOT NULL
                 ORDER BY 2 DESC NULLS LAST, o.abonelik_at DESC LIMIT 1
             ) od ON true
             WHERE z.created_at >= now() - make_interval(days => :gun)
             ORDER BY z.created_at DESC
             LIMIT '.self::AZAMI_SATIR,
            ['gun' => $gun],
        );

        return array_map(static fn (array $s): array => [
            'zaman' => self::dizge($s['created_at']),
            'app' => self::dizge($s['app']),
            'huni' => self::dizgeYaDaNull($s['huni']),
            'kaynak' => self::dizge($s['kaynak']),
            'kampanya' => self::dizgeYaDaNull($s['utm_campaign']),
            'reklam' => true === $s['reklam'],
            'asama' => true === $s['odedi'] ? 'ödedi' : (true === $s['abone'] ? 'deneme başladı' : (true === $s['odemeye_gecti'] ? 'ödemeye geçti' : 'sayfayı gördü')),
            'durum' => self::dizgeYaDaNull($s['status']),
            'email' => self::maskele(self::dizgeYaDaNull($s['email'])),
        ], $satirlar);
    }

    /**
     * Ziyaret başına: deneme = abone ama hiçbir oturumunda para çekilmedi;
     * deneme + odedi = abone olan ziyaret.
     *
     * @return array{reklam: array{ziyaret: int, odemeye_gecti: int, deneme: int, odedi: int}, organik: array{ziyaret: int, odemeye_gecti: int, deneme: int, odedi: int}}
     */
    public function sayimlar(int $gun, ?string $app = null): array
    {
        $sayim = ['reklam' => ['ziyaret' => 0, 'odemeye_gecti' => 0, 'deneme' => 0, 'odedi' => 0], 'organik' => ['ziyaret' => 0, 'odemeye_gecti' => 0, 'deneme' => 0, 'odedi' => 0]];
        $satirlar = $this->db->fetchAllAssociative(
            'SELECT '.self::REKLAM.' AS reklam, count(*) AS ziyaret,
                    count(*) FILTER (WHERE EXISTS (SELECT 1 FROM odeme_oturumlari o WHERE o.ziyaret_id = z.id)) AS odemeye_gecti,
                    count(*) FILTER (WHERE od.abone AND NOT od.odedi) AS deneme,
                    count(*) FILTER (WHERE od.odedi) AS odedi
             FROM huni_ziyaretleri z
             CROSS JOIN LATERAL (
                 SELECT coalesce(bool_or(o.abonelik_at IS NOT NULL), false) AS abone, coalesce(bool_or('.self::ODEDI.'), false) AS odedi
                 FROM odeme_oturumlari o WHERE o.ziyaret_id = z.id
             ) od
             WHERE z.created_at >= now() - make_interval(days => :gun) AND (CAST(:app AS text) IS NULL OR z.app = CAST(:app AS text))
             GROUP BY 1',
            ['gun' => $gun, 'app' => $app],
        );
        foreach ($satirlar as $s) {
            $sayim[true === $s['reklam'] ? 'reklam' : 'organik'] = [
                'ziyaret' => self::sayi($s['ziyaret']),
                'odemeye_gecti' => self::sayi($s['odemeye_gecti']),
                'deneme' => self::sayi($s['deneme']),
                'odedi' => self::sayi($s['odedi']),
            ];
        }

        return $sayim;
    }

    /**
     * P4: aralığın her UTC takvim günü (en eski önce) — ziyaret, ödeme
     * başlatıldı/tamamlandı; boş gün 0. Olay deposundan (T4), aynı adlar.
     *
     * @return list<array{gun: string, ziyaret: int, odemeBaslatildi: int, odemeTamamlandi: int}>
     */
    public function gunluk(int $gun, ?string $app = null): array
    {
        return array_map(static fn (array $s): array => [
            'gun' => \is_string($s['gun']) ? $s['gun'] : '',
            'ziyaret' => self::sayi($s['ziyaret']),
            'odemeBaslatildi' => self::sayi($s['baslatildi']),
            'odemeTamamlandi' => self::sayi($s['tamamlandi']),
        ], $this->db->fetchAllAssociative(
            "WITH bugun AS (SELECT (now() AT TIME ZONE 'UTC')::date AS g)
             SELECT to_char(d, 'YYYY-MM-DD') AS gun,
                    count(o.ad) FILTER (WHERE o.ad = 'huni.ziyaret') AS ziyaret,
                    count(o.ad) FILTER (WHERE o.ad = 'odeme.baslatildi') AS baslatildi,
                    count(o.ad) FILTER (WHERE o.ad = 'odeme.tamamlandi') AS tamamlandi
             FROM bugun, generate_series(bugun.g - (CAST(:gun AS int) - 1), bugun.g, interval '1 day') d
             LEFT JOIN ziyaret_olaylari o
               ON o.ad IN ('huni.ziyaret', 'odeme.baslatildi', 'odeme.tamamlandi')
              AND o.zaman >= d AT TIME ZONE 'UTC' AND o.zaman < (d + interval '1 day') AT TIME ZONE 'UTC'
              AND (CAST(:app AS text) IS NULL OR o.app = CAST(:app AS text))
             GROUP BY d ORDER BY d",
            ['gun' => $gun, 'app' => $app],
        ));
    }

    /**
     * Ç56 (3): reklam ölçümü izni — kabul / ret / seçmeyen (yalnız sayı).
     *
     * @return array{kabul: int, red: int, secmeyen: int}
     */
    public function izinler(int $gun, ?string $app = null): array
    {
        $s = $this->db->fetchAssociative(
            'SELECT count(*) FILTER (WHERE izin_at IS NOT NULL) AS kabul,
                    count(*) FILTER (WHERE izin_red_at IS NOT NULL) AS red,
                    count(*) FILTER (WHERE izin_at IS NULL AND izin_red_at IS NULL) AS secmeyen
             FROM huni_ziyaretleri WHERE created_at >= now() - make_interval(days => :gun)
               AND (CAST(:app AS text) IS NULL OR app = CAST(:app AS text))',
            ['gun' => $gun, 'app' => $app],
        );

        return [
            'kabul' => self::sayi($s['kabul'] ?? 0),
            'red' => self::sayi($s['red'] ?? 0),
            'secmeyen' => self::sayi($s['secmeyen'] ?? 0),
        ];
    }

    /**
     * Anket hunisi: her adıma kaç ziyaret ulaştı (uygulama + huni başına, A8).
     * Adım adı huni tanımından gelir; tanım değiştiyse aynı sıradaki en son ad.
     *
     * @return list<array{app: string, huni: string, sira: int, adim: string, ulasan: int}>
     */
    public function adimlar(int $gun): array
    {
        return array_map(static fn (array $s): array => [
            'app' => self::dizge($s['app']),
            'huni' => self::dizgeYaDaNull($s['huni']) ?? '—',
            'sira' => self::sayi($s['sira']),
            'adim' => self::dizge($s['adim']),
            'ulasan' => self::sayi($s['ulasan']),
        ], $this->db->fetchAllAssociative(
            'SELECT z.app, z.huni, a.sira, (array_agg(a.adim ORDER BY a.created_at DESC))[1] AS adim, count(*) AS ulasan
             FROM huni_adimlari a JOIN huni_ziyaretleri z ON z.id = a.ziyaret_id
             WHERE z.created_at >= now() - make_interval(days => :gun)
             GROUP BY z.app, z.huni, a.sira
             ORDER BY z.app, z.huni NULLS LAST, a.sira',
            ['gun' => $gun],
        ));
    }

    /**
     * M5c: seçenek başına seçen ziyaret ve bunlardan abone olan (deneme dahil —
     * dönüşüm tanımı deneylerle aynı, A17). Cevap son hâli.
     *
     * @return list<array{app: string, soru: string, secenek: string, sayi: int, abone: int}>
     */
    public function cevaplar(int $gun, ?string $app = null): array
    {
        return array_map(static fn (array $s): array => [
            'app' => self::dizge($s['app']),
            'soru' => self::dizge($s['soru']),
            'secenek' => self::dizge($s['secenek']),
            'sayi' => self::sayi($s['sayi']),
            'abone' => self::sayi($s['abone']),
        ], $this->db->fetchAllAssociative(
            'SELECT z.app, c.soru, c.secenek, count(*) AS sayi,
                    count(*) FILTER (WHERE EXISTS (SELECT 1 FROM odeme_oturumlari o WHERE o.ziyaret_id = c.ziyaret_id AND o.abonelik_at IS NOT NULL)) AS abone
             FROM huni_cevaplari c JOIN huni_ziyaretleri z ON z.id = c.ziyaret_id
             WHERE z.created_at >= now() - make_interval(days => :gun) AND (CAST(:app AS text) IS NULL OR z.app = CAST(:app AS text))
             GROUP BY z.app, c.soru, c.secenek
             ORDER BY z.app, c.soru, sayi DESC, c.secenek',
            ['gun' => $gun, 'app' => $app],
        ));
    }

    /**
     * M5c: kaynak / ortam / kampanya başına huni — aralıkta gelen ziyaretler;
     * gelir bu ziyaretlerin bugüne kadar tahsil edilmiş faturaları (para birimi
     * başına, ana birim). En çok ziyaretli AZAMI_SATIR grup.
     *
     * @return list<array{kaynak: string, ortam: string|null, kampanya: string|null, reklam: bool, ziyaret: int, odemeye_gecti: int, deneme: int, odedi: int,
     *                    gelir: list<array{paraBirimi: string, tutar: int|float}>}>
     */
    public function utm(int $gun, ?string $app = null): array
    {
        // Gelir ayrı sorguda, grup anahtarıyla PHP'de birleşir (ilişkili alt sorgu
        // grup × ziyaret tarardı). REKLAM organikte NULL olabilir — iki sorguda aynı.
        $v = 'WITH v AS (
                 SELECT z.id, '.self::KAYNAK.' AS kaynak, z.utm_medium AS ortam, z.utm_campaign AS kampanya, '.self::REKLAM.' AS reklam
                 FROM huni_ziyaretleri z WHERE z.created_at >= now() - make_interval(days => :gun) AND (CAST(:app AS text) IS NULL OR z.app = CAST(:app AS text))
             ) ';
        $gelir = [];
        foreach ($this->db->fetchAllAssociative(
            $v.'SELECT v.kaynak, v.ortam, v.kampanya, v.reklam, f.para_birimi, sum(f.tutar_kucuk - f.iade_kucuk) AS toplam
             FROM v JOIN odeme_oturumlari o ON o.ziyaret_id = v.id JOIN odeme_faturalari f ON f.stripe_subscription_id = o.stripe_subscription_id
             GROUP BY 1, 2, 3, 4, 5 ORDER BY f.para_birimi',
            ['gun' => $gun, 'app' => $app],
        ) as $s) {
            $para = self::dizge($s['para_birimi']);
            $gelir[self::grup($s)][] = ['paraBirimi' => strtoupper($para), 'tutar' => ParaBirimi::anaBirim(self::sayi($s['toplam']), $para)];
        }

        return array_map(static fn (array $s): array => [
            'kaynak' => self::dizge($s['kaynak']),
            'ortam' => self::dizgeYaDaNull($s['ortam']),
            'kampanya' => self::dizgeYaDaNull($s['kampanya']),
            'reklam' => true === $s['reklam'],
            'ziyaret' => self::sayi($s['ziyaret']),
            'odemeye_gecti' => self::sayi($s['gecti']),
            'deneme' => self::sayi($s['deneme']),
            'odedi' => self::sayi($s['odedi']),
            'gelir' => $gelir[self::grup($s)] ?? [],
        ], $this->db->fetchAllAssociative(
            $v.'SELECT v.kaynak, v.ortam, v.kampanya, v.reklam, count(*) AS ziyaret, count(*) FILTER (WHERE od.gecti) AS gecti,
                    count(*) FILTER (WHERE od.abone AND NOT od.odedi) AS deneme, count(*) FILTER (WHERE od.odedi) AS odedi
             FROM v CROSS JOIN LATERAL (
                 SELECT count(o.*) > 0 AS gecti, coalesce(bool_or(o.abonelik_at IS NOT NULL), false) AS abone, coalesce(bool_or('.self::ODEDI.'), false) AS odedi
                 FROM odeme_oturumlari o WHERE o.ziyaret_id = v.id
             ) od
             GROUP BY 1, 2, 3, 4
             ORDER BY ziyaret DESC, v.kaynak COLLATE "C", v.ortam COLLATE "C" NULLS FIRST, v.kampanya COLLATE "C" NULLS FIRST
             LIMIT '.self::AZAMI_SATIR,
            ['gun' => $gun, 'app' => $app],
        ));
    }

    /**
     * M5d: Meta kampanyası başına harcama (aralıktaki günler) ve aynı adlı
     * utm_campaign ile Meta'dan gelen ziyaretlerin geliri (aralıkta gelen,
     * bugüne kadar). ROAS yalnız hesabın para biriminde — başka para birimi
     * gelir listesinde görünür, çevrilmez. Kampanya adı değişirse son ad.
     *
     * @return list<array{kampanya: string, harcama: array{paraBirimi: string, tutar: int|float}, ziyaret: int, odedi: int,
     *                    gelir: list<array{paraBirimi: string, tutar: int|float}>, roas: float|null}>
     */
    public function kampanyalar(int $gun, ?string $app = null): array
    {
        $harcamalar = $this->db->fetchAllAssociative(
            "SELECT (array_agg(kampanya_adi ORDER BY gun DESC))[1] AS ad, para_birimi, sum(harcama_kucuk) AS harcama
             FROM reklam_harcamalari
             WHERE saglayici = 'meta' AND gun > (now() AT TIME ZONE 'UTC')::date - CAST(:gun AS int)
             GROUP BY kampanya_id, para_birimi ORDER BY harcama DESC, kampanya_id COLLATE \"C\"",
            ['gun' => $gun],
        );
        $adlar = array_values(array_unique(array_map(static fn (array $h): string => self::dizge($h['ad']), $harcamalar)));
        $ziyaret = [];
        $gelir = [];
        if ([] !== $adlar) {
            $v = 'WITH v AS (SELECT z.id, z.utm_campaign AS ad FROM huni_ziyaretleri z
                   WHERE z.utm_campaign IN (:adlar) AND z.created_at >= now() - make_interval(days => :gun) AND (CAST(:app AS text) IS NULL OR z.app = CAST(:app AS text)) AND '.self::META.') ';
            foreach ($this->db->fetchAllAssociative(
                $v.'SELECT v.ad, count(*) AS ziyaret, count(*) FILTER (WHERE EXISTS (SELECT 1 FROM odeme_oturumlari o WHERE o.ziyaret_id = v.id AND '.self::ODEDI.')) AS odedi FROM v GROUP BY 1',
                ['adlar' => $adlar, 'gun' => $gun, 'app' => $app], ['adlar' => ArrayParameterType::STRING],
            ) as $s) {
                $ziyaret[self::dizge($s['ad'])] = [self::sayi($s['ziyaret']), self::sayi($s['odedi'])];
            }
            foreach ($this->db->fetchAllAssociative(
                $v.'SELECT v.ad, f.para_birimi, sum(f.tutar_kucuk - f.iade_kucuk) AS toplam FROM v JOIN odeme_oturumlari o ON o.ziyaret_id = v.id
                 JOIN odeme_faturalari f ON f.stripe_subscription_id = o.stripe_subscription_id GROUP BY 1, 2 ORDER BY 2',
                ['adlar' => $adlar, 'gun' => $gun, 'app' => $app], ['adlar' => ArrayParameterType::STRING],
            ) as $s) {
                $gelir[self::dizge($s['ad'])][self::dizge($s['para_birimi'])] = self::sayi($s['toplam']);
            }
        }

        return array_map(static function (array $h) use ($ziyaret, $gelir): array {
            $ad = self::dizge($h['ad']);
            $para = self::dizge($h['para_birimi']);
            $harcama = self::sayi($h['harcama']);
            $tutarlar = $gelir[$ad] ?? [];

            return [
                'kampanya' => $ad,
                'harcama' => ['paraBirimi' => strtoupper($para), 'tutar' => ParaBirimi::anaBirim($harcama, $para)],
                'ziyaret' => $ziyaret[$ad][0] ?? 0,
                'odedi' => $ziyaret[$ad][1] ?? 0,
                'gelir' => array_map(static fn (string $p, int $t): array => ['paraBirimi' => strtoupper($p), 'tutar' => ParaBirimi::anaBirim($t, $p)], array_keys($tutarlar), $tutarlar),
                // Aynı para birimindeki gelir / harcama; harcama 0 ise tanımsız.
                'roas' => $harcama > 0 ? round(($tutarlar[$para] ?? 0) / $harcama, 2) : null,
            ];
        }, $harcamalar);
    }

    /** @param array<string, mixed> $s */
    private static function grup(array $s): string
    {
        return json_encode([$s['kaynak'], $s['ortam'], $s['kampanya'], $s['reklam']], \JSON_THROW_ON_ERROR);
    }

    /**
     * M5c: kohort = ziyaretin UTC haftası (pazartesi). Her pencerede ziyaret
     * başına birikimli gelir: fatura ödeme anı < ziyaret + pencere (0 = ilk 24
     * saat). Pencere haftanın son ziyareti için de dolmadıysa olgun değil
     * (sayı yok) — yarım pencere geliri düşük gösterirdi.
     *
     * @return list<array{hafta: string, ziyaret: int, pencereler: list<array{gun: int, olgun: bool, kisiBasi: list<array{paraBirimi: string, tutar: int|float}>}>}>
     */
    public function kohort(int $gun, ?string $app = null): array
    {
        $haftalar = $this->db->fetchAllAssociative(
            "SELECT to_char(date_trunc('week', z.created_at AT TIME ZONE 'UTC'), 'YYYY-MM-DD') AS hafta, count(*) AS ziyaret,
                    extract(epoch FROM now() - max(z.created_at)) AS en_genc_sn
             FROM huni_ziyaretleri z WHERE z.created_at >= now() - make_interval(days => :gun) AND (CAST(:app AS text) IS NULL OR z.app = CAST(:app AS text))
             GROUP BY 1 ORDER BY 1 DESC",
            ['gun' => $gun, 'app' => $app],
        );
        $gelir = [];
        foreach ($this->db->fetchAllAssociative(
            "SELECT to_char(date_trunc('week', z.created_at AT TIME ZONE 'UTC'), 'YYYY-MM-DD') AS hafta, f.para_birimi, p.gun,
                    sum(f.tutar_kucuk - f.iade_kucuk) AS toplam
             FROM huni_ziyaretleri z
             JOIN odeme_oturumlari o ON o.ziyaret_id = z.id
             JOIN odeme_faturalari f ON f.stripe_subscription_id = o.stripe_subscription_id
             CROSS JOIN unnest(CAST(:pencereler AS int[])) AS p(gun)
             WHERE z.created_at >= now() - make_interval(days => :gun) AND (CAST(:app AS text) IS NULL OR z.app = CAST(:app AS text))
               AND f.odendi_at < z.created_at + make_interval(days => greatest(p.gun, 1))
             GROUP BY 1, 2, 3",
            ['gun' => $gun, 'app' => $app, 'pencereler' => '{'.implode(',', self::KOHORT_GUNLERI).'}'],
        ) as $s) {
            $gelir[self::dizge($s['hafta'])][self::sayi($s['gun'])][self::dizge($s['para_birimi'])] = self::sayi($s['toplam']);
        }

        return array_map(static function (array $h) use ($gelir): array {
            $hafta = self::dizge($h['hafta']);
            $ziyaret = self::sayi($h['ziyaret']);
            $yas = is_numeric($h['en_genc_sn']) ? (float) $h['en_genc_sn'] : 0.0;

            return ['hafta' => $hafta, 'ziyaret' => $ziyaret, 'pencereler' => array_map(static function (int $g) use ($gelir, $hafta, $ziyaret, $yas): array {
                $olgun = $yas >= max($g, 1) * 86400;
                $tutarlar = $gelir[$hafta][$g] ?? [];
                ksort($tutarlar, \SORT_STRING);
                $kisiBasi = [];
                foreach ($tutarlar as $para => $toplam) {
                    $kisiBasi[] = ['paraBirimi' => strtoupper((string) $para), 'tutar' => round(ParaBirimi::anaBirim($toplam, (string) $para) / $ziyaret, 2)];
                }

                return ['gun' => $g, 'olgun' => $olgun, 'kisiBasi' => $olgun ? $kisiBasi : []];
            }, self::KOHORT_GUNLERI)];
        }, $haftalar);
    }

    /** @return list<array{zaman: string, app: string, soru: string, cevap: string}> */
    public function metinCevaplari(int $gun): array
    {
        return array_map(static fn (array $s): array => [
            'zaman' => self::dizge($s['created_at']),
            'app' => self::dizge($s['app']),
            'soru' => self::dizge($s['soru']),
            'cevap' => self::dizge($s['cevap']),
        ], $this->db->fetchAllAssociative(
            'SELECT z.created_at, z.app, c.soru, c.cevap
             FROM huni_metin_cevaplari c JOIN huni_ziyaretleri z ON z.id = c.ziyaret_id
             WHERE z.created_at >= now() - make_interval(days => :gun)
             ORDER BY c.updated_at DESC
             LIMIT '.self::AZAMI_SATIR,
            ['gun' => $gun],
        ));
    }

    /** Ekran görüntüsünde kişisel veri sızmasın (kullanıcı kararı): a***@alan.com. */
    public static function maskele(?string $email): ?string
    {
        if (null === $email) {
            return null;
        }
        $at = strrpos($email, '@');
        if (false === $at || 0 === $at) {
            return '***';
        }

        return mb_substr($email, 0, 1).'***'.substr($email, $at);
    }

    private static function dizge(mixed $d): string
    {
        return \is_string($d) ? $d : '';
    }

    private static function dizgeYaDaNull(mixed $d): ?string
    {
        return \is_string($d) ? $d : null;
    }

    private static function sayi(mixed $d): int
    {
        return is_numeric($d) ? (int) $d : 0;
    }
}
