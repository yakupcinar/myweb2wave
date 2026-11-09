<?php

declare(strict_types=1);

namespace App\Olay;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * M7a (A20): olay deposundan giden webhook. İki adım (cron, app:webhook:gonder):
 *
 * 1. kuyruga(): her etkin hedef için imlecinden sonraki, seçtiği adlardaki
 *    uygulama olaylarını teslim satırına çevirir, imleci ilerletir. Yalnız
 *    şu an açık en eski işlemden önce bitenler (tuzak §53) — yarım işlemin
 *    küçük id'li olayı atlanmaz.
 * 2. gonder(): zamanı gelen teslimleri imzalı POST eder (Standard Webhooks).
 *    2xx teslim; değilse üstel bekleme (30 sn × 2^deneme, en çok 1 sa),
 *    AZAMI_DENEME'de vazgeçilir. İç ağ adresine gitmez (NoPrivateNetwork).
 *
 * Gövdede kişisel veri yok (A20, Ç90): olay kimliği, ad, ziyaret, sözlük verisi, zaman.
 */
final readonly class GidenWebhook
{
    public const AZAMI_DENEME = 10;

    private HttpClientInterface $http;

    public function __construct(
        private Connection $db,
        HttpClientInterface $http,
        private LoggerInterface $logger,
    ) {
        $this->http = new NoPrivateNetworkHttpClient($http);
    }

    /** @return int kuyruğa giren teslim sayısı */
    public function kuyruga(): int
    {
        $toplam = 0;
        foreach ($this->db->fetchFirstColumn('SELECT id FROM webhook_hedefleri WHERE aktif ORDER BY created_at') as $hedef) {
            $toplam += $this->db->transactional(function (Connection $db) use ($hedef): int {
                // İmleç kilitli okunur: eşzamanlı iki cron aynı olayı iki kez sıraya koymasın.
                $h = $db->fetchAssociative('SELECT app, olaylar::text AS olaylar, son_islem::text AS islem, son_olay_id FROM webhook_hedefleri WHERE id = :h FOR UPDATE', ['h' => $hedef]);
                if (false === $h) {
                    return 0;
                }
                $kosul = 'o.app = :app AND (o.islem, o.id) > (CAST(:islem AS xid8), :son) AND '.str_replace('islem', 'o.islem', Olaylar::BITMIS);
                $p = ['h' => $hedef, 'app' => $h['app'], 'islem' => $h['islem'], 'son' => $h['son_olay_id']];
                $yeni = $db->fetchFirstColumn(
                    "INSERT INTO webhook_teslimleri (hedef_id, olay_id)
                     SELECT :h, o.id FROM olaylar o WHERE {$kosul} AND o.ad = ANY (CAST(:adlar AS text[]))
                     ORDER BY o.islem, o.id ON CONFLICT DO NOTHING RETURNING olay_id",
                    $p + ['adlar' => $h['olaylar']],
                );
                // İmleç seçilmeyen adları da geçer (bir daha taranmasın).
                $db->executeStatement(
                    "UPDATE webhook_hedefleri h SET son_islem = s.islem, son_olay_id = s.id
                     FROM (SELECT o.islem, o.id FROM olaylar o WHERE {$kosul} ORDER BY o.islem DESC, o.id DESC LIMIT 1) s
                     WHERE h.id = :h",
                    $p,
                );

                return \count($yeni);
            });
        }

        return $toplam;
    }

    /** @return array{teslim: int, basarisiz: int} */
    public function gonder(int $azami = 100): array
    {
        $sonuc = ['teslim' => 0, 'basarisiz' => 0];
        $satirlar = $this->db->fetchAllAssociative(
            "SELECT t.id, t.olay_id, h.adres, h.sir, o.ad, o.ziyaret_id::text AS ziyaret, o.veri::text AS veri,
                    to_char(o.zaman AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS zaman
             FROM webhook_teslimleri t JOIN webhook_hedefleri h ON h.id = t.hedef_id JOIN olaylar o ON o.id = t.olay_id
             WHERE t.durum = 'bekliyor' AND t.sonraki_deneme_at <= now() AND h.aktif
             ORDER BY t.olay_id, t.id LIMIT :n",
            ['n' => $azami],
            ['n' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
        foreach ($satirlar as $s) {
            \assert(\is_string($s['adres']) && \is_string($s['sir']) && \is_string($s['veri']) && is_numeric($s['olay_id']));
            $govde = json_encode([
                'id' => (int) $s['olay_id'], 'ad' => $s['ad'], 'ziyaret' => $s['ziyaret'],
                'veri' => (object) json_decode($s['veri'], true, 16, \JSON_THROW_ON_ERROR), 'zaman' => $s['zaman'],
            ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            $hata = $this->tekGonder($s['adres'], $s['sir'], 'olay_'.$s['olay_id'], $govde);
            if (null === $hata) {
                $this->db->executeStatement("UPDATE webhook_teslimleri SET durum = 'teslim', teslim_at = now(), deneme = deneme + 1, son_hata = NULL WHERE id = :id", ['id' => $s['id']]);
                ++$sonuc['teslim'];
                continue;
            }
            $this->db->executeStatement(
                "UPDATE webhook_teslimleri SET deneme = deneme + 1, son_hata = :hata,
                     durum = CASE WHEN deneme + 1 >= :azami THEN 'vazgecildi' ELSE durum END,
                     sonraki_deneme_at = now() + make_interval(secs => LEAST(3600, 30 * power(2, deneme)))
                 WHERE id = :id",
                ['hata' => mb_substr($hata, 0, 500), 'azami' => self::AZAMI_DENEME, 'id' => $s['id']],
            );
            ++$sonuc['basarisiz'];
        }

        return $sonuc;
    }

    /**
     * Standard Webhooks imzası: base64(HMAC-SHA256(sır, "<id>.<zaman>.<gövde>")).
     * Sır "whsec_" + base64 rastgele; HMAC anahtarı base64 çözülmüş hâli.
     */
    public static function imza(string $sir, string $id, int $zaman, string $govde): string
    {
        $anahtar = base64_decode(str_starts_with($sir, 'whsec_') ? substr($sir, 6) : $sir, true);

        return 'v1,'.base64_encode(hash_hmac('sha256', "{$id}.{$zaman}.{$govde}", false === $anahtar ? $sir : $anahtar, true));
    }

    public static function yeniSir(): string
    {
        return 'whsec_'.base64_encode(random_bytes(24));
    }

    private function tekGonder(string $adres, string $sir, string $id, string $govde): ?string
    {
        $zaman = time();
        try {
            $yanit = $this->http->request('POST', $adres, [
                'headers' => ['Content-Type' => 'application/json', 'webhook-id' => $id, 'webhook-timestamp' => (string) $zaman, 'webhook-signature' => self::imza($sir, $id, $zaman, $govde)],
                'body' => $govde,
                'timeout' => 10,
                'max_redirects' => 0,
            ]);
            $kod = $yanit->getStatusCode();

            return $kod >= 200 && $kod < 300 ? null : "HTTP {$kod}";
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Giden webhook teslim edilemedi', ['adres' => parse_url($adres, \PHP_URL_HOST), 'hata' => $e->getMessage()]);

            return 'bağlantı hatası: '.mb_substr($e->getMessage(), 0, 200);
        }
    }
}
