<?php

declare(strict_types=1);

namespace App\Yasal;

use App\Uygulama\UygulamaAyarlari;
use Doctrine\DBAL\Connection;

/**
 * M9b1 (A23): yasal veriler (unvan, adres, kayıt no) + belge × dil metinleri.
 *
 * - Metin yalnız yönetici KAYDEDİNCE yayında; kaydedilmemiş belgenin sayfası
 *   yok (404). Panel kaydedilmemiş belgede taslağı (Taslaklar) gösterir.
 * - Yer tutucular ({{unvan}}…) sayfa çizilirken doldurulur: ayar değişince
 *   metin yeniden kaydedilmeden güncellenir. Bilinmeyen yer tutucu kayıtta
 *   reddedilir (yazım hatası sayfada ham kalmasın); boş değer "—".
 * - HTML yok: metin ayrıştırılıp kaçışlanarak çizilir ("## " başlık,
 *   "- " madde, **kalın**, boş satır paragraf).
 */
final readonly class YasalMetinler
{
    /** alan => en çok karakter (göçteki CHECK ve sözleşmeyle aynı). */
    public const VERILER = ['unvan' => 200, 'adres' => 500, 'kayitNo' => 100];
    public const AZAMI = 50000;
    /** yer tutucu => kaynağı */
    private const YER_TUTUCULAR = ['uygulama', 'unvan', 'adres', 'kayitNo', 'eposta'];

    public function __construct(private Connection $db, private UygulamaAyarlari $ayarlar)
    {
    }

    /** @return array{unvan: string, adres: string, kayitNo: string} */
    public function veriler(string $app): array
    {
        $s = $this->db->fetchAssociative('SELECT unvan, adres, kayit_no FROM uygulama_ayarlari WHERE app = ?', [$app]);
        $metin = static fn (string $a): string => \is_array($s) && \is_string($s[$a] ?? null) ? $s[$a] : '';

        return ['unvan' => $metin('unvan'), 'adres' => $metin('adres'), 'kayitNo' => $metin('kayit_no')];
    }

    /** @return list<string> hatalar; boşsa kaydedildi */
    public function verileriKaydet(string $app, mixed $veri): array
    {
        if (!\is_array($veri)) {
            return ['gövde bir nesne olmalı'];
        }
        $hatalar = [];
        if ([] !== $fazla = array_diff(array_map(strval(...), array_keys($veri)), array_keys(self::VERILER))) {
            $hatalar[] = 'bilinmeyen alan: '.implode(', ', $fazla);
        }
        $d = [];
        foreach (self::VERILER as $alan => $azami) {
            $v = $veri[$alan] ?? '';
            if (!\is_string($v)) {
                $hatalar[] = "{$alan}: metin olmalı";
                $v = '';
            }
            $d[$alan] = trim($v);
            if (mb_strlen($d[$alan]) > $azami) {
                $hatalar[] = "{$alan}: en çok {$azami} karakter";
            }
        }
        if ([] !== $hatalar) {
            return $hatalar;
        }
        $n = $this->db->executeStatement(
            'UPDATE uygulama_ayarlari SET unvan = ?, adres = ?, kayit_no = ?, updated_at = now() WHERE app = ?',
            [$d['unvan'], $d['adres'], $d['kayitNo'], $app],
        );

        return 1 === $n ? [] : ['önce Ayarlar sayfasında uygulamanın kimliğini kaydedin'];
    }

    /**
     * Panel için: her belge × dil — kaydedilmişse metni, değilse taslak.
     *
     * @return list<array{belge: string, dil: string, baslik: string, metin: string, kayitli: bool, guncellendi: string|null, eksikler: list<string>}>
     */
    public function belgeler(string $app): array
    {
        $kayitli = [];
        foreach ($this->db->fetchAllAssociative('SELECT belge, dil, metin, updated_at FROM yasal_metinler WHERE app = ?', [$app]) as $r) {
            $kayitli[(\is_string($r['belge']) ? $r['belge'] : '').'/'.(\is_string($r['dil']) ? $r['dil'] : '')] = $r;
        }
        $degerler = $this->degerler($app);
        $liste = [];
        foreach (Taslaklar::BELGELER as $belge) {
            foreach (Taslaklar::DILLER as $dil) {
                $r = $kayitli["{$belge}/{$dil}"] ?? null;
                $metin = \is_array($r) && \is_string($r['metin']) ? $r['metin'] : Taslaklar::metin($belge, $dil);
                $liste[] = [
                    'belge' => $belge, 'dil' => $dil, 'baslik' => Taslaklar::BASLIKLAR[$belge][$dil], 'metin' => $metin,
                    'kayitli' => null !== $r,
                    'guncellendi' => \is_array($r) && \is_string($r['updated_at']) ? (new \DateTimeImmutable($r['updated_at']))->format(\DATE_ATOM) : null,
                    // Metinde geçen ama değeri boş yer tutucular (sayfada "—").
                    'eksikler' => array_values(array_filter(self::yerTutucular($metin), static fn (string $y): bool => '' === ($degerler[$y] ?? ''))),
                ];
            }
        }

        return $liste;
    }

    /** @return list<string> hatalar; boşsa kaydedildi (yayında) */
    public function kaydet(string $app, string $belge, string $dil, mixed $veri): array
    {
        $metin = \is_array($veri) ? ($veri['metin'] ?? null) : null;
        if (!\is_array($veri) || [] !== array_diff(array_map(strval(...), array_keys($veri)), ['metin']) || !\is_string($metin)) {
            return ['gövde yalnız metin alanı olan bir nesne olmalı'];
        }
        $metin = trim(str_replace("\r\n", "\n", $metin));
        if ('' === $metin || mb_strlen($metin) > self::AZAMI) {
            return ['metin: 1–'.self::AZAMI.' karakter'];
        }
        if ([] !== $bilinmeyen = array_values(array_diff(self::yerTutucular($metin), self::YER_TUTUCULAR))) {
            return ['bilinmeyen yer tutucu: '.implode(', ', array_map(static fn (string $y): string => '{{'.$y.'}}', $bilinmeyen))];
        }
        $this->db->executeStatement(
            'INSERT INTO yasal_metinler (app, belge, dil, metin) VALUES (?, ?, ?, ?)
             ON CONFLICT (app, belge, dil) DO UPDATE SET metin = EXCLUDED.metin, updated_at = now()',
            [$app, $belge, $dil, $metin],
        );

        return [];
    }

    /**
     * Herkese açık sayfa; kaydedilmemişse null.
     *
     * @return array{baslik: string, bloklar: list<array{tur: 'baslik'|'paragraf', parcalar: list<array{kalin: bool, metin: string}>}|array{tur: 'liste', maddeler: list<list<array{kalin: bool, metin: string}>>}>, guncellendi: \DateTimeImmutable}|null
     */
    public function sayfa(string $app, string $belge, string $dil): ?array
    {
        $r = $this->db->fetchAssociative('SELECT metin, updated_at FROM yasal_metinler WHERE app = ? AND belge = ? AND dil = ?', [$app, $belge, $dil]);
        if (false === $r || !\is_string($r['metin']) || !\is_string($r['updated_at'])) {
            return null;
        }
        $degerler = $this->degerler($app);
        $dolu = preg_replace_callback('/\{\{(\w+)\}\}/', static fn (array $m): string => '' !== ($degerler[$m[1]] ?? '') ? $degerler[$m[1]] : '—', $r['metin']) ?? '';

        return ['baslik' => Taslaklar::BASLIKLAR[$belge][$dil], 'bloklar' => self::ayristir($dolu), 'guncellendi' => new \DateTimeImmutable($r['updated_at'])];
    }

    /**
     * Yayında (kaydedilmiş) belgeler, Taslaklar sırasıyla.
     *
     * @return list<string>
     */
    public function yayindakiler(string $app, string $dil): array
    {
        $kayitli = $this->db->fetchFirstColumn('SELECT belge FROM yasal_metinler WHERE app = ? AND dil = ?', [$app, $dil]);

        return array_values(array_filter(Taslaklar::BELGELER, static fn (string $b): bool => \in_array($b, $kayitli, true)));
    }

    /** @return array<string, string> */
    private function degerler(string $app): array
    {
        $a = $this->ayarlar->oku($app);

        return ['uygulama' => $a['ad'], 'eposta' => $a['destekEpostasi']] + $this->veriler($app);
    }

    /** @return list<string> */
    private static function yerTutucular(string $metin): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $metin, $m);

        return array_values(array_unique($m[1]));
    }

    /** @return list<array{tur: 'baslik'|'paragraf', parcalar: list<array{kalin: bool, metin: string}>}|array{tur: 'liste', maddeler: list<list<array{kalin: bool, metin: string}>>}> */
    private static function ayristir(string $metin): array
    {
        $bloklar = [];
        $paragraf = [];
        $maddeler = [];
        foreach (explode("\n", $metin) as $satir) {
            $satir = trim($satir);
            if ('' === $satir) {
                self::bitir($bloklar, $paragraf, $maddeler);
            } elseif (str_starts_with($satir, '## ')) {
                self::bitir($bloklar, $paragraf, $maddeler);
                $bloklar[] = ['tur' => 'baslik', 'parcalar' => self::parcalar(substr($satir, 3))];
            } elseif (str_starts_with($satir, '- ')) {
                // Madde paragrafı keser; paragraf satırı listeyi keser.
                $bos = [];
                self::bitir($bloklar, $paragraf, $bos);
                $maddeler[] = self::parcalar(substr($satir, 2));
            } else {
                $bos = [];
                self::bitir($bloklar, $bos, $maddeler);
                $paragraf[] = $satir;
            }
        }
        self::bitir($bloklar, $paragraf, $maddeler);

        return $bloklar;
    }

    /**
     * Biriken paragrafı ve listeyi bloğa çevirip boşaltır.
     *
     * @param list<array{tur: 'baslik'|'paragraf', parcalar: list<array{kalin: bool, metin: string}>}|array{tur: 'liste', maddeler: list<list<array{kalin: bool, metin: string}>>}> $bloklar
     * @param list<string>                                                                                                                                                          $paragraf
     * @param list<list<array{kalin: bool, metin: string}>>                                                                                                                         $maddeler
     */
    private static function bitir(array &$bloklar, array &$paragraf, array &$maddeler): void
    {
        if ([] !== $paragraf) {
            $bloklar[] = ['tur' => 'paragraf', 'parcalar' => self::parcalar(implode(' ', $paragraf))];
            $paragraf = [];
        }
        if ([] !== $maddeler) {
            $bloklar[] = ['tur' => 'liste', 'maddeler' => $maddeler];
            $maddeler = [];
        }
    }

    /**
     * "**kalın**" bölümleri; geri kalan düz metin (kaçışı şablon yapar).
     *
     * @return list<array{kalin: bool, metin: string}>
     */
    private static function parcalar(string $metin): array
    {
        $p = [];
        foreach (explode('**', $metin) as $i => $parca) {
            if ('' !== $parca) {
                $p[] = ['kalin' => 1 === $i % 2, 'metin' => $parca];
            }
        }

        return $p;
    }
}
