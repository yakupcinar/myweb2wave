<?php

declare(strict_types=1);

namespace App\Olay;

use Doctrine\DBAL\Connection;

/**
 * T4 olay deposu: ziyaretin zaman çizelgesi ve uygulamanın olay akışı.
 * M5a (Ç92): yalnız ekleme yapılan `olaylar` tablosu (göç
 * Version20261109120000) — yazma yolları olayı aynı işlemde ekle() ile
 * yazar, yalnız durum DEĞİŞİNCE (aynı cevap ikinci kez olay değil). Raporlar,
 * grafikler (P4), giden webhook ve deneyler aynı akışı okur — ad değişmez,
 * yalnız eklenir. Serbest yanıt metni olaya girmez (Ç90).
 */
final class Olaylar
{
    /**
     * Sözlük: ad → [veri alanları, açıklama].
     *
     * @var array<string, array{string, string}>
     */
    public const SOZLUK = [
        'huni.ziyaret' => ['huni?, surum?', 'ziyaret başladı'],
        'izin.verildi' => ['', 'reklam ölçümü izni verildi (değişince)'],
        'izin.reddedildi' => ['', 'reklam ölçümü izni reddedildi (değişince)'],
        'adim.goruldu' => ['adim, sira', 'adım ilk kez gösterildi'],
        'cevap.verildi' => ['soru, secenekler?', 'cevap (değişince — M5a geçmişi tutar); serbest yanıtta yalnız soru'],
        'odeme.baslatildi' => ['', 'ödeme oturumu açıldı'],
        'odeme.tamamlandi' => ['', 'ödeme tamamlandı'],
        'abonelik.durum' => ['durum', 'Stripe abonelik durumu (değişince)'],
        'odeme.fatura' => ['tutar, paraBirimi, neden', 'Stripe faturası tahsil edildi (tutar > 0, küçük birim; neden: subscription_create / subscription_cycle …)'],
    ];

    private const UUID = '/^[0-9a-f-]{36}$/';

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Çağıranın işleminde olay ekler; uygulama ziyaretten. Ziyaret yoksa
     * hiçbir şey yazmaz.
     *
     * @param array<string, mixed> $veri
     */
    public static function ekle(Connection $db, string $ziyaret, string $ad, array $veri = []): void
    {
        \assert(isset(self::SOZLUK[$ad]), "sözlükte olmayan olay: {$ad}");
        $db->executeStatement(
            'INSERT INTO olaylar (ziyaret_id, app, ad, veri) SELECT id, app, :ad, CAST(:veri AS jsonb) FROM huni_ziyaretleri WHERE id = :z',
            ['z' => $ziyaret, 'ad' => $ad, 'veri' => json_encode((object) $veri, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)],
        );
    }

    /**
     * @phpstan-impure
     *
     * @return list<array{ad: string, veri: array<string, mixed>, zaman: string}>
     */
    public function ziyaretin(string $ziyaret): array
    {
        if (1 !== preg_match(self::UUID, $ziyaret)) {
            return [];
        }

        return array_map(static fn (array $o): array => ['ad' => $o['ad'], 'veri' => $o['veri'], 'zaman' => $o['zaman']], self::satirlar($this->db->fetchAllAssociative(
            self::secim().' WHERE ziyaret_id = :z ORDER BY id',
            ['z' => $ziyaret],
        )));
    }

    /**
     * Uygulamanın olayları imleçten sonra, en çok $sinir tane; imleç null =
     * baştan. Dönen imleç null ise akış bitti. Bozuk imleçte null.
     *
     * @phpstan-impure
     *
     * @return array{olaylar: list<array{ziyaret: string, ad: string, veri: array<string, mixed>, zaman: string}>, imlec: ?string}|null
     */
    public function akis(string $app, ?string $imlec, int $sinir): ?array
    {
        $sinir = max(1, min(1000, $sinir));
        // M5a: sıra id. id işlem içinde alınır, görünürlük commit'te: bitmemiş bir
        // işlemin küçük id'li olayı sonradan görünür, imleç onu çoktan geçmiş
        // olurdu. Yalnız şu an çalışan en eski işlemden önce bitenler okunur.
        $kosul = 'app = :app AND islem < pg_snapshot_xmin(pg_current_snapshot())';
        $p = ['app' => $app, 'n' => $sinir + 1];
        if (null !== $imlec) {
            $son = json_decode((string) base64_decode($imlec, true), true);
            if (!\is_array($son) || 1 !== \count($son) || !array_is_list($son) || !\is_int($son[0])) {
                return null;
            }
            $kosul .= ' AND id > :son';
            $p['son'] = $son[0];
        }
        $satirlar = self::satirlar($this->db->fetchAllAssociative(self::secim()." WHERE {$kosul} ORDER BY id LIMIT :n", $p));
        $devam = \count($satirlar) > $sinir;
        $satirlar = \array_slice($satirlar, 0, $sinir);
        $sonuncu = end($satirlar);

        return [
            'olaylar' => array_map(static fn (array $o): array => ['ziyaret' => $o['ziyaret'], 'ad' => $o['ad'], 'veri' => $o['veri'], 'zaman' => $o['zaman']], $satirlar),
            'imlec' => $devam && false !== $sonuncu ? base64_encode(json_encode([$sonuncu['id']], \JSON_THROW_ON_ERROR)) : null,
        ];
    }

    private static function secim(): string
    {
        return "SELECT id, ziyaret_id::text AS ziyaret, ad, veri::text AS veri,
                to_char(zaman AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"+00:00\"') AS zaman
                FROM olaylar";
    }

    /**
     * @param list<array<string, mixed>> $satirlar
     *
     * @return list<array{id: int, ziyaret: string, ad: string, veri: array<string, mixed>, zaman: string}>
     */
    private static function satirlar(array $satirlar): array
    {
        return array_map(static function (array $s): array {
            \assert(is_numeric($s['id']) && \is_string($s['ziyaret']) && \is_string($s['ad']) && \is_string($s['veri']) && \is_string($s['zaman']));
            $veri = [];
            foreach ((array) json_decode($s['veri'], true, 16, \JSON_THROW_ON_ERROR) as $k => $d) {
                $veri[(string) $k] = $d;
            }

            return ['id' => (int) $s['id'], 'ziyaret' => $s['ziyaret'], 'ad' => $s['ad'], 'veri' => $veri, 'zaman' => $s['zaman']];
        }, $satirlar);
    }
}
