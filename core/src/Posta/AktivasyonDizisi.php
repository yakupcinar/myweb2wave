<?php

declare(strict_types=1);

namespace App\Posta;

use App\Kimlik\GirisBaglantisi;
use App\Kimlik\Hak;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * M8a2 (A22): ödeyip uygulamaya bağlanmayana hatırlatma. İşlem e-postası
 * (aboneliğin kendisi için), pazarlama izni gerekmez; `outbound` akışı.
 *
 * - İlk adım ödeme e-postasının kendisi (GirisBaglantisi); bu dizi onun
 *   ardından ADIMLAR saatlerinde, her seferinde YENİ giriş bağlantısıyla
 *   (eskisi 24 saatte dolar).
 * - Durur: uygulamaya bağlandı, abonelik erişim vermiyor (iptal / bitti),
 *   adres bastırılmış.
 * - Gecikmiş adım üst üste gitmez: zamanı gelen EN SON adım gönderilir,
 *   öncekiler atlanır (cron günlerce durduysa kişi iki e-posta almasın).
 * - AZAMI_GUN'den eski ödemeye başlamaz: özellik açıldığında birikmiş
 *   listeye toplu gönderim olmasın.
 */
final readonly class AktivasyonDizisi
{
    public const DIZI = 'aktivasyon';
    /** adım → ödemeden sonra saat */
    public const ADIMLAR = [1 => 24, 2 => 72];
    public const AZAMI_GUN = 7;

    public function __construct(
        private Connection $db,
        private MailerInterface $mailer,
        private GirisBaglantisi $giris,
        private LoggerInterface $logger,
        /** @var array<string, string|null> uygulama → mağaza adresi (boş = yazılmaz) */
        #[Autowire('%uygulama_magaza_adresleri%')]
        private array $magazalar,
        #[Autowire('%env(MAIL_GONDEREN)%')]
        private string $gonderen,
    ) {
    }

    /** Asla atmaz; gönderilemeyen sonraki çağrıda yeniden denenir. Gönderilen sayısını döner. */
    public function gonder(int $azami = 50): int
    {
        $gonderilen = 0;
        foreach (array_reverse(self::ADIMLAR, true) as $adim => $saat) {
            foreach ($this->adaylar($adim, $saat, $azami) as $abonelik) {
                try {
                    $gonderilen += $this->db->transactional(fn (Connection $db): int => $this->adimGonder($db, $abonelik, $adim));
                } catch (\Throwable $e) {
                    $this->logger->error('Aktivasyon e-postası gönderilemedi', ['abonelik' => $abonelik, 'adim' => $adim, 'exception' => $e]);
                }
            }
        }

        return $gonderilen;
    }

    /** @return list<string> */
    private function adaylar(int $adim, int $saat, int $azami): array
    {
        $kimlikler = $this->db->fetchFirstColumn(
            'SELECT s.stripe_subscription_id FROM subscriptions s
             WHERE s.giris_epostasi_at IS NOT NULL AND s.email IS NOT NULL AND s.stripe_checkout_session_id IS NOT NULL
               AND s.status IN (:erisim)
               AND s.odeme_tamamlandi_at <= now() - make_interval(hours => :saat)
               AND s.odeme_tamamlandi_at > now() - make_interval(days => :gun)
               AND NOT EXISTS (SELECT 1 FROM baglantilar b JOIN kisiler k ON k.id = b.kisi_id
                               WHERE b.app = s.app AND k.email = lower(s.email))
               AND NOT EXISTS (SELECT 1 FROM posta_dizisi d
                               WHERE d.kaynak = s.stripe_subscription_id AND d.dizi = :dizi AND d.adim >= :adim)
               AND NOT EXISTS (SELECT 1 FROM posta_bastirma p
                               WHERE p.email = lower(s.email) AND p.akis = :akis AND p.bastirildi)
             ORDER BY s.odeme_tamamlandi_at LIMIT :azami',
            ['erisim' => Hak::ERISIM_VEREN, 'saat' => $saat, 'gun' => self::AZAMI_GUN, 'dizi' => self::DIZI,
                'adim' => $adim, 'akis' => Bastirma::ISLEM, 'azami' => $azami],
            ['erisim' => ArrayParameterType::STRING],
        );

        return array_values(array_filter($kimlikler, \is_string(...)));
    }

    private function adimGonder(Connection $db, string $abonelik, int $adim): int
    {
        // Kayıt önce: aynı anda koşan ikinci cron aynı adımı göndermesin.
        $yeni = $db->executeStatement(
            'INSERT INTO posta_dizisi (kaynak, dizi, adim) VALUES (?, ?, ?) ON CONFLICT DO NOTHING',
            [$abonelik, self::DIZI, $adim],
        );
        $s = $db->fetchAssociative('SELECT app, email, stripe_checkout_session_id FROM subscriptions WHERE stripe_subscription_id = ?', [$abonelik]);
        if (1 !== $yeni || false === $s || !\is_string($s['app']) || !\is_string($s['email']) || !\is_string($s['stripe_checkout_session_id'])) {
            return 0;
        }
        $baglanti = $this->giris->uret($db, $s['app'], $s['email'], $s['stripe_checkout_session_id']);
        $magaza = $this->magazalar[$s['app']] ?? null;
        $kur = null === $magaza || '' === $magaza
            ? 'Uygulamayı telefonunuza kurun'
            : "Uygulamayı telefonunuza kurun:\n{$magaza}";
        $this->mailer->send((new Email())
            ->from($this->gonderen)
            ->to($s['email'])
            ->subject(1 === $adim ? 'Aboneliğiniz hazır — uygulamaya bağlanın' : 'Aboneliğinizi henüz kullanmadınız')
            ->text("Aboneliğiniz etkin, ama uygulamaya henüz bağlanmadınız.\n\n1. {$kur}\n2. Bu bağlantıya telefonunuzda dokunun:\n{$baglanti['uygulama_adresi']}\n\nBağlantı tek kullanımlıktır ve ".GirisBaglantisi::GECERLILIK_SA." saat geçerlidir. Süresi dolarsa uygulamada \"Aboneliğimi bağla\" ile bu e-posta adresini girin.\n\nBu e-postayı aboneliğiniz nedeniyle alıyorsunuz; bağlandığınızda bir daha gönderilmez."));

        return 1;
    }
}
