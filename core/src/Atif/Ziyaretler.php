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
        if (1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ziyaret)) {
            return false;
        }

        return 1 === $this->db->executeStatement(
            'INSERT INTO odeme_oturumlari (stripe_checkout_session_id, ziyaret_id)
             SELECT :oturum, id FROM huni_ziyaretleri WHERE id = :ziyaret AND app = :app',
            ['oturum' => $oturum, 'ziyaret' => $ziyaret, 'app' => $app],
        );
    }
}
