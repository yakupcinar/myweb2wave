<?php

declare(strict_types=1);

namespace App\Atif;

use App\Billing\ParaBirimi;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * M5d (A18): Meta kampanya harcamasını gün başına çeker (Ads Insights,
 * level=campaign, time_increment=1). Son N gün her çekişte yeniden yazılır —
 * Meta yakın günleri düzeltir. Hesap ya da anahtar yoksa kapalı.
 */
final readonly class MetaHarcamalari
{
    /** Bir çekişte en çok bu kadar sayfa (100 satır / sayfa); üstü hata sayılır. */
    public const AZAMI_SAYFA = 50;

    public function __construct(
        private Connection $db,
        private HttpClientInterface $http,
        #[Autowire('%env(META_AD_ACCOUNT_ID)%')]
        private string $hesap,
        #[Autowire('%env(META_INSIGHTS_ACCESS_TOKEN)%')]
        private string $anahtar,
        #[Autowire('%env(META_GRAPH_API_VERSION)%')]
        private string $surum,
    ) {
    }

    public function acik(): bool
    {
        return 1 === preg_match('/^act_\d+$/', $this->hesap) && '' !== $this->anahtar && 1 === preg_match('/^v\d+\.\d+$/', $this->surum);
    }

    /**
     * Bugün dahil son $gun günü çeker ve yazar.
     *
     * @return array{satir: int, atlanan: int}|string satır sayısı ya da hata metni (anahtar içermez)
     */
    public function cek(int $gun, ?\DateTimeImmutable $bugun = null): array|string
    {
        if (!$this->acik()) {
            return 'Meta harcaması kapalı (META_AD_ACCOUNT_ID, META_INSIGHTS_ACCESS_TOKEN, META_GRAPH_API_VERSION)';
        }
        $bugun ??= new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $satirlar = [];
        $atlanan = 0;
        $sonraki = null;
        for ($sayfa = 0;; ++$sayfa) {
            if ($sayfa >= self::AZAMI_SAYFA) {
                return 'Meta yanıtı çok sayfalı (> '.self::AZAMI_SAYFA.')';
            }
            try {
                // Sonraki sayfa paging.next adresinden değil imleçten: adres anahtarı taşır.
                $yanit = $this->http->request('GET', 'https://graph.facebook.com/'.$this->surum.'/'.rawurlencode($this->hesap).'/insights', [
                    'query' => [
                        'access_token' => $this->anahtar,
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
