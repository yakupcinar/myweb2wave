<?php

declare(strict_types=1);

namespace App\Atif;

use App\Billing\ParaBirimi;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A31 (TB3): TikTok kampanya harcamasını gün başına çeker — MetaHarcamalari'nın
 * karşılığı. Marketing API eş zamanlı rapor (report/integrated/get, BASIC,
 * AUCTION_CAMPAIGN, campaign_id + stat_time_day). Gün boyutuyla tek istekte en
 * çok 30 gün → aralık 30 günlük pencerelere bölünür. Satırda para birimi yok:
 * reklamverenin para birimi advertiser/info'dan bir kez okunur. Son N gün her
 * çekişte yeniden yazılır; biri hata verirse hiçbiri yazılmaz.
 */
final readonly class TiktokHarcamalari
{
    /** Bir pencerede en çok bu kadar sayfa (1000 satır / sayfa); üstü hata sayılır. */
    public const AZAMI_SAYFA = 50;
    /** TikTok: stat_time_day boyutuyla bir istekte en çok 30 gün. */
    public const PENCERE = 30;

    public function __construct(
        private Connection $db,
        private HttpClientInterface $http,
        private TiktokBaglantisi $baglanti,
    ) {
    }

    public function acik(): bool
    {
        return [] !== $this->baglanti->harcamaHesaplari();
    }

    /**
     * Bugün dahil son $gun günü çeker ve yazar.
     *
     * @return array{satir: int, atlanan: int}|string satır sayısı ya da hata metni (anahtar içermez)
     */
    public function cek(int $gun, ?\DateTimeImmutable $bugun = null): array|string
    {
        if (!$this->acik()) {
            return 'TikTok harcaması kapalı: panelde Entegrasyonlar › TikTok (reklamveren + Marketing API anahtarı) yok';
        }
        $bugun ??= new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $satirlar = [];
        $atlanan = 0;
        foreach ($this->baglanti->harcamaHesaplari() as $h) {
            $sonuc = $this->hesaptan($h['reklamveren'], $h['rapor'], $gun, $bugun);
            if (\is_string($sonuc)) {
                return "{$h['app']}: {$sonuc}";
            }
            [$hesapSatirlari, $hesapAtlanan] = $sonuc;
            array_push($satirlar, ...$hesapSatirlari);
            $atlanan += $hesapAtlanan;
        }
        $this->db->transactional(function (Connection $db) use ($satirlar): void {
            foreach ($satirlar as $s) {
                $db->executeStatement(
                    "INSERT INTO reklam_harcamalari (saglayici, kampanya_id, kampanya_adi, gun, harcama_kucuk, para_birimi)
                     VALUES ('tiktok', :id, :ad, :gun, :harcama, :para)
                     ON CONFLICT (saglayici, kampanya_id, gun) DO UPDATE SET kampanya_adi = EXCLUDED.kampanya_adi,
                         harcama_kucuk = EXCLUDED.harcama_kucuk, para_birimi = EXCLUDED.para_birimi, guncellendi_at = now()",
                    $s,
                );
            }
        });

        return ['satir' => \count($satirlar), 'atlanan' => $atlanan];
    }

    /**
     * Tek reklamverenin pencereleri ve sayfaları.
     *
     * @return array{list<array{id: string, ad: string, gun: string, harcama: int, para: string}>, int}|string satırlar + atlanan ya da hata
     */
    private function hesaptan(string $reklamveren, string $anahtar, int $gun, \DateTimeImmutable $bugun): array|string
    {
        $bilgi = $this->sor('/advertiser/info/', $anahtar, [
            'advertiser_ids' => json_encode([$reklamveren], \JSON_THROW_ON_ERROR),
            'fields' => json_encode(['currency'], \JSON_THROW_ON_ERROR),
        ]);
        if (\is_string($bilgi)) {
            return $bilgi;
        }
        $liste = \is_array($bilgi['list'] ?? null) ? $bilgi['list'] : [];
        $ilk = \is_array($liste[0] ?? null) ? $liste[0] : [];
        $para = $ilk['currency'] ?? null;
        if (!\is_string($para) || 1 !== preg_match('/^[A-Za-z]{3}$/', $para)) {
            return 'TikTok reklamverenin para birimi okunamadı';
        }

        $satirlar = [];
        $atlanan = 0;
        $bas = $bugun->modify('-'.($gun - 1).' days');
        while ($bas <= $bugun) {
            $son = min($bugun, $bas->modify('+'.(self::PENCERE - 1).' days'));
            for ($sayfa = 1;; ++$sayfa) {
                if ($sayfa > self::AZAMI_SAYFA) {
                    return 'TikTok raporu çok sayfalı (> '.self::AZAMI_SAYFA.')';
                }
                $veri = $this->sor('/report/integrated/get/', $anahtar, [
                    'advertiser_id' => $reklamveren,
                    'report_type' => 'BASIC',
                    'data_level' => 'AUCTION_CAMPAIGN',
                    'dimensions' => json_encode(['campaign_id', 'stat_time_day'], \JSON_THROW_ON_ERROR),
                    'metrics' => json_encode(['spend', 'campaign_name'], \JSON_THROW_ON_ERROR),
                    'start_date' => $bas->format('Y-m-d'),
                    'end_date' => $son->format('Y-m-d'),
                    'page' => $sayfa,
                    'page_size' => 1000,
                ]);
                if (\is_string($veri)) {
                    return $veri;
                }
                foreach (\is_array($veri['list'] ?? null) ? $veri['list'] : [] as $s) {
                    $satir = \is_array($s) ? self::satir($s, $para) : null;
                    if (null === $satir) {
                        ++$atlanan;
                    } else {
                        $satirlar[] = $satir;
                    }
                }
                $sayfaBilgisi = \is_array($veri['page_info'] ?? null) ? $veri['page_info'] : [];
                $toplam = $sayfaBilgisi['total_page'] ?? null;
                if (!\is_int($toplam) || $sayfa >= $toplam) {
                    break;
                }
            }
            $bas = $son->modify('+1 day');
        }

        return [$satirlar, $atlanan];
    }

    /**
     * @param array<string, int|string> $sorgu
     *
     * @return array<mixed>|string yanıtın data alanı ya da hata metni
     */
    private function sor(string $yol, string $anahtar, array $sorgu): array|string
    {
        try {
            $yanit = $this->http->request('GET', TiktokBaglantisi::API.$yol, [
                'headers' => ['Access-Token' => $anahtar],
                'query' => $sorgu,
                'timeout' => 30,
            ]);
            $kod = $yanit->getStatusCode();
            if ($kod < 200 || $kod >= 300) {
                return "TikTok HTTP {$kod}";
            }
            $govde = $yanit->toArray(false);
        } catch (ExceptionInterface) {
            return 'TikTok bağlantı veya yanıt hatası';
        }
        if (0 !== ($govde['code'] ?? null)) {
            $mesaj = \is_string($govde['message'] ?? null) ? $govde['message'] : 'bilinmeyen hata';
            $hataKodu = \is_int($govde['code'] ?? null) ? " (kod {$govde['code']})" : '';

            return 'TikTok reddetti: '.mb_substr(str_replace($anahtar, '…', $mesaj), 0, 300).$hataKodu;
        }

        return \is_array($govde['data'] ?? null) ? $govde['data'] : [];
    }

    /**
     * Satır: {dimensions: {campaign_id, stat_time_day: "YYYY-MM-DD HH:MM:SS"}, metrics: {spend, campaign_name}}.
     *
     * @param array<mixed> $s
     *
     * @return array{id: string, ad: string, gun: string, harcama: int, para: string}|null
     */
    private static function satir(array $s, string $para): ?array
    {
        $boyut = \is_array($s['dimensions'] ?? null) ? $s['dimensions'] : [];
        $olcu = \is_array($s['metrics'] ?? null) ? $s['metrics'] : [];
        $id = $boyut['campaign_id'] ?? null;
        $zaman = $boyut['stat_time_day'] ?? null;
        $ad = $olcu['campaign_name'] ?? null;
        $harcama = $olcu['spend'] ?? null;
        if (!\is_string($id) || !\is_string($ad) || !\is_string($zaman) || 1 !== preg_match('/^(\d{4}-\d\d-\d\d)( \d\d:\d\d:\d\d)?$/', $zaman, $e)
            || !\is_string($harcama)) {
            return null;
        }
        $kucuk = ParaBirimi::metindenKucuk($harcama, $para);

        return null === $kucuk ? null : ['id' => $id, 'ad' => $ad, 'gun' => $e[1], 'harcama' => $kucuk, 'para' => strtolower($para)];
    }
}
