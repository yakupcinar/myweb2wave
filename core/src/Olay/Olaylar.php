<?php

declare(strict_types=1);

namespace App\Olay;

use Doctrine\DBAL\Connection;

/**
 * T4 olay deposu: ziyaretin zaman çizelgesi ve uygulamanın olay akışı
 * (`ziyaret_olaylari` görünümü, göç Version20261103100000). Raporlar,
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
        'izin.verildi' => ['', 'reklam ölçümü izni verildi (son karar)'],
        'izin.reddedildi' => ['', 'reklam ölçümü izni reddedildi (son karar)'],
        'adim.goruldu' => ['adim, sira', 'adım ilk kez gösterildi'],
        'cevap.verildi' => ['soru, secenekler?', 'cevap (son hâli); serbest yanıtta yalnız soru'],
        'odeme.baslatildi' => ['', 'ödeme oturumu açıldı'],
        'odeme.tamamlandi' => ['', 'ödeme tamamlandı'],
        'abonelik.durum' => ['durum', 'Stripe abonelik durumu (son hâli)'],
    ];

    private const UUID = '/^[0-9a-f-]{36}$/';

    /** Sıra: zaman, ziyaret, ad, veri — aynı andaki olaylar da ayrılır. */
    private const SIRA = 'zaman, ziyaret_id::text COLLATE "C", ad COLLATE "C", veri::text COLLATE "C"';

    public function __construct(private readonly Connection $db)
    {
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
            self::secim().' WHERE ziyaret_id = :z ORDER BY '.self::SIRA,
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
        $kosul = 'app = :app';
        $p = ['app' => $app, 'n' => $sinir + 1];
        if (null !== $imlec) {
            $son = json_decode((string) base64_decode($imlec, true), true);
            if (!\is_array($son) || 4 !== \count($son) || !array_is_list($son) || [] !== array_filter($son, static fn (mixed $d): bool => !\is_string($d))) {
                return null;
            }
            $kosul .= ' AND ROW('.self::SIRA.') > ROW(:t::timestamptz, :z COLLATE "C", :a COLLATE "C", :v COLLATE "C")';
            [$p['t'], $p['z'], $p['a'], $p['v']] = $son;
        }
        $satirlar = self::satirlar($this->db->fetchAllAssociative(self::secim()." WHERE {$kosul} ORDER BY ".self::SIRA.' LIMIT :n', $p));
        $devam = \count($satirlar) > $sinir;
        $satirlar = \array_slice($satirlar, 0, $sinir);
        $sonuncu = end($satirlar);

        return [
            'olaylar' => array_map(static fn (array $o): array => ['ziyaret' => $o['ziyaret'], 'ad' => $o['ad'], 'veri' => $o['veri'], 'zaman' => $o['zaman']], $satirlar),
            'imlec' => $devam && false !== $sonuncu ? base64_encode(json_encode([$sonuncu['tam'], $sonuncu['ziyaret'], $sonuncu['ad'], $sonuncu['veriMetni']], \JSON_THROW_ON_ERROR)) : null,
        ];
    }

    private static function secim(): string
    {
        // Tam zaman (mikro saniye, UTC) imleç için; görünen zaman saniyeye kadar.
        return "SELECT ziyaret_id::text AS ziyaret, ad, veri::text AS veri,
                to_char(zaman AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"') AS tam,
                to_char(zaman AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"+00:00\"') AS zaman
                FROM ziyaret_olaylari";
    }

    /**
     * @param list<array<string, mixed>> $satirlar
     *
     * @return list<array{ziyaret: string, ad: string, veri: array<string, mixed>, veriMetni: string, tam: string, zaman: string}>
     */
    private static function satirlar(array $satirlar): array
    {
        return array_map(static function (array $s): array {
            \assert(\is_string($s['ziyaret']) && \is_string($s['ad']) && \is_string($s['veri']) && \is_string($s['tam']) && \is_string($s['zaman']));
            $veri = [];
            foreach ((array) json_decode($s['veri'], true, 16, \JSON_THROW_ON_ERROR) as $k => $d) {
                $veri[(string) $k] = $d;
            }

            return ['ziyaret' => $s['ziyaret'], 'ad' => $s['ad'], 'veri' => $veri, 'veriMetni' => $s['veri'], 'tam' => $s['tam'], 'zaman' => $s['zaman']];
        }, $satirlar);
    }
}
