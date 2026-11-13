<?php

declare(strict_types=1);

namespace App\Atif;

use App\Uygulama\Sifreleyici;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A30: uygulamanın Meta bağlantısı panelden — dönüşüm (Pixel + CAPI
 * anahtarı, isteğe bağlı test olay kodu) ve harcama (reklam hesabı +
 * Insights anahtarı). Anahtarlar şifreli; panel yalnız son 4 haneyi görür.
 * Graph API sürümü hesap ayarı değil, dağıtımın (META_GRAPH_API_VERSION).
 */
final readonly class MetaBaglantisi
{
    public function __construct(
        private Connection $db,
        private Sifreleyici $sifreleyici,
        private HttpClientInterface $http,
        #[Autowire('%env(META_GRAPH_API_VERSION)%')]
        private string $surum,
    ) {
    }

    public function surumGecerli(): bool
    {
        return 1 === preg_match('/^v\d+\.\d+$/', $this->surum);
    }

    public function surum(): string
    {
        return $this->surum;
    }

    /**
     * Gönderim için: anahtarlar çözülmüş; eksik ya da çözülemeyen alan null.
     *
     * @return array{pixel: ?string, capi: ?string, testKodu: ?string, hesap: ?string, insights: ?string}
     */
    public function oku(string $app): array
    {
        $r = $this->satir($app);

        return [
            'pixel' => self::dizgeYaDaNull($r['pixel_id'] ?? null),
            'capi' => $this->coz($r['capi_anahtari'] ?? null),
            'testKodu' => self::dizgeYaDaNull($r['test_kodu'] ?? null),
            'hesap' => self::dizgeYaDaNull($r['reklam_hesabi'] ?? null),
            'insights' => $this->coz($r['insights_anahtari'] ?? null),
        ];
    }

    /** Sunucu dönüşümleri (CAPI) gidebilir mi. */
    public function donusumAcik(string $app): bool
    {
        $m = $this->oku($app);

        return null !== $m['pixel'] && null !== $m['capi'] && $this->surumGecerli();
    }

    /** @return list<array{app: string, hesap: string, insights: string}> harcaması çekilebilen hesaplar */
    public function harcamaHesaplari(): array
    {
        $hesaplar = [];
        foreach ($this->db->fetchFirstColumn('SELECT app FROM meta_baglantilari WHERE reklam_hesabi IS NOT NULL AND insights_anahtari IS NOT NULL ORDER BY app') as $app) {
            $m = \is_string($app) ? $this->oku($app) : ['hesap' => null, 'insights' => null];
            if (\is_string($app) && null !== $m['hesap'] && null !== $m['insights']) {
                $hesaplar[] = ['app' => $app, 'hesap' => $m['hesap'], 'insights' => $m['insights']];
            }
        }

        return $hesaplar;
    }

    /**
     * Panel için: anahtarların yalnız son 4 hanesi; okunamayan (şifreleme
     * anahtarı değişmiş) anahtar "okunamadi".
     *
     * @return array{pixelId: string, capiAnahtari: array{durum: 'yok'|'kayitli'|'okunamadi', son4: ?string}, testKodu: string,
     *               reklamHesabi: string, insightsAnahtari: array{durum: 'yok'|'kayitli'|'okunamadi', son4: ?string},
     *               sifrelemeHazir: bool, surum: ?string, guncellendi: ?string}
     */
    public function panel(string $app): array
    {
        $r = $this->satir($app);

        return [
            'pixelId' => self::dizgeYaDaNull($r['pixel_id'] ?? null) ?? '',
            'capiAnahtari' => $this->maske($r['capi_anahtari'] ?? null),
            'testKodu' => self::dizgeYaDaNull($r['test_kodu'] ?? null) ?? '',
            'reklamHesabi' => self::dizgeYaDaNull($r['reklam_hesabi'] ?? null) ?? '',
            'insightsAnahtari' => $this->maske($r['insights_anahtari'] ?? null),
            'sifrelemeHazir' => $this->sifreleyici->hazir(),
            'surum' => $this->surumGecerli() ? $this->surum : null,
            'guncellendi' => \is_string($r['updated_at'] ?? null) ? (new \DateTimeImmutable($r['updated_at']))->format(\DATE_ATOM) : null,
        ];
    }

    /**
     * Gövde: pixelId, testKodu, reklamHesabi (boş = kaldır); capiAnahtari ve
     * insightsAnahtari yoksa ya da null ise DEĞİŞMEZ, '' kaldırır, değer yenisini yazar.
     *
     * @return list<string> hatalar; boşsa kaydedildi
     */
    public function kaydet(string $app, mixed $veri): array
    {
        $alanlar = ['pixelId', 'capiAnahtari', 'testKodu', 'reklamHesabi', 'insightsAnahtari'];
        if (!\is_array($veri) || [] !== array_diff(array_map(strval(...), array_keys($veri)), $alanlar)) {
            return ['gövde yalnız '.implode(', ', $alanlar).' alanları olan bir nesne olmalı'];
        }
        $h = [];
        $metin = static function (string $ad, string $desen, string $aciklama) use ($veri, &$h): ?string {
            $d = $veri[$ad] ?? '';
            if (!\is_string($d)) {
                $h[] = "{$ad}: metin olmalı";

                return null;
            }
            $d = trim($d);
            if ('' !== $d && 1 !== preg_match($desen, $d)) {
                $h[] = "{$ad}: {$aciklama}";
            }

            return '' === $d ? null : $d;
        };
        $pixel = $metin('pixelId', '/^\d{5,20}$/', 'yalnız rakam (5–20), Events Manager › Veri kaynakları');
        $test = $metin('testKodu', '/^[A-Za-z0-9_]{1,40}$/', 'harf, rakam ya da _ (en çok 40), ör. TEST12345');
        $hesap = $metin('reklamHesabi', '/^act_\d{5,25}$/', 'act_ ile başlayan reklam hesabı kimliği, ör. act_1234567890');
        $anahtarlar = [];
        foreach (['capiAnahtari' => 'capi_anahtari', 'insightsAnahtari' => 'insights_anahtari'] as $ad => $sutun) {
            $d = $veri[$ad] ?? null;
            if (null === $d) {
                continue;
            }
            if (!\is_string($d)) {
                $h[] = "{$ad}: metin olmalı";
                continue;
            }
            $d = trim($d);
            if ('' === $d) {
                $anahtarlar[$sutun] = null;
            } elseif (1 !== preg_match('/^[A-Za-z0-9_\-|.]{20,1000}$/', $d)) {
                $h[] = "{$ad}: erişim anahtarı boşluksuz, 20–1000 karakter olmalı";
            } elseif (!$this->sifreleyici->hazir()) {
                $h[] = 'anahtar saklanamıyor: sunucuda şifreleme anahtarı (AYAR_SIFRELEME_ANAHTARI) yok';
            } else {
                $anahtarlar[$sutun] = $this->sifreleyici->sifrele($d);
            }
        }
        if ([] !== $h) {
            return $h;
        }
        $satir = ['pixel_id' => $pixel, 'test_kodu' => $test, 'reklam_hesabi' => $hesap] + $anahtarlar;
        $sutunlar = array_keys($satir);
        $this->db->executeStatement(
            'INSERT INTO meta_baglantilari (app, '.implode(', ', $sutunlar).') VALUES (:app, :'.implode(', :', $sutunlar).')
             ON CONFLICT (app) DO UPDATE SET '.implode(', ', array_map(static fn (string $s): string => "{$s} = EXCLUDED.{$s}", $sutunlar)).', updated_at = now()',
            ['app' => $app] + $satir,
        );

        return [];
    }

    /** Bağlantıyı tamamen kaldır; kayıt yoksa false. */
    public function sil(string $app): bool
    {
        return 1 === $this->db->executeStatement('DELETE FROM meta_baglantilari WHERE app = ?', [$app]);
    }

    /**
     * Kayıtlı değerlerle Meta'ya gerçek istek: Pixel'i CAPI anahtarıyla,
     * reklam hesabını Insights anahtarıyla okur. Mesaj anahtar içermez.
     *
     * @return array{donusum: array{durum: 'tamam'|'hata'|'ayarsiz', mesaj: string}, harcama: array{durum: 'tamam'|'hata'|'ayarsiz', mesaj: string}}
     */
    public function dene(string $app): array
    {
        $m = $this->oku($app);

        return [
            'donusum' => null === $m['pixel'] || null === $m['capi']
                ? ['durum' => 'ayarsiz', 'mesaj' => 'Pixel ID ve CAPI anahtarı girilmedi.']
                : $this->sor($m['pixel'], 'name', $m['capi'], static fn (array $g): string => 'Pixel: '.(\is_string($g['name'] ?? null) ? $g['name'] : $m['pixel'])),
            'harcama' => null === $m['hesap'] || null === $m['insights']
                ? ['durum' => 'ayarsiz', 'mesaj' => 'Reklam hesabı ve Insights anahtarı girilmedi.']
                : $this->sor($m['hesap'], 'name,currency', $m['insights'], static fn (array $g): string => 'Reklam hesabı: '.(\is_string($g['name'] ?? null) ? $g['name'] : $m['hesap'])
                    .(\is_string($g['currency'] ?? null) ? " ({$g['currency']})" : '')),
        ];
    }

    /**
     * @param \Closure(array<mixed>): string $tamam
     *
     * @return array{durum: 'tamam'|'hata', mesaj: string}
     */
    private function sor(string $nesne, string $alanlar, string $anahtar, \Closure $tamam): array
    {
        if (!$this->surumGecerli()) {
            return ['durum' => 'hata', 'mesaj' => 'Sunucuda Graph API sürümü (META_GRAPH_API_VERSION) yok.'];
        }
        try {
            $yanit = $this->http->request('GET', 'https://graph.facebook.com/'.$this->surum.'/'.rawurlencode($nesne), [
                'query' => ['fields' => $alanlar, 'access_token' => $anahtar],
                'timeout' => 10,
            ]);
            $govde = $yanit->toArray(false);
            $kod = $yanit->getStatusCode();
        } catch (ExceptionInterface) {
            // İstemci hatası adresi (ve sorgudaki anahtarı) içerebilir.
            return ['durum' => 'hata', 'mesaj' => "Meta'ya bağlanılamadı."];
        }
        if ($kod >= 200 && $kod < 300 && !isset($govde['error'])) {
            return ['durum' => 'tamam', 'mesaj' => $tamam($govde)];
        }
        $hata = \is_array($govde['error'] ?? null) ? $govde['error'] : [];
        $mesaj = \is_string($hata['message'] ?? null) ? $hata['message'] : "HTTP {$kod}";

        return ['durum' => 'hata', 'mesaj' => 'Meta reddetti: '.mb_substr(str_replace($anahtar, '…', $mesaj), 0, 300)];
    }

    /** @return array<string, mixed> */
    private function satir(string $app): array
    {
        $r = $this->db->fetchAssociative('SELECT * FROM meta_baglantilari WHERE app = ?', [$app]);

        return false === $r ? [] : $r;
    }

    private function coz(mixed $kapali): ?string
    {
        return \is_string($kapali) ? $this->sifreleyici->coz($kapali) : null;
    }

    /** @return array{durum: 'yok'|'kayitli'|'okunamadi', son4: ?string} */
    private function maske(mixed $kapali): array
    {
        if (!\is_string($kapali)) {
            return ['durum' => 'yok', 'son4' => null];
        }
        $acik = $this->sifreleyici->coz($kapali);

        return null === $acik ? ['durum' => 'okunamadi', 'son4' => null] : ['durum' => 'kayitli', 'son4' => mb_substr($acik, -4)];
    }

    private static function dizgeYaDaNull(mixed $d): ?string
    {
        return \is_string($d) && '' !== $d ? $d : null;
    }
}
