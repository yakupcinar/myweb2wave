<?php

declare(strict_types=1);

namespace App\Atif;

use App\Billing\ParaBirimi;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * M5d (A18): Meta kampanya harcamasını gün başına çeker (Ads Insights,
 * level=campaign, time_increment=1). Son N gün her çekişte yeniden yazılır —
 * Meta yakın günleri düzeltir. A30: hesap ve anahtar uygulama başına
 * panelden (MetaBaglantisi); bağlı her hesap çekilir, hiçbiri yoksa kapalı.
 */
final readonly class MetaHarcamalari
{
    /** Bir çekişte en çok bu kadar sayfa (100 satır / sayfa); üstü hata sayılır. */
    public const AZAMI_SAYFA = 50;

    public function __construct(
        private Connection $db,
        private HttpClientInterface $http,
        private MetaBaglantisi $baglanti,
    ) {
    }

    public function acik(): bool
    {
        return $this->baglanti->surumGecerli() && [] !== $this->baglanti->harcamaHesaplari();
    }

    /**
     * Bugün dahil son $gun günü çeker ve yazar.
     *
     * @return array{satir: int, atlanan: int}|string satır sayısı ya da hata metni (anahtar içermez)
     */
    public function cek(int $gun, ?\DateTimeImmutable $bugun = null): array|string
    {
        if (!$this->acik()) {
            return 'Meta harcaması kapalı: panelde Entegrasyonlar › Meta (reklam hesabı + Insights anahtarı) ya da META_GRAPH_API_VERSION yok';
        }
        $bugun ??= new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $satirlar = [];
        $atlanan = 0;
        foreach ($this->baglanti->harcamaHesaplari() as $h) {
            $sonuc = $this->hesaptan($h['hesap'], $h['insights'], $gun, $bugun);
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
                     VALUES ('meta', :id, :ad, :gun, :harcama, :para)
                     ON CONFLICT (saglayici, kampanya_id, gun) DO UPDATE SET kampanya_adi = EXCLUDED.kampanya_adi,
                         harcama_kucuk = EXCLUDED.harcama_kucuk, para_birimi = EXCLUDED.para_birimi, guncellendi_at = now()",
                    $s,
                );
            }
        });

        return ['satir' => \count($satirlar), 'atlanan' => $atlanan];
    }

    /**
     * Tek hesabın sayfaları.
     *
     * @return array{list<array{id: string, ad: string, gun: string, harcama: int, para: string}>, int}|string satırlar + atlanan ya da hata
     */
    private function hesaptan(string $hesap, string $anahtar, int $gun, \DateTimeImmutable $bugun): array|string
    {
        $satirlar = [];
        $atlanan = 0;
        $sonraki = null;
        for ($sayfa = 0;; ++$sayfa) {
            if ($sayfa >= self::AZAMI_SAYFA) {
                return 'Meta yanıtı çok sayfalı (> '.self::AZAMI_SAYFA.')';
            }
            try {
                // Sonraki sayfa paging.next adresinden değil imleçten: adres anahtarı taşır.
                $yanit = $this->http->request('GET', 'https://graph.facebook.com/'.$this->baglanti->surum().'/'.rawurlencode($hesap).'/insights', [
                    'query' => [
                        'access_token' => $anahtar,
                        'level' => 'campaign',
                        'time_increment' => 1,
                        'time_range' => json_encode(['since' => $bugun->modify('-'.($gun - 1).' days')->format('Y-m-d'), 'until' => $bugun->format('Y-m-d')], \JSON_THROW_ON_ERROR),
                        'fields' => 'campaign_id,campaign_name,spend,account_currency',
                        'limit' => 100,
                    ] + (null === $sonraki ? [] : ['after' => $sonraki]),
                    'timeout' => 30,
                ]);
                $kod = $yanit->getStatusCode();
                if ($kod < 200 || $kod >= 300) {
                    return "Meta Insights HTTP {$kod}";
                }
                $govde = $yanit->toArray(false);
            } catch (ExceptionInterface) {
                // İstemci hatası adresi (ve sorgudaki anahtarı) içerebilir.
                return 'Meta Insights bağlantı veya yanıt hatası';
            }
            foreach (\is_array($govde['data'] ?? null) ? $govde['data'] : [] as $s) {
                $satir = \is_array($s) ? self::satir($s) : null;
                if (null === $satir) {
                    ++$atlanan;
                } else {
                    $satirlar[] = $satir;
                }
            }
            $paging = $govde['paging'] ?? null;
            $imlecler = \is_array($paging) ? ($paging['cursors'] ?? null) : null;
            $sonraki = \is_array($paging) && isset($paging['next']) && \is_array($imlecler) && \is_string($imlecler['after'] ?? null) ? $imlecler['after'] : null;
            if (null === $sonraki) {
                break;
            }
        }

        return [$satirlar, $atlanan];
    }

    /**
     * @param array<mixed> $s
     *
     * @return array{id: string, ad: string, gun: string, harcama: int, para: string}|null
     */
    private static function satir(array $s): ?array
    {
        $id = $s['campaign_id'] ?? null;
        $ad = $s['campaign_name'] ?? null;
        $gun = $s['date_start'] ?? null;
        $para = $s['account_currency'] ?? null;
        $harcama = $s['spend'] ?? null;
        if (!\is_string($id) || !\is_string($ad) || !\is_string($gun) || 1 !== preg_match('/^\d{4}-\d\d-\d\d$/', $gun)
            || !\is_string($para) || 1 !== preg_match('/^[A-Za-z]{3}$/', $para) || !\is_string($harcama)) {
            return null;
        }
        $kucuk = ParaBirimi::metindenKucuk($harcama, $para);

        return null === $kucuk ? null : ['id' => $id, 'ad' => $ad, 'gun' => $gun, 'harcama' => $kucuk, 'para' => strtolower($para)];
    }
}
