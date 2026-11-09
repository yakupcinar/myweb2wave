<?php

declare(strict_types=1);

namespace App\Huni;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * M4a (A17): deneyler. Yönlendirme deneyi bir hunin trafiğini varyant
 * hunilerine böler; içerik deneyi hunin içinde `deney.<ad>` koşuluyla varyant
 * gösterir. Atama saklanmaz: ziyaretçinin sekme tohumu + deney adının
 * SHA-256'sından (0–99 kova) üretilir — aynı tohum hep aynı varyant.
 *
 * Durum: taslak (düzenlenir) → aktif ↔ durdu → bitti (son). Taslak dışında
 * varyantlar kilitli; taslağa dönüş yok (sonuçlar karışmasın).
 */
final readonly class Deneyler
{
    public const TOHUM = '/^[A-Za-z0-9-]{8,64}$/';
    private const VARYANT = '/^[A-Za-z0-9_-]{1,20}$/';
    private const GECISLER = ['taslak' => ['aktif'], 'aktif' => ['durdu', 'bitti'], 'durdu' => ['aktif', 'bitti'], 'bitti' => []];

    public function __construct(
        private Connection $db,
        private Huniler $huniler,
    ) {
    }

    /**
     * Veritabanından canlı okur — iki çağrı farklı sonuç verebilir.
     *
     * @phpstan-impure
     *
     * @return list<array{ad: string, tur: string, huni: string, varyantlar: list<array{ad: string, pay: int, huni?: string}>, durum: string, baslangic: string|null, bitis: string|null}>
     */
    public function liste(string $app): array
    {
        /** @var list<array{ad: string, tur: string, huni: string, varyantlar: string, durum: string, baslangic: string|null, bitis: string|null}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative(
            "SELECT ad, tur, huni, varyantlar, durum,
                    to_char(baslangic_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS baslangic,
                    to_char(bitis_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS bitis
             FROM deneyler WHERE app = :app ORDER BY created_at DESC, ad",
            ['app' => $app],
        );

        return array_map(static fn (array $s): array => ['varyantlar' => self::varyantlar($s['varyantlar'])] + $s, $satirlar);
    }

    /**
     * Yeni ya da taslaktaki deneyi yazar (taslak dışındaki kilitli).
     *
     * @return list<string> hatalar
     */
    public function kaydet(string $app, mixed $ad, mixed $tur, mixed $huni, mixed $varyantlar): array
    {
        $h = [];
        if (!\is_string($ad) || 1 !== preg_match(Tanim::KIMLIK, $ad)) {
            $h[] = 'Deney adı küçük harf, rakam, - ve _ (1–64) olmalı — koşulda deney.<ad> olarak kullanılır.';
        }
        if (!\in_array($tur, ['yonlendirme', 'icerik'], true)) {
            $h[] = 'Tür yonlendirme ya da icerik olmalı.';
        }
        if (!\is_string($huni) || null === $this->huniler->taslak($app, $huni)) {
            $h[] = 'Huni bu uygulamada yok.';
        }
        $h = [...$h, ...$this->varyantHatalari($app, $tur, $huni, $varyantlar)];
        if ([] !== $h || !\is_string($ad) || !\is_string($tur) || !\is_string($huni) || !\is_array($varyantlar)) {
            return $h;
        }
        $mevcut = $this->db->fetchOne('SELECT durum FROM deneyler WHERE app = :app AND ad = :ad', ['app' => $app, 'ad' => $ad]);
        if (\is_string($mevcut) && 'taslak' !== $mevcut) {
            return ["'{$ad}' deneyi {$mevcut} — yalnız taslak düzenlenir (sonuçlar karışmasın)."];
        }
        $this->db->executeStatement(
            'INSERT INTO deneyler (app, ad, tur, huni, varyantlar) VALUES (:app, :ad, :tur, :huni, CAST(:v AS jsonb))
             ON CONFLICT (app, ad) DO UPDATE SET tur = EXCLUDED.tur, huni = EXCLUDED.huni, varyantlar = EXCLUDED.varyantlar',
            ['app' => $app, 'ad' => $ad, 'tur' => $tur, 'huni' => $huni, 'v' => json_encode(array_values($varyantlar), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)],
        );

        return [];
    }

    /**
     * Durum geçişi; aktife geçerken huniler yayında olmalı.
     *
     * @return list<string>|null null = deney yok
     */
    public function durum(string $app, string $ad, mixed $yeni): ?array
    {
        /** @var array{durum: string, tur: string, huni: string, varyantlar: string}|false $d */
        $d = $this->db->fetchAssociative('SELECT durum, tur, huni, varyantlar FROM deneyler WHERE app = :app AND ad = :ad', ['app' => $app, 'ad' => $ad]);
        if (false === $d) {
            return null;
        }
        if (!\is_string($yeni) || !\in_array($yeni, self::GECISLER[$d['durum']] ?? [], true)) {
            return ["{$d['durum']} → ".(\is_string($yeni) ? $yeni : '?').' geçişi yok (taslak → aktif ↔ durdu → bitti).'];
        }
        if ('aktif' === $yeni) {
            $huniler = 'yonlendirme' === $d['tur'] ? array_column(self::varyantlar($d['varyantlar']), 'huni') : [$d['huni']];
            foreach ($huniler as $hn) {
                if (null === $this->huniler->yayinda($app, $hn)) {
                    return ["'{$hn}' hunisi yayında değil — önce yayınla."];
                }
            }
        }
        try {
            $this->db->executeStatement(
                'UPDATE deneyler SET durum = :durum,
                        baslangic_at = coalesce(baslangic_at, CASE WHEN :durum = \'aktif\' THEN now() END),
                        bitis_at = CASE WHEN :durum = \'bitti\' THEN now() ELSE bitis_at END
                 WHERE app = :app AND ad = :ad',
                ['durum' => $yeni, 'app' => $app, 'ad' => $ad],
            );
        } catch (UniqueConstraintViolationException) {
            return ["'{$d['huni']}' hunisinde zaten aktif bir yönlendirme deneyi var."];
        }

        return [];
    }

    /**
     * Yönlendirme: bu hunide aktif deney varsa tohumun varyantının hunisi.
     *
     * @return array{huni: string, atama: array<string, string>}
     */
    public function yonlendir(string $app, string $huni, string $tohum): array
    {
        /** @var array{ad: string, varyantlar: string}|false $d */
        $d = $this->db->fetchAssociative("SELECT ad, varyantlar FROM deneyler WHERE app = :app AND huni = :huni AND tur = 'yonlendirme' AND durum = 'aktif'", ['app' => $app, 'huni' => $huni]);
        if (false === $d) {
            return ['huni' => $huni, 'atama' => []];
        }
        $v = self::sec(self::varyantlar($d['varyantlar']), $d['ad'], $tohum);

        return ['huni' => $v['huni'] ?? $huni, 'atama' => [$d['ad'] => $v['ad']]];
    }

    /** @return array<string, string> içerik deneyi adı → varyant (bu hunin aktifleri) */
    public function icerik(string $app, string $huni, string $tohum): array
    {
        /** @var list<array{ad: string, varyantlar: string}> $satirlar */
        $satirlar = $this->db->fetchAllAssociative("SELECT ad, varyantlar FROM deneyler WHERE app = :app AND huni = :huni AND tur = 'icerik' AND durum = 'aktif' ORDER BY ad", ['app' => $app, 'huni' => $huni]);
        $atama = [];
        foreach ($satirlar as $d) {
            $atama[$d['ad']] = self::sec(self::varyantlar($d['varyantlar']), $d['ad'], $tohum)['ad'];
        }

        return $atama;
    }

    /**
     * Ziyaretin atamalarını yazar — yalnız ziyaretin hunisindeki aktif
     * deneyin geçerli varyantı (istemciden gelir, güvenilmez; eski sekme
     * başka hunin atamasını taşıyabilir).
     *
     * @param array<mixed> $atama
     */
    public function ziyaretKaydet(string $ziyaret, string $app, ?string $huni, array $atama): void
    {
        foreach ($atama as $ad => $varyant) {
            $d = \is_string($ad) && \is_string($varyant) && null !== $huni
                ? $this->db->fetchAssociative("SELECT tur, huni, varyantlar FROM deneyler WHERE app = :app AND ad = :ad AND durum = 'aktif'", ['app' => $app, 'ad' => $ad])
                : false;
            if (false === $d) {
                continue;
            }
            $varyantlar = self::varyantlar($d['varyantlar']);
            $secili = array_values(array_filter($varyantlar, static fn (array $v): bool => $v['ad'] === $varyant))[0] ?? null;
            // İçerik deneyinin varyantında huni yoktur; onun geçerliliği deneyin
            // kendi hunisiyle ölçülür. Önceki kod her iki türü de yönlendirme
            // eşlemesiyle doğruladığından içerik atamalarını sessizce atıyordu.
            $gecerliHuni = 'yonlendirme' === $d['tur']
                ? $secili['huni'] ?? null
                : $d['huni'];
            if (null === $secili || $huni !== $gecerliHuni) {
                continue;
            }
            $this->db->executeStatement(
                'INSERT INTO ziyaret_deneyleri (ziyaret_id, deney, varyant) VALUES (:z, :d, :v) ON CONFLICT DO NOTHING',
                ['z' => $ziyaret, 'd' => $ad, 'v' => $varyant],
            );
        }
    }

    /**
     * Tohum + deney adının özeti → 0–99 kova → payların birikimli dilimi.
     *
     * @param list<array{ad: string, pay: int, huni?: string}> $varyantlar
     *
     * @return array{ad: string, pay: int, huni?: string}
     */
    public static function sec(array $varyantlar, string $deney, string $tohum): array
    {
        $kova = hexdec(substr(hash('sha256', $deney.'|'.$tohum), 0, 8)) % 100;
        $toplam = 0;
        foreach ($varyantlar as $v) {
            $toplam += $v['pay'];
            if ($kova < $toplam) {
                return $v;
            }
        }

        return $varyantlar[0];
    }

    /** @return list<array{ad: string, pay: int, huni?: string}> */
    private static function varyantlar(mixed $json): array
    {
        $v = \is_string($json) ? json_decode($json, true) : $json;
        $liste = [];
        foreach (\is_array($v) ? $v : [] as $x) {
            if (\is_array($x) && \is_string($x['ad'] ?? null) && \is_int($x['pay'] ?? null)) {
                $liste[] = ['ad' => $x['ad'], 'pay' => $x['pay']] + (\is_string($x['huni'] ?? null) ? ['huni' => $x['huni']] : []);
            }
        }

        return $liste;
    }

    /** @return list<string> */
    private function varyantHatalari(string $app, mixed $tur, mixed $huni, mixed $varyantlar): array
    {
        if (!\is_array($varyantlar) || !array_is_list($varyantlar) || \count($varyantlar) < 2 || \count($varyantlar) > 4) {
            return ['Varyantlar 2–4 öğeli liste olmalı.'];
        }
        $h = [];
        $adlar = [];
        $huniler = [];
        $toplam = 0;
        foreach ($varyantlar as $i => $v) {
            $yer = 'Varyant '.($i + 1);
            $alanlar = 'yonlendirme' === $tur ? ['ad', 'pay', 'huni'] : ['ad', 'pay'];
            if (!\is_array($v) || [] !== array_diff(array_keys($v), $alanlar) || [] !== array_diff($alanlar, array_keys($v))) {
                $h[] = "{$yer}: alanlar tam olarak ".implode(', ', $alanlar).' olmalı.';
                continue;
            }
            if (!\is_string($v['ad']) || 1 !== preg_match(self::VARYANT, $v['ad'])) {
                $h[] = "{$yer}: ad harf, rakam, - ve _ (1–20) olmalı.";
            } else {
                $adlar[] = $v['ad'];
            }
            if (!\is_int($v['pay']) || $v['pay'] < 1 || $v['pay'] > 99) {
                $h[] = "{$yer}: pay 1–99 tam sayı (yüzde) olmalı.";
            } else {
                $toplam += $v['pay'];
            }
            if ('yonlendirme' === $tur) {
                if (!\is_string($v['huni']) || null === $this->huniler->taslak($app, $v['huni'])) {
                    $h[] = "{$yer}: huni bu uygulamada yok.";
                } elseif (0 === $i && $v['huni'] !== $huni) {
                    $h[] = "{$yer}: ilk varyant deneyin hunisi olmalı (kontrol grubu).";
                } else {
                    $huniler[] = $v['huni'];
                }
            }
        }
        if (\count(array_unique($adlar)) !== \count($adlar)) {
            $h[] = 'Varyant adları farklı olmalı.';
        }
        if ([] === $h && 100 !== $toplam) {
            $h[] = "Payların toplamı 100 olmalı (şu an {$toplam}).";
        }
        if ('yonlendirme' === $tur && [] === $h && \count(array_unique($huniler)) !== \count($varyantlar)) {
            $h[] = 'Yönlendirme varyantlarının hunileri farklı olmalı.';
        }

        return $h;
    }
}
