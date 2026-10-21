<?php

declare(strict_types=1);

namespace App\Atif;

use Doctrine\DBAL\Connection;

/**
 * Reklam atfı (A6): huni ziyareti → ödeme oturumu → abonelik.
 * Atıf ödemeyi asla durdurmaz: bilinmeyen/başka uygulamanın ziyareti
 * sessizce atlanır, ödeme yine açılır.
 */
final readonly class Ziyaretler
{
    /** Huninin URL'sinden okunan alanlar; başka alan kabul edilmez. */
    public const ALANLAR = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'ttclid'];

    /** Kişiye bağlanabilen tıklama kimlikleri: yalnız izinle saklanır (H6a). */
    public const TIKLAMA = ['fbclid', 'ttclid'];

    /** fbclid ~200 karakter; üstü kötüye kullanım. */
    public const AZAMI_UZUNLUK = 500;

    /** Huni tanımındaki adım, soru ve seçenek kimlikleri (funnel/src/huniler/). */
    public const KIMLIK = '/^[a-z0-9_-]{1,64}$/';

    /** Bir hunide en çok bu kadar adım; üstü kötüye kullanım. */
    public const AZAMI_SIRA = 50;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function __construct(
        private Connection $db,
    ) {
    }

    /**
     * Ziyaretin gördüğü huni ve sürüm (A8) yayınlanmış bir çift değilse ikisi
     * de null yazılır — atıf ziyareti asla durdurmaz.
     *
     * Tıklama kimlikleri yalnız $izin ile yazılır (H6a): izinsiz gelen
     * kimlik — örneğin önbellekteki eski huni betiğinden — sessizce atılır.
     *
     * @param array<value-of<self::ALANLAR>, string|null> $alanlar
     */
    public function kaydet(string $app, array $alanlar, ?string $huni = null, ?int $surum = null, bool $izin = false): string
    {
        if (!$izin) {
            $alanlar = array_merge($alanlar, array_fill_keys(self::TIKLAMA, null));
        }
        $id = $this->db->fetchOne(
            'WITH s AS (SELECT ad, surum FROM huni_surumleri WHERE app = :app AND ad = :huni AND surum = CAST(:surum AS int))
             INSERT INTO huni_ziyaretleri (app, utm_source, utm_medium, utm_campaign, utm_content, utm_term, fbclid, ttclid, huni, surum, izin_at)
             VALUES (:app, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :fbclid, :ttclid,
                     (SELECT ad FROM s), (SELECT surum FROM s), CASE WHEN :izin THEN now() END)
             RETURNING id',
            ['app' => $app, 'huni' => $huni, 'surum' => $surum, 'izin' => $izin] + $alanlar,
            ['izin' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
        \assert(\is_string($id));

        return $id;
    }

    /**
     * Onay şeridinde "Kabul" (H6a): izin anı + o anda adresteki tıklama
     * kimlikleri (önceden yazılmışı silmez). Ziyaret yoksa false.
     *
     * @param array<value-of<self::TIKLAMA>, string|null> $tiklama
     */
    public function izinVer(string $ziyaret, array $tiklama): bool
    {
        if (1 !== preg_match(self::UUID, $ziyaret)) {
            return false;
        }

        return 1 === $this->db->executeStatement(
            'UPDATE huni_ziyaretleri SET izin_at = coalesce(izin_at, now()),
                    fbclid = coalesce(:fbclid, fbclid), ttclid = coalesce(:ttclid, ttclid)
             WHERE id = :id',
            ['id' => $ziyaret, 'fbclid' => $tiklama['fbclid'] ?? null, 'ttclid' => $tiklama['ttclid'] ?? null],
        );
    }

    /** Ziyaret bu uygulamanınsa oturumu ona bağlar; değilse hiçbir şey yazmaz. */
    public function oturumBagla(string $ziyaret, string $app, string $oturum): bool
    {
        if (1 !== preg_match(self::UUID, $ziyaret)) {
            return false;
        }

        return 1 === $this->db->executeStatement(
            'INSERT INTO odeme_oturumlari (stripe_checkout_session_id, ziyaret_id)
             SELECT :oturum, id FROM huni_ziyaretleri WHERE id = :ziyaret AND app = :app',
            ['oturum' => $oturum, 'ziyaret' => $ziyaret, 'app' => $app],
        );
    }

    /**
     * Anket ilerlemesi: adıma ulaşıldı (bir kez sayılır), varsa bu adımın
     * cevabı (son cevap geçerli). Ziyaret yoksa false — hiçbir şey yazılmaz.
     */
    public function ilerle(string $ziyaret, int $sira, string $adim, ?string $cevap): bool
    {
        if (1 !== preg_match(self::UUID, $ziyaret)) {
            return false;
        }

        return $this->db->transactional(function (Connection $db) use ($ziyaret, $sira, $adim, $cevap): bool {
            if (false === $db->fetchOne('SELECT 1 FROM huni_ziyaretleri WHERE id = :id', ['id' => $ziyaret])) {
                return false;
            }
            $db->executeStatement(
                'INSERT INTO huni_adimlari (ziyaret_id, sira, adim) VALUES (:z, :sira, :adim)
                 ON CONFLICT (ziyaret_id, sira) DO NOTHING',
                ['z' => $ziyaret, 'sira' => $sira, 'adim' => $adim],
            );
            if (null !== $cevap) {
                $db->executeStatement(
                    'INSERT INTO huni_cevaplari (ziyaret_id, soru, secenek) VALUES (:z, :soru, :secenek)
                     ON CONFLICT (ziyaret_id, soru) DO UPDATE SET secenek = EXCLUDED.secenek, updated_at = now()',
                    ['z' => $ziyaret, 'soru' => $adim, 'secenek' => $cevap],
                );
            }

            return true;
        });
    }
}
