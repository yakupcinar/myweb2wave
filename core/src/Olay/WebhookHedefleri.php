<?php

declare(strict_types=1);

namespace App\Olay;

use Doctrine\DBAL\Connection;

/**
 * M7b (A20): panelden giden webhook hedefleri. Sır yalnız eklemede bir kez
 * gösterilir (imza için düz saklanır; listede son 4 hanesi). Adres https;
 * olaylar sözlükten. Teslim günlüğü ve yeniden gönderme (deneme sıfırlanır).
 */
final readonly class WebhookHedefleri
{
    public const AZAMI_HEDEF = 10;
    public const GUNLUK = 50;

    public function __construct(private Connection $db)
    {
    }

    /**
     * @phpstan-impure
     *
     * @return list<array{id: string, adres: string, olaylar: list<string>, aktif: bool, sirSonu: string, bekleyen: int, teslim: int, vazgecildi: int}>
     */
    public function liste(string $app): array
    {
        /** @var list<array{id: string, adres: string, olaylar: string, aktif: bool, sir: string, bekleyen: int, teslim: int, vazgecildi: int}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            "SELECT h.id, h.adres, h.olaylar::text AS olaylar, h.aktif, h.sir,
                    count(t.id) FILTER (WHERE t.durum = 'bekliyor') AS bekleyen,
                    count(t.id) FILTER (WHERE t.durum = 'teslim') AS teslim,
                    count(t.id) FILTER (WHERE t.durum = 'vazgecildi') AS vazgecildi
             FROM webhook_hedefleri h LEFT JOIN webhook_teslimleri t ON t.hedef_id = h.id
             WHERE h.app = :app GROUP BY h.id ORDER BY h.created_at",
            ['app' => $app],
        );

        return array_map(static fn (array $h): array => [
            'id' => $h['id'],
            'adres' => $h['adres'],
            'olaylar' => self::dizi($h['olaylar']),
            'aktif' => $h['aktif'],
            'sirSonu' => substr($h['sir'], -4),
            'bekleyen' => $h['bekleyen'],
            'teslim' => $h['teslim'],
            'vazgecildi' => $h['vazgecildi'],
        ], $satirlar);
    }

    /**
     * @return array{id: string, sir: string}|list<string> eklenen ya da hatalar
     */
    public function ekle(string $app, mixed $adres, mixed $olaylar): array
    {
        $hatalar = [];
        if (!\is_string($adres) || 1 !== preg_match('~^https://[^\s/?#]+[^\s]*$~', $adres) || \strlen($adres) > 2000) {
            $hatalar[] = 'Adres https:// ile başlamalı.';
        }
        $sozluk = array_keys(Olaylar::SOZLUK);
        $olaylar = \is_array($olaylar) && array_is_list($olaylar) && [] === array_filter($olaylar, static fn (mixed $o): bool => !\is_string($o)) ? $olaylar : null;
        /** @var list<string>|null $olaylar */
        if (null === $olaylar || [] === $olaylar || [] !== array_diff($olaylar, $sozluk) || \count($olaylar) !== \count(array_unique($olaylar))) {
            $hatalar[] = 'En az bir olay seçin (sözlükten).';
        }
        $sayi = $this->db->fetchOne('SELECT count(*) FROM webhook_hedefleri WHERE app = ?', [$app]);
        if (is_numeric($sayi) && (int) $sayi >= self::AZAMI_HEDEF) {
            $hatalar[] = 'Uygulama başına en çok '.self::AZAMI_HEDEF.' hedef.';
        }
        if ([] !== $hatalar || !\is_string($adres) || null === $olaylar) {
            return $hatalar;
        }
        $sir = GidenWebhook::yeniSir();
        $secilen = array_values(array_intersect($sozluk, $olaylar));
        $id = $this->db->fetchOne(
            'INSERT INTO webhook_hedefleri (app, adres, olaylar, sir) VALUES (?, ?, CAST(? AS text[]), ?) RETURNING id',
            [$app, $adres, '{'.implode(',', $secilen).'}', $sir],
        );

        \assert(\is_string($id));

        return ['id' => $id, 'sir' => $sir];
    }

    public function durum(string $app, string $id, bool $aktif): bool
    {
        return 1 === $this->db->executeStatement('UPDATE webhook_hedefleri SET aktif = :a WHERE id = :id AND app = :app', ['a' => $aktif, 'id' => $id, 'app' => $app], ['a' => \Doctrine\DBAL\ParameterType::BOOLEAN]);
    }

    public function sil(string $app, string $id): bool
    {
        return 1 === $this->db->executeStatement('DELETE FROM webhook_hedefleri WHERE id = :id AND app = :app', ['id' => $id, 'app' => $app]);
    }

    /**
     * @phpstan-impure
     *
     * @return list<array{id: int, olay: string, durum: string, deneme: int, sonHata: ?string, zaman: string}>|null null: hedef yok
     */
    public function teslimler(string $app, string $id): ?array
    {
        if (false === $this->db->fetchOne('SELECT 1 FROM webhook_hedefleri WHERE id = :id AND app = :app', ['id' => $id, 'app' => $app])) {
            return null;
        }

        /** @var list<array{id: int, ad: string, durum: string, deneme: int, son_hata: ?string, zaman: string}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            "SELECT t.id, o.ad, t.durum, t.deneme, t.son_hata, to_char(t.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"+00:00\"') AS zaman
             FROM webhook_teslimleri t JOIN olaylar o ON o.id = t.olay_id WHERE t.hedef_id = :id ORDER BY t.id DESC LIMIT ".self::GUNLUK,
            ['id' => $id],
        );

        return array_map(static fn (array $t): array => [
            'id' => $t['id'],
            'olay' => $t['ad'],
            'durum' => $t['durum'],
            'deneme' => $t['deneme'],
            'sonHata' => $t['son_hata'],
            'zaman' => $t['zaman'],
        ], $satirlar);
    }

    /** Teslimi yeniden sıraya koyar (deneme sıfır, hemen); teslim edilmiş olsa da. */
    public function yenidenGonder(string $app, string $hedef, int $teslim): bool
    {
        return 1 === $this->db->executeStatement(
            "UPDATE webhook_teslimleri t SET durum = 'bekliyor', deneme = 0, sonraki_deneme_at = now(), son_hata = NULL
             FROM webhook_hedefleri h WHERE t.id = :t AND t.hedef_id = :h AND h.id = t.hedef_id AND h.app = :app",
            ['t' => $teslim, 'h' => $hedef, 'app' => $app],
        );
    }

    /** @return list<string> */
    private static function dizi(string $pg): array
    {
        $ic = trim($pg, '{}');

        return '' === $ic ? [] : explode(',', $ic);
    }
}
