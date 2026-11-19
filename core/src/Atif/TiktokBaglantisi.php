<?php

declare(strict_types=1);

namespace App\Atif;

use App\Uygulama\Sifreleyici;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A31: uygulamanın TikTok bağlantısı panelden — A30'un (MetaBaglantisi)
 * karşılığı. Dönüşüm (Pixel kodu + Events API anahtarı, isteğe bağlı test
 * olay kodu) ve harcama (reklamveren kimliği + Marketing API anahtarı).
 * Anahtarlar şifreli; panel yalnız son 4 haneyi görür.
 */
final readonly class TiktokBaglantisi
{
    public const API = 'https://business-api.tiktok.com/open_api/v1.3';

    public function __construct(
        private Connection $db,
        private Sifreleyici $sifreleyici,
        private HttpClientInterface $http,
    ) {
    }

    /**
     * Gönderim için: anahtarlar çözülmüş; eksik ya da çözülemeyen alan null.
     *
     * @return array{pixel: ?string, events: ?string, testKodu: ?string, reklamveren: ?string, rapor: ?string}
     */
    public function oku(string $app): array
    {
        $r = $this->satir($app);

        return [
            'pixel' => self::dizgeYaDaNull($r['pixel_kodu'] ?? null),
            'events' => $this->coz($r['events_anahtari'] ?? null),
            'testKodu' => self::dizgeYaDaNull($r['test_kodu'] ?? null),
            'reklamveren' => self::dizgeYaDaNull($r['reklamveren'] ?? null),
            'rapor' => $this->coz($r['rapor_anahtari'] ?? null),
        ];
    }

    /** Sunucu dönüşümleri (Events API) gidebilir mi. */
    public function donusumAcik(string $app): bool
    {
        $t = $this->oku($app);

        return null !== $t['pixel'] && null !== $t['events'];
    }

    /** @return list<array{app: string, reklamveren: string, rapor: string}> harcaması çekilebilen hesaplar */
    public function harcamaHesaplari(): array
    {
        $hesaplar = [];
        foreach ($this->db->fetchFirstColumn('SELECT app FROM tiktok_baglantilari WHERE reklamveren IS NOT NULL AND rapor_anahtari IS NOT NULL ORDER BY app') as $app) {
            $t = \is_string($app) ? $this->oku($app) : ['reklamveren' => null, 'rapor' => null];
            if (\is_string($app) && null !== $t['reklamveren'] && null !== $t['rapor']) {
                $hesaplar[] = ['app' => $app, 'reklamveren' => $t['reklamveren'], 'rapor' => $t['rapor']];
            }
        }

        return $hesaplar;
    }

    /**
     * Panel için: anahtarların yalnız son 4 hanesi; okunamayan (şifreleme
     * anahtarı değişmiş) anahtar "okunamadi".
     *
     * @return array{pixelKodu: string, eventsAnahtari: array{durum: 'yok'|'kayitli'|'okunamadi', son4: ?string}, testKodu: string,
     *               reklamveren: string, raporAnahtari: array{durum: 'yok'|'kayitli'|'okunamadi', son4: ?string},
     *               sifrelemeHazir: bool, guncellendi: ?string}
     */
    public function panel(string $app): array
    {
        $r = $this->satir($app);

        return [
            'pixelKodu' => self::dizgeYaDaNull($r['pixel_kodu'] ?? null) ?? '',
            'eventsAnahtari' => $this->maske($r['events_anahtari'] ?? null),
            'testKodu' => self::dizgeYaDaNull($r['test_kodu'] ?? null) ?? '',
            'reklamveren' => self::dizgeYaDaNull($r['reklamveren'] ?? null) ?? '',
            'raporAnahtari' => $this->maske($r['rapor_anahtari'] ?? null),
            'sifrelemeHazir' => $this->sifreleyici->hazir(),
            'guncellendi' => \is_string($r['updated_at'] ?? null) ? (new \DateTimeImmutable($r['updated_at']))->format(\DATE_ATOM) : null,
        ];
    }

    /**
     * Gövde: pixelKodu, testKodu, reklamveren (boş = kaldır); eventsAnahtari ve
     * raporAnahtari yoksa ya da null ise DEĞİŞMEZ, '' kaldırır, değer yenisini yazar.
     *
     * @return list<string> hatalar; boşsa kaydedildi
     */
    public function kaydet(string $app, mixed $veri): array
    {
        $alanlar = ['pixelKodu', 'eventsAnahtari', 'testKodu', 'reklamveren', 'raporAnahtari'];
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
        $pixel = $metin('pixelKodu', '/^[A-Z0-9]{10,40}$/', 'büyük harf ve rakam (10–40), Events Manager › Web olayları › Pixel kodu');
        $test = $metin('testKodu', '/^[A-Za-z0-9_]{1,40}$/', 'harf, rakam ya da _ (en çok 40), ör. TEST12345');
        $reklamveren = $metin('reklamveren', '/^\d{5,25}$/', 'yalnız rakam, Ads Manager › hesap seçicideki reklamveren kimliği');
        $anahtarlar = [];
        foreach (['eventsAnahtari' => 'events_anahtari', 'raporAnahtari' => 'rapor_anahtari'] as $ad => $sutun) {
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
        $satir = ['pixel_kodu' => $pixel, 'test_kodu' => $test, 'reklamveren' => $reklamveren] + $anahtarlar;
        $sutunlar = array_keys($satir);
        $this->db->executeStatement(
            'INSERT INTO tiktok_baglantilari (app, '.implode(', ', $sutunlar).') VALUES (:app, :'.implode(', :', $sutunlar).')
             ON CONFLICT (app) DO UPDATE SET '.implode(', ', array_map(static fn (string $s): string => "{$s} = EXCLUDED.{$s}", $sutunlar)).', updated_at = now()',
            ['app' => $app] + $satir,
        );

        return [];
    }

    /** Bağlantıyı tamamen kaldır; kayıt yoksa false. */
    public function sil(string $app): bool
    {
        return 1 === $this->db->executeStatement('DELETE FROM tiktok_baglantilari WHERE app = ?', [$app]);
    }

    /**
     * Kayıtlı değerlerle TikTok'a gerçek istek. Events API anahtarını yan
     * etkisiz okuyan uç yok: dönüşüm yalnız test olay koduyla bir deneme
     * olayı gönderir (TikTok › Test events'te görünür, canlı veriye karışmaz).
     * Harcama reklamveren bilgisini Marketing API anahtarıyla okur. Mesaj
     * anahtar içermez.
     *
     * @return array{donusum: array{durum: 'tamam'|'hata'|'ayarsiz'|'kayitli', mesaj: string}, harcama: array{durum: 'tamam'|'hata'|'ayarsiz', mesaj: string}}
     */
    public function dene(string $app): array
    {
        $t = $this->oku($app);
        if (null === $t['pixel'] || null === $t['events']) {
            $donusum = ['durum' => 'ayarsiz', 'mesaj' => 'Pixel kodu ve Events API anahtarı girilmedi.'];
        } elseif (null === $t['testKodu']) {
            $donusum = ['durum' => 'kayitli', 'mesaj' => 'Canlı olaylar gönderiliyor. Bağlantıyı denemek için test olay kodu girin (Events Manager › Test events); deneme olayı yalnız orada görünür.'];
        } else {
            $donusum = $this->sor('POST', '/event/track/', $t['events'], ['json' => [
                'event_source' => 'web',
                'event_source_id' => $t['pixel'],
                'test_event_code' => $t['testKodu'],
                'data' => [['event' => 'ViewContent', 'event_time' => time(), 'event_id' => 'dene-'.bin2hex(random_bytes(8))]],
            ]], static fn (array $v): string => 'Deneme olayı gönderildi — TikTok › Test events\'te görünmeli.');
        }

        return [
            'donusum' => $donusum,
            'harcama' => null === $t['reklamveren'] || null === $t['rapor']
                ? ['durum' => 'ayarsiz', 'mesaj' => 'Reklamveren kimliği ve Marketing API anahtarı girilmedi.']
                : $this->sor('GET', '/advertiser/info/', $t['rapor'], ['query' => [
                    'advertiser_ids' => json_encode([$t['reklamveren']], \JSON_THROW_ON_ERROR),
                    'fields' => json_encode(['name', 'currency'], \JSON_THROW_ON_ERROR),
                ]], static function (array $v) use ($t): string {
                    $liste = \is_array($v['list'] ?? null) ? $v['list'] : [];
                    $h = \is_array($liste[0] ?? null) ? $liste[0] : [];

                    return 'Reklamveren: '.(\is_string($h['name'] ?? null) ? $h['name'] : $t['reklamveren'])
                        .(\is_string($h['currency'] ?? null) ? " ({$h['currency']})" : '');
                }),
        ];
    }

    /**
     * TikTok yanıtı her zaman {code, message, data}; başarı code 0.
     *
     * @param array<string, mixed>           $secenekler
     * @param \Closure(array<mixed>): string $tamam      data alanını alır
     *
     * @return array{durum: 'tamam'|'hata', mesaj: string}
     */
    private function sor(string $yontem, string $yol, string $anahtar, array $secenekler, \Closure $tamam): array
    {
        try {
            $yanit = $this->http->request($yontem, self::API.$yol, $secenekler + [
                'headers' => ['Access-Token' => $anahtar],
                'timeout' => 10,
            ]);
            $govde = $yanit->toArray(false);
            $kod = $yanit->getStatusCode();
        } catch (ExceptionInterface) {
            return ['durum' => 'hata', 'mesaj' => "TikTok'a bağlanılamadı."];
        }
        if ($kod >= 200 && $kod < 300 && 0 === ($govde['code'] ?? null)) {
            return ['durum' => 'tamam', 'mesaj' => $tamam(\is_array($govde['data'] ?? null) ? $govde['data'] : [])];
        }
        $mesaj = \is_string($govde['message'] ?? null) ? $govde['message'] : "HTTP {$kod}";
        $hataKodu = \is_int($govde['code'] ?? null) ? " (kod {$govde['code']})" : '';

        return ['durum' => 'hata', 'mesaj' => 'TikTok reddetti: '.mb_substr(str_replace($anahtar, '…', $mesaj), 0, 300).$hataKodu];
    }

    /** @return array<string, mixed> */
    private function satir(string $app): array
    {
        $r = $this->db->fetchAssociative('SELECT * FROM tiktok_baglantilari WHERE app = ?', [$app]);

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
