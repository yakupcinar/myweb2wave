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

    /** @param array<value-of<self::ALANLAR>, string|null> $alanlar */
    public function kaydet(string $app, array $alanlar): string
    {
        $id = $this->db->fetchOne(
            'INSERT INTO huni_ziyaretleri (app, utm_source, utm_medium, utm_campaign, utm_content, utm_term, fbclid, ttclid)
             VALUES (:app, :utm_source, :utm_medium, :utm_campaign, :utm_content, :utm_term, :fbclid, :ttclid)
             RETURNING id',
            ['app' => $app] + $alanlar,
        );
        \assert(\is_string($id));

        return $id;
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
