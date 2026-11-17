<?php

declare(strict_types=1);

namespace App\Posta;

use App\Kimlik\Hak;
use App\Uygulama\UygulamaAyarlari;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\MessageStreamHeader;
use Symfony\Component\Mailer\MailerInterface;

/**
 * M8b (A22): terk edilen ödeme kurtarma — PAZARLAMA e-postası: yalnız huninin
 * izin kutusu işaretliyse (huni_epostalari.pazarlama_izni_at), `broadcast`
 * akışından (abonelikten çıkma bağlantısı ve başlıklarını Postmark ekler,
 * çıkan Subscription Change ile bastırma listesine gelir).
 *
 * - Bağlantı Stripe'ın kurtarma adresi (oturum dolunca verilir, 30 gün;
 *   açılınca aynı satırların kopyası yeni oturum — metadata, dönüş adresi,
 *   e-posta korunuyor, ölçüldü 2026-11-10).
 * - Kurtarma adresi yalnız dolan oturumda var (webhook yazar) — "doldu mu"
 *   ayrıca tutulmaz.
 * - Adım 1 oturum dolunca (OTURUM_SURESI_SN), adım 2 oturum açıldıktan 24 sa
 *   sonra; gecikmişlerden yalnız sonuncusu.
 * - Durur: ödendi (bu ya da aynı ziyaretten başka oturum), aynı e-postayla bu
 *   uygulamada erişim veren abonelik var, izin geri alındı, adres bastırıldı.
 * - huni_epostalari.eposta küçük harf (DB kısıtı); abonelikteki Stripe'tan
 *   geldiği gibi, lower() ile.
 * - Aynı kişi (uygulama + e-posta) için AZAMI_GUN içinde tek dizi: birkaç
 *   oturumu yarım bırakan kişi birkaç dizi almasın.
 */
final readonly class KurtarmaDizisi
{
    public const DIZI = 'kurtarma';
    /** adım → oturum açıldıktan sonra saat (adım 1: dolar dolmaz) */
    public const ADIMLAR = [1 => 0, 2 => 24];
    public const AZAMI_GUN = 7;

    public function __construct(
        private Connection $db,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private UygulamaAyarlari $ayarlar,
        private EpostaSablonlari $sablonlar,
    ) {
    }

    /** Asla atmaz; gönderilemeyen sonraki çağrıda yeniden denenir. Gönderilen sayısını döner. */
    public function gonder(int $azami = 50): int
    {
        $gonderilen = 0;
        foreach (array_reverse(self::ADIMLAR, true) as $adim => $saat) {
            foreach ($this->adaylar($adim, $saat, $azami) as $oturum) {
                try {
                    $gonderilen += $this->db->transactional(fn (Connection $db): int => $this->adimGonder($db, $oturum, $adim));
                } catch (\Throwable $e) {
                    $this->logger->error('Kurtarma e-postası gönderilemedi', ['oturum' => $oturum, 'adim' => $adim, 'exception' => $e]);
                }
            }
        }

        return $gonderilen;
    }

    /** @return list<string> */
    private function adaylar(int $adim, int $saat, int $azami): array
    {
        $kimlikler = $this->db->fetchFirstColumn(
            'SELECT o.stripe_checkout_session_id FROM odeme_oturumlari o
             JOIN huni_ziyaretleri z ON z.id = o.ziyaret_id
             JOIN huni_epostalari e ON e.ziyaret_id = o.ziyaret_id
             WHERE o.kurtarma_url IS NOT NULL
               AND e.pazarlama_izni_at IS NOT NULL
               AND o.created_at <= now() - make_interval(hours => :saat)
               AND o.created_at > now() - make_interval(days => :gun)
               AND NOT EXISTS (SELECT 1 FROM odeme_oturumlari o2
                               WHERE o2.ziyaret_id = o.ziyaret_id AND (o2.abonelik_at IS NOT NULL OR o2.tamamlandi_at IS NOT NULL))
               AND NOT EXISTS (SELECT 1 FROM subscriptions s
                               WHERE s.app = z.app AND lower(s.email) = e.eposta AND s.status IN (:erisim))
               AND NOT EXISTS (SELECT 1 FROM posta_dizisi d
                               WHERE d.kaynak = o.stripe_checkout_session_id AND d.dizi = :dizi AND d.adim >= :adim)
               AND NOT EXISTS (SELECT 1 FROM posta_dizisi d
                               JOIN odeme_oturumlari o3 ON o3.stripe_checkout_session_id = d.kaynak
                               JOIN huni_ziyaretleri z3 ON z3.id = o3.ziyaret_id
                               JOIN huni_epostalari e3 ON e3.ziyaret_id = o3.ziyaret_id
                               WHERE d.dizi = :dizi AND d.kaynak <> o.stripe_checkout_session_id AND z3.app = z.app
                                 AND e3.eposta = e.eposta AND d.gonderildi_at > now() - make_interval(days => :gun))
               AND NOT EXISTS (SELECT 1 FROM posta_bastirma p
                               WHERE p.email = e.eposta AND p.akis = :akis AND p.bastirildi)
             ORDER BY o.created_at LIMIT :azami',
            ['saat' => $saat, 'gun' => self::AZAMI_GUN, 'erisim' => Hak::ERISIM_VEREN, 'dizi' => self::DIZI,
                'adim' => $adim, 'akis' => Bastirma::YAYIN, 'azami' => $azami],
            ['erisim' => ArrayParameterType::STRING],
        );

        return array_values(array_filter($kimlikler, \is_string(...)));
    }

    private function adimGonder(Connection $db, string $oturum, int $adim): int
    {
        // Kayıt önce: aynı anda koşan ikinci cron aynı adımı göndermesin.
        $yeni = $db->executeStatement(
            'INSERT INTO posta_dizisi (kaynak, dizi, adim) VALUES (?, ?, ?) ON CONFLICT DO NOTHING',
            [$oturum, self::DIZI, $adim],
        );
        $s = $db->fetchAssociative(
            'SELECT z.app, e.eposta, o.kurtarma_url FROM odeme_oturumlari o JOIN huni_epostalari e ON e.ziyaret_id = o.ziyaret_id
             JOIN huni_ziyaretleri z ON z.id = o.ziyaret_id WHERE o.stripe_checkout_session_id = ?',
            [$oturum],
        );
        if (1 !== $yeni || false === $s || !\is_string($s['app']) || !\is_string($s['eposta']) || !\is_string($s['kurtarma_url'])) {
            return 0;
        }
        // E1a: şablon (panelden), huninin dilinde.
        $e = $this->sablonlar->doldur($s['app'], 'kurtarma_'.$adim, EpostaSablonlari::oturumDili($db, $oturum), ['baglanti' => $s['kurtarma_url']]);
        $posta = $this->ayarlar->eposta($s['app'])->to($s['eposta'])->subject($e['konu'])->text($e['metin']);
        $posta->getHeaders()->add(new MessageStreamHeader(Bastirma::YAYIN));
        $this->mailer->send($posta);

        return 1;
    }
}
