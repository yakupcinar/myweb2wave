<?php

declare(strict_types=1);

namespace App\Mcp;

use Doctrine\DBAL\Connection;

/**
 * Kişisel API anahtarları (A5a, B6): MCP istemcisi (Claude Code, Claude
 * Desktop…) `Authorization: Bearer <anahtar>` ile bağlanır. Tek şirket, SaaS
 * değil: anahtar bir uygulamaya bağlıdır, araçların kapsamı odur. Anahtar
 * yalnız oluşturulurken bir kez gösterilir; veritabanında SHA-256 özeti
 * (yüksek entropili rastgele değer — yavaş özet gerekmez).
 */
final readonly class ApiAnahtarlari
{
    public const ONEK = 'mw2w_';

    public function __construct(
        private Connection $db,
    ) {
    }

    /**
     * M7c2 (A21): $yonetim = abonelik ve deney yazma araçları (yalnız bilerek verilir).
     *
     * @return array{id: int, anahtar: string} anahtarın kendisi yalnız burada döner
     */
    public function olustur(string $app, string $ad, bool $yonetim = false): array
    {
        $anahtar = self::ONEK.bin2hex(random_bytes(24));
        /** @var int|string $id */
        $id = $this->db->fetchOne(
            'INSERT INTO api_anahtarlari (app, ad, ozet, onek, yonetim) VALUES (:app, :ad, :ozet, :onek, :yonetim) RETURNING id',
            ['app' => $app, 'ad' => $ad, 'ozet' => hash('sha256', $anahtar), 'onek' => substr($anahtar, 0, 11), 'yonetim' => $yonetim],
            ['yonetim' => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );

        return ['id' => (int) $id, 'anahtar' => $anahtar];
    }

    /**
     * Geçerli (iptal edilmemiş) anahtarın sahibi; son kullanım işlenir.
     *
     * @return array{id: int, app: string, yonetim: bool}|null
     */
    public function dogrula(string $anahtar): ?array
    {
        if (!str_starts_with($anahtar, self::ONEK)) {
            return null;
        }
        /** @var array{id: int|string, app: string, yonetim: bool}|false $satir */
        $satir = $this->db->fetchAssociative(
            'UPDATE api_anahtarlari SET son_kullanim_at = now() WHERE ozet = :ozet AND iptal_at IS NULL RETURNING id, app, yonetim',
            ['ozet' => hash('sha256', $anahtar)],
        );

        return false === $satir ? null : ['id' => (int) $satir['id'], 'app' => $satir['app'], 'yonetim' => true === $satir['yonetim']];
    }

    /** @return list<array{id: int, ad: string, onek: string, olusturma: string, sonKullanim: string|null, iptal: bool, yonetim: bool}> */
    public function liste(string $app): array
    {
        /** @var list<array{id: int|string, ad: string, onek: string, created_at: string, son_kullanim_at: string|null, iptal_at: string|null, yonetim: bool}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            'SELECT id, ad, onek, created_at, son_kullanim_at, iptal_at, yonetim FROM api_anahtarlari WHERE app = :app ORDER BY iptal_at IS NOT NULL, created_at DESC',
            ['app' => $app],
        );

        return array_map(static fn (array $s): array => [
            'id' => (int) $s['id'],
            'ad' => $s['ad'],
            'onek' => $s['onek'],
            'olusturma' => $s['created_at'],
            'sonKullanim' => $s['son_kullanim_at'],
            'iptal' => null !== $s['iptal_at'],
            'yonetim' => $s['yonetim'],
        ], $satirlar);
    }

    /** Geri alınamaz; kayıt (çağrı geçmişi) kalır. */
    public function iptal(string $app, int $id): bool
    {
        return 1 === $this->db->executeStatement(
            'UPDATE api_anahtarlari SET iptal_at = now() WHERE id = :id AND app = :app AND iptal_at IS NULL',
            ['id' => $id, 'app' => $app],
        );
    }

    /**
     * P7d (A25): yalnız iptal edilmiş anahtar silinir; çağrı kayıtları da
     * gider (yalnız bu anahtara bağlı, başka yerde okunmuyor). Etkin anahtar
     * önce iptal edilir — tek tıkla kalıcı kayıp olmasın.
     */
    public function sil(string $app, int $id): bool
    {
        return $this->db->transactional(function (Connection $db) use ($app, $id): bool {
            $var = $db->fetchOne('SELECT 1 FROM api_anahtarlari WHERE id = :id AND app = :app AND iptal_at IS NOT NULL FOR UPDATE', ['id' => $id, 'app' => $app]);
            if (false === $var) {
                return false;
            }
            $db->executeStatement('DELETE FROM mcp_cagrilari WHERE anahtar_id = :id', ['id' => $id]);
            $db->executeStatement('DELETE FROM api_anahtarlari WHERE id = :id', ['id' => $id]);

            return true;
        });
    }

    public function cagriKaydet(int $anahtarId, string $arac, bool $hata): void
    {
        $this->db->insert('mcp_cagrilari', ['anahtar_id' => $anahtarId, 'arac' => $arac, 'hata' => $hata ? 'true' : 'false']);
    }
}
