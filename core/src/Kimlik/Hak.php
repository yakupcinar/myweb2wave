<?php

declare(strict_types=1);

namespace App\Kimlik;

use Doctrine\DBAL\Connection;

/**
 * "Bu kullanıcının bu uygulamada aktif aboneliği var mı?" (A5).
 * Erişim: active, trialing, past_due — Stripe yeniden denerken kullanıcı
 * kilitlenmez. Kişinin DOĞRULANMIŞ e-postası, uygulamanın aboneliklerindeki
 * e-postayla (küçük harf) eşlenir.
 */
final readonly class Hak
{
    public const ERISIM_VEREN = ['active', 'trialing', 'past_due'];

    public function __construct(private Connection $db)
    {
    }

    /** @return array{aktif: bool, durum: ?string}|null null: kullanıcı bağlanmamış */
    public function sorgula(string $app, string $kullanici): ?array
    {
        $kisi = $this->db->fetchOne(
            'SELECT k.email FROM baglantilar b JOIN kisiler k ON k.id = b.kisi_id WHERE b.app = ? AND b.harici_kullanici = ?',
            [$app, $kullanici],
        );
        if (!\is_string($kisi)) {
            return null;
        }

        $durumlar = $this->db->fetchFirstColumn(
            'SELECT status FROM subscriptions WHERE app = ? AND lower(email) = ? AND status IS NOT NULL ORDER BY updated_at DESC',
            [$app, $kisi],
        );
        foreach (self::ERISIM_VEREN as $erisim) {
            if (\in_array($erisim, $durumlar, true)) {
                return ['aktif' => true, 'durum' => $erisim];
            }
        }
        $son = $durumlar[0] ?? null;

        return ['aktif' => false, 'durum' => \is_string($son) ? $son : null];
    }
}
