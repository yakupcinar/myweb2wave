<?php

declare(strict_types=1);

namespace App\Kimlik;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Ödeme sonrası giriş bağlantısı (Ç46; RevenueCat Redemption Links'in
 * çizgisi). Ödeme kimliği (cs_…) giriş anahtarı DEĞİLDİR — başarı sayfasının
 * adresinde durur, geçmişe ve piksellere sızar. Onun yerine:
 *
 * - Jeton 256 bit rastgele, düz saklanmaz (SHA-256), TEK kullanımlık,
 *   GECERLILIK_SA saat geçerli.
 * - Başarı sayfası jetonu yalnız ödeme tamamlandıktan (webhook) sonraki
 *   PENCERE_DK dakikada ve oturum başına en çok AZAMI_JETON kez alabilir —
 *   sızan cs_ ile sonradan jeton basılamaz.
 * - Aynı bağlantı ödeme e-postasına bir kez gider (kurtarma yolu; uygulamayı
 *   sonra kuran kişi için).
 * - Kullanılınca e-posta kodu yoluyla aynı bağlama (Baglama).
 */
final readonly class GirisBaglantisi
{
    public const GECERLILIK_SA = 24;
    public const PENCERE_DK = 60;
    public const AZAMI_JETON = 3;

    public function __construct(
        private Connection $db,
        private MailerInterface $mailer,
        private Baglama $baglama,
        private LoggerInterface $logger,
        /** @var array<string, string> uygulama → özel URL şeması */
        #[Autowire('%uygulama_semalari%')]
        private array $semalar,
        #[Autowire('%env(MAIL_GONDEREN)%')]
        private string $gonderen,
    ) {
    }

    /**
     * Başarı sayfası için.
     *
     * @return array{uygulama_adresi: string, son_kullanma: string}|'hazir_degil'|'pencere_kapandi'|'cok_istek'
     */
    public function sayfaIcin(string $app, string $oturum): array|string
    {
        /* @var array{uygulama_adresi: string, son_kullanma: string}|'hazir_degil'|'pencere_kapandi'|'cok_istek' */
        return $this->db->transactional(function (Connection $db) use ($app, $oturum): array|string {
            $satir = $db->fetchAssociative(
                "SELECT email, odeme_tamamlandi_at > now() - interval '".self::PENCERE_DK." minutes' AS acik
                 FROM subscriptions
                 WHERE stripe_checkout_session_id = ? AND app = ? AND odeme_tamamlandi_at IS NOT NULL AND email IS NOT NULL
                 FOR UPDATE",
                [$oturum, $app],
            );
            if (false === $satir || !\is_string($satir['email'])) {
                return 'hazir_degil';
            }
            if (true !== $satir['acik']) {
                return 'pencere_kapandi';
            }
            $sayi = $db->fetchOne('SELECT count(*) FROM giris_jetonlari WHERE stripe_checkout_session_id = ?', [$oturum]);
            if (is_numeric($sayi) && (int) $sayi >= self::AZAMI_JETON) {
                return 'cok_istek';
            }

            return $this->uret($db, $app, $satir['email'], $oturum);
        });
    }

    /**
     * Uygulamanın sunucusu için: jeton geçerliyse kullanıcıyı kişiye bağlar.
     *
     * @return array{sonuc: 'baglandi', kisi: string}|array{sonuc: 'invalid_token'|'expired_token'}
     */
    public function kullan(string $app, string $jeton, string $kullanici): array
    {
        /* @var array{sonuc: 'baglandi', kisi: string}|array{sonuc: 'invalid_token'|'expired_token'} */
        return $this->db->transactional(function (Connection $db) use ($app, $jeton, $kullanici): array {
            // Kullanılmış ve bilinmeyen jeton aynı cevabı alır.
            $satir = $db->fetchAssociative(
                'SELECT id, email, son_kullanma < now() AS dolmus FROM giris_jetonlari
                 WHERE ozet = ? AND app = ? AND kullanildi_at IS NULL FOR UPDATE',
                [self::ozet($jeton), $app],
            );
            if (false === $satir || !\is_string($satir['email'])) {
                return ['sonuc' => 'invalid_token'];
            }
            if (true === $satir['dolmus']) {
                return ['sonuc' => 'expired_token'];
            }
            $db->executeStatement('UPDATE giris_jetonlari SET kullanildi_at = now() WHERE id = ?', [$satir['id']]);

            return ['sonuc' => 'baglandi', 'kisi' => $this->baglama->bagla($db, $app, $satir['email'], $kullanici)];
        });
    }

    /**
     * Ödemesi tamamlanmış, giriş e-postası gitmemiş abonelikler. Webhook'tan
     * sonra çağrılır; asla atmaz — gönderilemeyen bir sonraki çağrıda yeniden
     * denenir. E-posta işlem İÇİNDE gider: işlem düşerse ikinci e-posta
     * gidebilir, ama hiç gitmemesi daha kötü.
     */
    public function epostalariGonder(int $azami = 20): void
    {
        $oturumlar = $this->db->fetchFirstColumn(
            'SELECT stripe_checkout_session_id FROM subscriptions
             WHERE odeme_tamamlandi_at IS NOT NULL AND giris_epostasi_at IS NULL AND email IS NOT NULL
             ORDER BY odeme_tamamlandi_at LIMIT ?',
            [$azami],
        );
        foreach ($oturumlar as $oturum) {
            try {
                $this->db->transactional(function (Connection $db) use ($oturum): void {
                    $satir = $db->fetchAssociative(
                        'SELECT app, email FROM subscriptions
                         WHERE stripe_checkout_session_id = ? AND giris_epostasi_at IS NULL FOR UPDATE SKIP LOCKED',
                        [$oturum],
                    );
                    if (false === $satir || !\is_string($satir['app']) || !\is_string($satir['email']) || !\is_string($oturum)) {
                        return;
                    }
                    $baglanti = $this->uret($db, $satir['app'], $satir['email'], $oturum);
                    $db->executeStatement('UPDATE subscriptions SET giris_epostasi_at = now() WHERE stripe_checkout_session_id = ?', [$oturum]);
                    $this->mailer->send((new Email())
                        ->from($this->gonderen)
                        ->to($satir['email'])
                        ->subject('Uygulamaya giriş bağlantınız')
                        ->text("Aboneliğiniz için teşekkürler.\n\nUygulamayı telefonunuza kurduktan sonra bu bağlantıya telefonunuzda dokunun:\n{$baglanti['uygulama_adresi']}\n\nBağlantı tek kullanımlıktır ve ".self::GECERLILIK_SA.' saat geçerlidir. Süresi dolarsa uygulamada "Aboneliğimi bağla" ile bu e-posta adresini girin.'));
                });
            } catch (\Throwable $e) {
                $this->logger->error('Giriş bağlantısı e-postası gönderilemedi', ['oturum' => $oturum, 'exception' => $e]);
            }
        }
    }

    /** @return array{uygulama_adresi: string, son_kullanma: string} */
    private function uret(Connection $db, string $app, string $email, string $oturum): array
    {
        $sema = $this->semalar[$app] ?? throw new \LogicException("'{$app}' için URL şeması yapılandırılmamış");
        $jeton = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $son = $db->fetchOne(
            "INSERT INTO giris_jetonlari (app, email, stripe_checkout_session_id, ozet, son_kullanma)
             VALUES (?, ?, ?, ?, now() + interval '".self::GECERLILIK_SA." hours') RETURNING son_kullanma",
            [$app, mb_strtolower($email), $oturum, self::ozet($jeton)],
        );

        return [
            'uygulama_adresi' => $sema.'://giris?jeton='.$jeton,
            'son_kullanma' => (new \DateTimeImmutable(\is_string($son) ? $son : 'now'))->format(\DATE_ATOM),
        ];
    }

    private static function ozet(string $jeton): string
    {
        return hash('sha256', $jeton);
    }
}
