<?php

declare(strict_types=1);

namespace App\Medya;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Huni görselleri (E2.3). Yüklenen dosyaya güvenilmez: türü içerikten
 * okunur (uzantı/MIME başlığı değil), yalnız PNG/JPEG/WebP; boyut ve piksel
 * sınırı ÇÖZMEDEN önce (sıkıştırma bombası); sonra GD ile yeniden çizilip
 * WebP'ye kodlanır — EXIF/konum verisi, gömülü betik, poliglot dosya
 * yeniden kodlamada kalmaz. SVG yok (içinde betik taşıyabilir).
 */
final readonly class Medyalar
{
    public const AZAMI_BAYT = 5 * 1024 * 1024;
    /**
     * Çözmeden önce: kenar ve toplam piksel. GD piksel başına ~4 bayt; PHP
     * memory_limit 128M → 16 Mpx (~64 MB) güvenli. 40 Mpx ~160 MB isterdi,
     * çekirdek bellekten düşerdi (hesaplandı ve testte 4000×4000 ölçüldü).
     */
    public const AZAMI_KENAR = 8000;
    public const AZAMI_PIKSEL = 16_000_000;
    /** Kaydedilen görselin en uzun kenarı (huni dar bir sütun). */
    public const HEDEF_KENAR = 1600;
    public const WEBP_KALITE = 82;
    public const KIMLIK = '/^[0-9a-f]{64}$/';

    public function __construct(
        private Connection $db,
        #[Autowire('%env(resolve:MEDYA_DIZINI)%')]
        private string $dizin,
    ) {
    }

    /** @return array{id: string}|list<string> kimlik ya da hatalar */
    public function yukle(string $app, string $yol, string $ozgunAd): array
    {
        $bayt = @filesize($yol);
        if (false === $bayt || 0 === $bayt) {
            return ['dosya boş ya da okunamadı'];
        }
        if ($bayt > self::AZAMI_BAYT) {
            return ['dosya en çok 5 MB olmalı'];
        }
        $bilgi = @getimagesize($yol);
        if (false === $bilgi || !\in_array($bilgi[2], [\IMAGETYPE_PNG, \IMAGETYPE_JPEG, \IMAGETYPE_WEBP], true)) {
            return ['yalnız PNG, JPEG ya da WebP görsel (SVG ve GIF kabul edilmez)'];
        }
        [$g, $y] = [$bilgi[0], $bilgi[1]];
        if ($g < 1 || $y < 1 || $g > self::AZAMI_KENAR || $y > self::AZAMI_KENAR || $g * $y > self::AZAMI_PIKSEL) {
            return ['görsel en çok '.self::AZAMI_KENAR.' piksel kenarlı ve 16 megapiksel olmalı'];
        }
        $icerik = file_get_contents($yol);
        $kaynak = false === $icerik ? false : @imagecreatefromstring($icerik);
        if (false === $kaynak) {
            return ['görsel çözülemedi (bozuk dosya)'];
        }

        $oran = min(1.0, self::HEDEF_KENAR / max($g, $y));
        $yg = max(1, (int) round($g * $oran));
        $yy = max(1, (int) round($y * $oran));
        $hedef = imagecreatetruecolor($yg, $yy);
        imagealphablending($hedef, false);
        imagesavealpha($hedef, true);
        imagecopyresampled($hedef, $kaynak, 0, 0, 0, 0, $yg, $yy, $g, $y);

        ob_start();
        imagewebp($hedef, null, self::WEBP_KALITE);
        $webp = (string) ob_get_clean();
        $id = hash('sha256', $webp);

        if (!is_dir($this->dizin) && !@mkdir($this->dizin, 0o755, true) && !is_dir($this->dizin)) {
            return ['görsel dizini oluşturulamadı'];
        }
        $hedefYol = $this->yol($id);
        if (!is_file($hedefYol)) {
            // Önce geçici ada yaz, sonra taşı: yarım dosya hiç sunulmaz.
            $gecici = $hedefYol.'.'.bin2hex(random_bytes(4)).'.yarim';
            if (false === file_put_contents($gecici, $webp) || !rename($gecici, $hedefYol)) {
                @unlink($gecici);

                return ['görsel kaydedilemedi'];
            }
        }
        $this->db->executeStatement(
            'INSERT INTO medya (app, id, genislik, yukseklik, bayt, ozgun_ad) VALUES (:app, :id, :g, :y, :b, :ad)
             ON CONFLICT (app, id) DO NOTHING',
            ['app' => $app, 'id' => $id, 'g' => $yg, 'y' => $yy, 'b' => \strlen($webp), 'ad' => mb_substr($ozgunAd, 0, 200)],
        );

        return ['id' => $id];
    }

    /** @return list<array{id: string, genislik: int, yukseklik: int, ozgun_ad: string}> en yeni önce */
    public function listele(string $app): array
    {
        return array_map(static fn (array $s): array => [
            'id' => \is_string($s['id']) ? $s['id'] : '',
            'genislik' => is_numeric($s['genislik']) ? (int) $s['genislik'] : 0,
            'yukseklik' => is_numeric($s['yukseklik']) ? (int) $s['yukseklik'] : 0,
            'ozgun_ad' => \is_string($s['ozgun_ad']) ? $s['ozgun_ad'] : '',
        ], $this->db->fetchAllAssociative(
            'SELECT id, genislik, yukseklik, ozgun_ad FROM medya WHERE app = :app ORDER BY created_at DESC, id',
            ['app' => $app],
        ));
    }

    /**
     * Bu uygulamaya yüklenmemiş kimlikler.
     *
     * @param list<string> $kimlikler
     *
     * @return list<string>
     */
    public function eksikler(string $app, array $kimlikler): array
    {
        if ([] === $kimlikler) {
            return [];
        }
        $var = $this->db->fetchFirstColumn(
            'SELECT id FROM medya WHERE app = ? AND id IN (?)',
            [$app, array_values(array_unique($kimlikler))],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        return array_values(array_diff($kimlikler, array_filter($var, \is_string(...))));
    }

    /** Sunulacak dosya; kimlik biçimi denetlenir (yol dışına çıkılamaz). */
    public function dosya(string $id): ?string
    {
        if (1 !== preg_match(self::KIMLIK, $id)) {
            return null;
        }
        $yol = $this->yol($id);

        return is_file($yol) ? $yol : null;
    }

    private function yol(string $id): string
    {
        return rtrim($this->dizin, '/').'/'.$id.'.webp';
    }
}
