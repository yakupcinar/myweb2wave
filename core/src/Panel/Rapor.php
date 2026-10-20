<?php

declare(strict_types=1);

namespace App\Panel;

use Doctrine\DBAL\Connection;

/**
 * Panelin verisi (A6/2): huni ziyareti → ödeme oturumu → abonelik.
 * Satırlar ve sayımlar aynı "reklam mı" tanımını kullanır (tek yer: REKLAM).
 */
final readonly class Rapor
{
    /** Tıklama kimliği ya da ücretli UTM ortamı varsa reklam; yoksa organik. */
    private const REKLAM = "(z.fbclid IS NOT NULL OR z.ttclid IS NOT NULL
        OR lower(z.utm_medium) IN ('cpc', 'ppc', 'paid', 'paid_social', 'paidsocial', 'display', 'ads'))";

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
            'SELECT z.created_at, z.app, z.huni, z.utm_source, z.utm_campaign, z.fbclid, z.ttclid, '.self::REKLAM.' AS reklam,
                    EXISTS (SELECT 1 FROM odeme_oturumlari o WHERE o.ziyaret_id = z.id) AS odemeye_gecti,
                    od.ziyaret_id IS NOT NULL AS odedi, od.status, od.email
             FROM huni_ziyaretleri z
             LEFT JOIN LATERAL (
                 SELECT o.ziyaret_id, s.status, s.email FROM odeme_oturumlari o
                 LEFT JOIN subscriptions s ON s.stripe_subscription_id = o.stripe_subscription_id
                 WHERE o.ziyaret_id = z.id AND o.tamamlandi_at IS NOT NULL
                 ORDER BY o.tamamlandi_at DESC LIMIT 1
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
            'kaynak' => self::kaynak($s),
            'kampanya' => self::dizgeYaDaNull($s['utm_campaign']),
            'reklam' => true === $s['reklam'],
            'asama' => true === $s['odedi'] ? 'ödedi' : (true === $s['odemeye_gecti'] ? 'ödemeye geçti' : 'sayfayı gördü'),
            'durum' => self::dizgeYaDaNull($s['status']),
            'email' => self::maskele(self::dizgeYaDaNull($s['email'])),
        ], $satirlar);
    }

    /** @return array{reklam: array{ziyaret: int, odemeye_gecti: int, odedi: int}, organik: array{ziyaret: int, odemeye_gecti: int, odedi: int}} */
    public function sayimlar(int $gun): array
    {
        $sayim = ['reklam' => ['ziyaret' => 0, 'odemeye_gecti' => 0, 'odedi' => 0], 'organik' => ['ziyaret' => 0, 'odemeye_gecti' => 0, 'odedi' => 0]];
        $satirlar = $this->db->fetchAllAssociative(
            'SELECT '.self::REKLAM.' AS reklam, count(*) AS ziyaret,
                    count(*) FILTER (WHERE EXISTS (SELECT 1 FROM odeme_oturumlari o WHERE o.ziyaret_id = z.id)) AS odemeye_gecti,
                    count(*) FILTER (WHERE EXISTS (SELECT 1 FROM odeme_oturumlari o WHERE o.ziyaret_id = z.id AND o.tamamlandi_at IS NOT NULL)) AS odedi
             FROM huni_ziyaretleri z
             WHERE z.created_at >= now() - make_interval(days => :gun)
             GROUP BY 1',
            ['gun' => $gun],
        );
        foreach ($satirlar as $s) {
            $sayim[true === $s['reklam'] ? 'reklam' : 'organik'] = [
                'ziyaret' => self::sayi($s['ziyaret']),
                'odemeye_gecti' => self::sayi($s['odemeye_gecti']),
                'odedi' => self::sayi($s['odedi']),
            ];
        }

        return $sayim;
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

    /** @return list<array{app: string, soru: string, secenek: string, sayi: int}> */
    public function cevaplar(int $gun): array
    {
        return array_map(static fn (array $s): array => [
            'app' => self::dizge($s['app']),
            'soru' => self::dizge($s['soru']),
            'secenek' => self::dizge($s['secenek']),
            'sayi' => self::sayi($s['sayi']),
        ], $this->db->fetchAllAssociative(
            'SELECT z.app, c.soru, c.secenek, count(*) AS sayi
             FROM huni_cevaplari c JOIN huni_ziyaretleri z ON z.id = c.ziyaret_id
             WHERE z.created_at >= now() - make_interval(days => :gun)
             GROUP BY z.app, c.soru, c.secenek
             ORDER BY z.app, c.soru, sayi DESC, c.secenek',
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

    /** @param array<string, mixed> $s */
    private static function kaynak(array $s): string
    {
        return self::dizgeYaDaNull($s['utm_source'])
            ?? (null !== $s['fbclid'] ? 'meta (fbclid)' : (null !== $s['ttclid'] ? 'tiktok (ttclid)' : 'doğrudan'));
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
