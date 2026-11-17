<?php

declare(strict_types=1);

namespace App\Ai;

use Doctrine\DBAL\Connection;

/**
 * Uygulama başına ürün bağlamı (A4): yönetici panelde yazar, AI asistanı
 * sistem isteminde okur. Bağlamsız sonuçlar belirgin şekilde kötü
 * (FunnelFox belgesi; ai-asistan-arastirmasi.md). Ziyaretçi verisi değil —
 * yalnız ürünü anlatan, yöneticinin yazdığı metin.
 */
final readonly class UrunBaglami
{
    /** alan => en çok karakter (göçteki CHECK ile aynı). */
    public const ALANLAR = ['urun' => 1000, 'kategori' => 100, 'kitle' => 500, 'ton' => 200, 'yasaklar' => 1000];

    /** Sistem isteminde alanın adı. */
    private const ETIKET = [
        'urun' => 'Ürün ne yapıyor',
        'kategori' => 'Kategori',
        'kitle' => 'Hedef kitle',
        'ton' => 'Ton',
        'yasaklar' => 'Asla yazma / kaçın',
    ];

    public function __construct(
        private Connection $db,
    ) {
    }

    /** @return array{urun: string, kategori: string, kitle: string, ton: string, yasaklar: string} */
    public function oku(string $app): array
    {
        $s = $this->db->fetchAssociative('SELECT urun, kategori, kitle, ton, yasaklar FROM uygulama_baglamlari WHERE app = :app', ['app' => $app]);
        $metin = static fn (string $a): string => \is_array($s) && \is_string($s[$a] ?? null) ? $s[$a] : '';

        return ['urun' => $metin('urun'), 'kategori' => $metin('kategori'), 'kitle' => $metin('kitle'), 'ton' => $metin('ton'), 'yasaklar' => $metin('yasaklar')];
    }

    /** @return list<string> hatalar; boşsa kaydedildi */
    public function kaydet(string $app, mixed $veri): array
    {
        if (!\is_array($veri)) {
            return ['gövde bir nesne olmalı'];
        }
        $hatalar = [];
        if ([] !== $fazla = array_diff(array_map(strval(...), array_keys($veri)), array_keys(self::ALANLAR))) {
            $hatalar[] = 'bilinmeyen alan: '.implode(', ', $fazla);
        }
        $deger = [];
        foreach (self::ALANLAR as $alan => $azami) {
            $v = $veri[$alan] ?? '';
            if (!\is_string($v)) {
                $hatalar[] = "{$alan}: metin olmalı";
                continue;
            }
            $v = trim($v);
            if (mb_strlen($v) > $azami) {
                $hatalar[] = "{$alan}: en çok {$azami} karakter";
            }
            $deger[$alan] = $v;
        }
        if ([] !== $hatalar) {
            return $hatalar;
        }
        $this->db->executeStatement(
            'INSERT INTO uygulama_baglamlari (app, urun, kategori, kitle, ton, yasaklar)
             VALUES (:app, :urun, :kategori, :kitle, :ton, :yasaklar)
             ON CONFLICT (app) DO UPDATE SET urun = EXCLUDED.urun, kategori = EXCLUDED.kategori, kitle = EXCLUDED.kitle,
                 ton = EXCLUDED.ton, yasaklar = EXCLUDED.yasaklar, updated_at = now()',
            ['app' => $app] + $deger,
        );

        return [];
    }

    /** Sistem istemine giren metin; hiçbir alan dolu değilse null (blok gönderilmez). */
    public function istemMetni(string $app): ?string
    {
        $satirlar = [];
        foreach ($this->oku($app) as $alan => $deger) {
            if ('' !== $deger) {
                $satirlar[] = self::ETIKET[$alan].': '.$deger;
            }
        }

        return [] === $satirlar ? null : "Ürün bağlamı (yöneticinin yazdığı; metinleri ve görünümü (tema) buna göre seç):\n".implode("\n", $satirlar);
    }
}
