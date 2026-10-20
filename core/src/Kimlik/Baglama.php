<?php

declare(strict_types=1);

namespace App\Kimlik;

use App\RevenueCat\Aktarim;
use Doctrine\DBAL\Connection;

/**
 * Doğrulanmış bir e-postayı kişiye, uygulamadaki kullanıcıyı da o kişiye
 * bağlar (A5). İki yol da buradan geçer: e-posta kodu ve ödeme sonrası
 * giriş bağlantısı (Ç46) — kural ikisinde ayrı yazılırsa kayar.
 */
final readonly class Baglama
{
    public function __construct(
        private Aktarim $revenuecat,
    ) {
    }

    /** Çağıranın işlemi içinde çalışır; kişi UUID'ini döndürür. */
    public function bagla(Connection $db, string $app, string $email, string $kullanici): string
    {
        $kisi = $db->fetchOne(
            'INSERT INTO kisiler (email) VALUES (?) ON CONFLICT (email) DO UPDATE SET email = EXCLUDED.email RETURNING id',
            [$email],
        );
        if (!\is_string($kisi)) {
            throw new \LogicException('kisiler INSERT … RETURNING id dizge döndürmedi');
        }
        $db->executeStatement(
            'INSERT INTO baglantilar (app, harici_kullanici, kisi_id) VALUES (?, ?, ?)
             ON CONFLICT (app, harici_kullanici) DO UPDATE SET kisi_id = EXCLUDED.kisi_id',
            [$app, $kullanici, $kisi],
        );
        // A9: bu kişinin bu uygulamadaki abonelikleri RevenueCat'e (kullanıcı = kişi UUID'i).
        $this->revenuecat->kisiyiKuyrugaAl($db, $app, $kisi);

        return $kisi;
    }
}
