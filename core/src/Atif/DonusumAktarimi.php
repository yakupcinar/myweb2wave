<?php

declare(strict_types=1);

namespace App\Atif;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** İzinli, ödenmiş Checkout oturumlarını Meta ve TikTok'a gönderir (H6b). */
final readonly class DonusumAktarimi
{
    public const AZAMI_DENEME = 10;

    private const SIFIR_ONDALIKLI_PARA_BIRIMLERI = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public function __construct(
        private Connection $db,
        private HttpClientInterface $http,
        private LoggerInterface $logger,
        #[Autowire('%env(META_PIXEL_ID)%')]
        private string $metaPixel,
        #[Autowire('%env(META_ACCESS_TOKEN)%')]
        private string $metaToken,
        #[Autowire('%env(META_GRAPH_API_VERSION)%')]
        private string $metaSurum,
        #[Autowire('%env(META_TEST_EVENT_CODE)%')]
        private string $metaTestKodu,
        #[Autowire('%env(TIKTOK_PIXEL_ID)%')]
        private string $tiktokPixel,
        #[Autowire('%env(TIKTOK_ACCESS_TOKEN)%')]
        private string $tiktokToken,
        #[Autowire('%env(TIKTOK_TEST_EVENT_CODE)%')]
        private string $tiktokTestKodu,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private string $huniAdresi,
    ) {
    }

    /**
     * Stripe ödeme oturumu ve onaylı ziyaret aynı transaction içindeyken çağrılır.
     *
     * @param array<string, mixed> $session
     */
    public function kuyrugaAl(Connection $db, array $session, string $app, int $eventTime): void
    {
        $oturum = $session['id'] ?? null;
        $tutar = $session['amount_total'] ?? null;
        $para = $session['currency'] ?? null;
        if (!\is_string($oturum)) {
            return;
        }
        if (!\is_int($tutar) || $tutar < 0 || !\is_string($para) || 3 !== \strlen($para)) {
            $this->baglamSil($db, $oturum);

            return;
        }

        $ziyaret = $db->fetchAssociative(
            'SELECT z.fbclid, z.ttclid, z.created_at, host(o.ip_adresi) AS ip_adresi, o.user_agent
             FROM odeme_oturumlari o JOIN huni_ziyaretleri z ON z.id = o.ziyaret_id
             WHERE o.stripe_checkout_session_id = :oturum AND z.app = :app AND z.izin_at IS NOT NULL
             -- İzin geri almayla yarışırsa sıra belirli olsun; geri alma önceyse olay kuyruğa girmesin.
             FOR UPDATE OF z',
            ['oturum' => $oturum, 'app' => $app],
        );
        if (!\is_array($ziyaret)) {
            $this->baglamSil($db, $oturum);

            return;
        }
        $ua = $ziyaret['user_agent'] ?? null;
        $ip = $ziyaret['ip_adresi'] ?? null;
        if (!\is_string($ua) || '' === $ua || !\is_string($ip) || '' === $ip) {
            $this->baglamSil($db, $oturum);

            return;
        }

        $url = rtrim($this->huniAdresi, '/').'/'.rawurlencode($app).'/basarili';
        $eventId = $oturum; // H6c tarayıcı Purchase olayı da aynı Stripe oturumunu kullanır.
        $anaBirimTutar = \in_array(strtoupper($para), self::SIFIR_ONDALIKLI_PARA_BIRIMLERI, true) ? $tutar : $tutar / 100;
        $metaUser = ['client_ip_address' => $ip, 'client_user_agent' => $ua];
        $fbclid = $ziyaret['fbclid'] ?? null;
        $createdAt = $ziyaret['created_at'] ?? null;
        if (\is_string($fbclid) && '' !== $fbclid && \is_string($createdAt)) {
            $zaman = strtotime($createdAt);
            if (false !== $zaman) {
                $metaUser['fbc'] = 'fb.1.'.($zaman * 1000).'.'.$fbclid;
            }
        }
        $meta = [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => $eventTime,
                'event_id' => $eventId,
                'action_source' => 'website',
                'event_source_url' => $url,
                'user_data' => $metaUser,
                'custom_data' => ['value' => $anaBirimTutar, 'currency' => strtoupper($para)],
            ]],
        ];
        if ('' !== $this->metaTestKodu) {
            $meta['test_event_code'] = $this->metaTestKodu;
        }
        $tiktokUser = ['ip' => $ip, 'user_agent' => $ua];
        if (\is_string($ziyaret['ttclid'] ?? null) && '' !== $ziyaret['ttclid']) {
            $tiktokUser['ttclid'] = $ziyaret['ttclid'];
        }
        $tiktok = [
            'event_source' => 'web',
            'event_source_id' => $this->tiktokPixel,
            'data' => [[
                'event' => 'CompletePayment',
                'event_time' => $eventTime,
                'event_id' => $eventId,
                'user' => $tiktokUser,
                'properties' => ['value' => $anaBirimTutar, 'currency' => strtoupper($para)],
                'page' => ['url' => $url],
            ]],
        ];
        if ('' !== $this->tiktokTestKodu) {
            $tiktok['test_event_code'] = $this->tiktokTestKodu;
        }

        $this->ekle($db, 'meta', $eventId, $meta, '' !== $this->metaPixel && '' !== $this->metaToken && 1 === preg_match('/^v\d+\.\d+$/', $this->metaSurum));
        $this->ekle($db, 'tiktok', $eventId, $tiktok, '' !== $this->tiktokPixel && '' !== $this->tiktokToken);
        // Kişisel eşleme sinyalleri artık yalnız teslim bekleyen kuyrukta kalır.
        $this->baglamSil($db, $oturum);
    }

    /**
     * Bekleyenleri gönderir; başarılı veya son denemede kişisel yükü kuyruktan çıkarır.
     *
     * @return array{gonderilen: int, basarisiz: int}
     */
    public function gonder(int $azami = 50): array
    {
        $sonuc = ['gonderilen' => 0, 'basarisiz' => 0];
        // Yedi günden eski yükü göndermeyiz; sıradaki cron geçersiz PII yükünü de temizler.
        $this->db->executeStatement(
            "UPDATE reklam_donusumleri SET payload = NULL, son_hata = coalesce(son_hata, 'süre doldu')
             WHERE payload IS NOT NULL AND created_at < now() - interval '7 days'",
        );
        $satirlar = $this->db->fetchAllAssociative(
            'SELECT id, saglayici, event_id, payload::text AS payload FROM reklam_donusumleri
             WHERE gonderildi_at IS NULL AND payload IS NOT NULL AND deneme < :azami
               AND (sonraki_deneme_at IS NULL OR sonraki_deneme_at <= now())
               AND created_at >= now() - interval \'7 days\'
             ORDER BY id LIMIT :limit',
            ['azami' => self::AZAMI_DENEME, 'limit' => $azami],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
        foreach ($satirlar as $s) {
            $saglayici = \is_string($s['saglayici']) ? $s['saglayici'] : '';
            $yuk = \is_string($s['payload']) ? json_decode($s['payload'], true) : null;
            if (!\is_array($yuk) || array_is_list($yuk)) {
                $this->db->executeStatement('UPDATE reklam_donusumleri SET payload = NULL, son_hata = :h WHERE id = :id', ['h' => 'geçersiz kuyruk gövdesi', 'id' => $s['id']]);
                continue;
            }
            /** @var array<string, mixed> $yuk */
            $hata = $this->tekGonder($saglayici, $yuk);
            if (null === $hata) {
                $this->db->executeStatement('UPDATE reklam_donusumleri SET gonderildi_at = now(), payload = NULL, son_hata = NULL WHERE id = :id', ['id' => $s['id']]);
                ++$sonuc['gonderilen'];
            } else {
                $this->db->executeStatement(
                    'UPDATE reklam_donusumleri SET deneme = deneme + 1, son_hata = :h,
                 sonraki_deneme_at = now() + make_interval(secs => LEAST(3600, 30 * power(2, deneme))),
                 payload = CASE WHEN deneme + 1 >= :azami THEN NULL ELSE payload END WHERE id = :id',
                    ['h' => mb_substr($hata, 0, 500), 'azami' => self::AZAMI_DENEME, 'id' => $s['id']],
                );
                $this->logger->warning('Reklam dönüşümü gönderilemedi', ['saglayici' => $saglayici, 'event_id' => $s['event_id'], 'hata' => $hata]);
                ++$sonuc['basarisiz'];
            }
        }

        return $sonuc;
    }

    /** @param array<string, mixed> $payload */
    private function ekle(Connection $db, string $saglayici, string $eventId, array $payload, bool $etkin): void
    {
        if (!$etkin) {
            return;
        }
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $db->executeStatement(
            'INSERT INTO reklam_donusumleri (saglayici, event_id, payload)
             VALUES (:saglayici, :event_id, CAST(:payload AS jsonb)) ON CONFLICT (saglayici, event_id) DO NOTHING',
            ['saglayici' => $saglayici, 'event_id' => $eventId, 'payload' => $json],
        );
    }

    private function baglamSil(Connection $db, string $oturum): void
    {
        $db->executeStatement('UPDATE odeme_oturumlari SET ip_adresi = NULL, user_agent = NULL WHERE stripe_checkout_session_id = :oturum', ['oturum' => $oturum]);
    }

    /** @param array<string, mixed> $payload */
    private function tekGonder(string $saglayici, array $payload): ?string
    {
        try {
            if ('meta' === $saglayici) {
                $yanit = $this->http->request('POST', 'https://graph.facebook.com/'.$this->metaSurum.'/'.rawurlencode($this->metaPixel).'/events', [
                    'query' => ['access_token' => $this->metaToken],
                    'json' => $payload,
                    'timeout' => 10,
                ]);
            } elseif ('tiktok' === $saglayici) {
                $yanit = $this->http->request('POST', 'https://business-api.tiktok.com/open_api/v1.3/event/track/', [
                    'headers' => ['Access-Token' => $this->tiktokToken],
                    'json' => $payload,
                    'timeout' => 10,
                ]);
            } else {
                return 'bilinmeyen sağlayıcı';
            }
            $kod = $yanit->getStatusCode();
            if ($kod < 200 || $kod >= 300) {
                return "HTTP {$kod}";
            }
            $govde = $yanit->toArray(false);
            if ('tiktok' === $saglayici && 0 !== ($govde['code'] ?? null)) {
                return 'TikTok API yanıtı başarısız';
            }
            if ('meta' === $saglayici && isset($govde['error'])) {
                return 'Meta API yanıtı başarısız';
            }

            return null;
        } catch (ExceptionInterface) {
            // İstemci hatası URL başlığında Meta erişim anahtarını içerebilir.
            return 'Reklam ağına bağlantı veya yanıt hatası';
        }
    }
}
