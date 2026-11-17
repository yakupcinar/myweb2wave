<?php

declare(strict_types=1);

namespace App\Medya;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * V3b: stok fotoğraf — yalnız Pexels (lisans ve API kuralları ölçüldü,
 * `v3b-stok-fotograf.md`): ticari kullanım ve değiştirme serbest, atıf
 * zorunlu değil; tanınabilir kişi kötü gösterilmez, ürünü onaylıyormuş gibi
 * kullanılmaz. Unsplash elendi (API görselin kendi adresinden sunulmasını
 * şart koşuyor). İçe alınan görsel yüklenen gibi yeniden kodlanır
 * (Medyalar); kaynak, fotoğrafçı ve lisans görselle saklanır. Görsel
 * bilgisi istemciden değil Pexels'ten okunur; yalnız Pexels'in görsel
 * sunucusundan indirilir.
 */
final readonly class StokGorseller
{
    public const LISANS = 'Pexels Lisansı';
    public const LISANS_ADRESI = 'https://www.pexels.com/license/';
    private const API = 'https://api.pexels.com/v1/';
    private const GORSEL_SUNUCUSU = 'https://images.pexels.com/';

    public function __construct(
        private HttpClientInterface $http,
        private Medyalar $medyalar,
        private Connection $db,
        private LoggerInterface $logger,
        #[Autowire('%env(default::PEXELS_API_KEY)%')]
        private ?string $anahtar,
    ) {
    }

    public function acik(): bool
    {
        return null !== $this->anahtar && '' !== $this->anahtar;
    }

    /**
     * @return array{sonuclar: list<array{kimlik: int, kucuk: string, genislik: int, yukseklik: int, yazar: string, sayfa: string, aciklama: string}>}|list<string>
     */
    public function ara(mixed $sorgu, mixed $yon): array
    {
        if (!$this->acik()) {
            return ['stok fotoğraf kapalı (PEXELS_API_KEY yok)'];
        }
        if (!\is_string($sorgu) || '' === trim($sorgu) || mb_strlen($sorgu) > 100) {
            return ['arama 1–100 karakter olmalı'];
        }
        if (!\in_array($yon, [null, 'portrait', 'landscape', 'square'], true)) {
            return ['yon portrait, landscape ya da square olmalı'];
        }
        $veri = $this->istek('search', ['query' => trim($sorgu), 'per_page' => 15, 'locale' => 'tr-TR'] + (null === $yon ? [] : ['orientation' => $yon]));
        if (null === $veri || !\is_array($veri['photos'] ?? null)) {
            return ['Pexels şu an yanıt vermiyor'];
        }
        $sonuclar = [];
        foreach ($veri['photos'] as $f) {
            $foto = \is_array($f) ? self::foto($f) : null;
            if (null !== $foto) {
                $sonuclar[] = ['kimlik' => $foto['kimlik'], 'kucuk' => $foto['kucuk'], 'genislik' => $foto['genislik'], 'yukseklik' => $foto['yukseklik'], 'yazar' => $foto['yazar'], 'sayfa' => $foto['sayfa'], 'aciklama' => $foto['aciklama']];
            }
        }

        return ['sonuclar' => $sonuclar];
    }

    /** @return array{id: string}|list<string> */
    public function al(string $app, mixed $kimlik): array
    {
        if (!$this->acik()) {
            return ['stok fotoğraf kapalı (PEXELS_API_KEY yok)'];
        }
        if (!\is_int($kimlik) || $kimlik < 1) {
            return ['Pexels görsel kimliği pozitif tam sayı olmalı'];
        }
        $veri = $this->istek("photos/{$kimlik}", []);
        $foto = null === $veri ? null : self::foto($veri);
        if (null === $foto) {
            return ['Pexels görseli bulunamadı ya da bilgisi eksik'];
        }
        $gecici = tempnam(sys_get_temp_dir(), 'stok');
        try {
            file_put_contents($gecici, $this->http->request('GET', $foto['buyuk'], ['timeout' => 20, 'max_redirects' => 0])->getContent());
        } catch (\Throwable $e) {
            @unlink($gecici);
            $this->logger->warning('Pexels görseli indirilemedi', ['kimlik' => $kimlik, 'exception' => $e]);

            return ['Pexels görseli indirilemedi'];
        }
        $sonuc = $this->medyalar->yukle($app, $gecici, "pexels-{$kimlik}.jpg");
        @unlink($gecici);
        if (!isset($sonuc['id'])) {
            return $sonuc;
        }
        $this->db->executeStatement(
            'UPDATE medya SET kaynak = :kaynak, kaynak_adresi = :sayfa, yazar = :yazar, yazar_adresi = :yazarAdresi, lisans = :lisans, lisans_adresi = :lisansAdresi WHERE app = :app AND id = :id',
            ['kaynak' => 'pexels', 'sayfa' => $foto['sayfa'], 'yazar' => $foto['yazar'], 'yazarAdresi' => $foto['yazarAdresi'], 'lisans' => self::LISANS, 'lisansAdresi' => self::LISANS_ADRESI, 'app' => $app, 'id' => $sonuc['id']],
        );

        return $sonuc;
    }

    /**
     * Pexels yanıtından kullanılan alanlar; eksik ya da beklenmeyen adresli
     * kayıt atlanır (indirme yalnız Pexels'in görsel sunucusundan).
     *
     * @param array<mixed> $f
     *
     * @return array{kimlik: int, kucuk: string, buyuk: string, genislik: int, yukseklik: int, yazar: string, yazarAdresi: string, sayfa: string, aciklama: string}|null
     */
    private static function foto(array $f): ?array
    {
        $src = \is_array($f['src'] ?? null) ? $f['src'] : [];
        $alanlar = [$f['id'] ?? null, $src['medium'] ?? null, $src['large2x'] ?? null, $f['width'] ?? null, $f['height'] ?? null, $f['photographer'] ?? null, $f['photographer_url'] ?? null, $f['url'] ?? null];
        [$kimlik, $kucuk, $buyuk, $g, $y, $yazar, $yazarAdresi, $sayfa] = $alanlar;
        if (!\is_int($kimlik) || !\is_string($kucuk) || !\is_string($buyuk) || !\is_int($g) || !\is_int($y)
            || !\is_string($yazar) || !\is_string($yazarAdresi) || !\is_string($sayfa)
            || !str_starts_with($kucuk, self::GORSEL_SUNUCUSU) || !str_starts_with($buyuk, self::GORSEL_SUNUCUSU)
            || !str_starts_with($sayfa, 'https://www.pexels.com/')) {
            return null;
        }
        $aciklama = $f['alt'] ?? '';

        return ['kimlik' => $kimlik, 'kucuk' => $kucuk, 'buyuk' => $buyuk, 'genislik' => $g, 'yukseklik' => $y, 'yazar' => $yazar, 'yazarAdresi' => $yazarAdresi, 'sayfa' => $sayfa, 'aciklama' => \is_string($aciklama) ? $aciklama : ''];
    }

    /**
     * @param array<string, int|string> $sorgu
     *
     * @return array<mixed>|null
     */
    private function istek(string $yol, array $sorgu): ?array
    {
        try {
            return $this->http->request('GET', self::API.$yol, ['query' => $sorgu, 'headers' => ['Authorization' => (string) $this->anahtar], 'timeout' => 8])->toArray();
        } catch (\Throwable $e) {
            $this->logger->warning('Pexels isteği başarısız', ['yol' => $yol, 'exception' => $e]);

            return null;
        }
    }
}
