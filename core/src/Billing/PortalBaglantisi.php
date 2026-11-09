<?php

declare(strict_types=1);

namespace App\Billing;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * M6b2 (A19): uygulamayı silen müşteri de aboneliğini yönetebilsin — huni
 * sayfasında e-posta → tek kullanımlık bağlantı → Stripe portalı.
 *
 * - Hesap sızdırmaz: istek her zaman "gönderildi" der; e-posta yalnız o
 *   uygulamada aboneliği olan adrese gider.
 * - E-posta başına PENCERE_DK dakikada en çok AZAMI kez gönderilir.
 * - Jeton GECERLILIK_DK dakika, bir kez; düz saklanmaz.
 */
final readonly class PortalBaglantisi
{
    public const GECERLILIK_DK = 60;
    public const PENCERE_DK = 60;
    public const AZAMI = 3;

    public function __construct(
        private Connection $db,
        private MailerInterface $mailer,
        private MusteriPortali $portal,
        private LoggerInterface $logger,
        #[Autowire('%env(MAIL_GONDEREN)%')]
        private string $gonderen,
    ) {
    }

    /** @param \Closure(string): string $adres jeton → mutlak bağlantı adresi */
    public function iste(string $app, string $email, \Closure $adres): void
    {
        $email = mb_strtolower(trim($email));
        if (null === $this->portal->musteri($app, $email)) {
            return;
        }
        $sayi = $this->db->fetchOne(
            "SELECT count(*) FROM portal_jetonlari WHERE app = ? AND email = ? AND created_at > now() - interval '".self::PENCERE_DK." minutes'",
            [$app, $email],
        );
        if (is_numeric($sayi) && (int) $sayi >= self::AZAMI) {
            return;
        }
        $jeton = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->executeStatement(
            "INSERT INTO portal_jetonlari (ozet, app, email, son_kullanma) VALUES (?, ?, ?, now() + interval '".self::GECERLILIK_DK." minutes')",
            [hash('sha256', $jeton), $app, $email],
        );
        try {
            $this->mailer->send((new Email())
                ->from($this->gonderen)
                ->to($email)
                ->subject('Aboneliğinizi yönetin')
                ->text("Aboneliğinizi yönetmek (kart, iptal, faturalar) için bağlantı:\n{$adres($jeton)}\n\nBağlantı tek kullanımlıktır ve ".self::GECERLILIK_DK.' dakika geçerlidir. Bu isteği siz yapmadıysanız e-postayı yok sayın.'));
        } catch (\Throwable $e) {
            $this->logger->error('Portal bağlantısı e-postası gönderilemedi', ['exception' => $e]);
        }
    }

    /**
     * Jeton geçerliyse bir kez kullanır ve portal adresini döndürür.
     *
     * @return array{adres: string}|'gecersiz'|'suresi_doldu'|'abonelik_yok'|'portal_hatasi'
     */
    public function kullan(string $jeton): array|string
    {
        $satir = $this->db->transactional(fn (Connection $db): array|false => $db->fetchAssociative(
            'UPDATE portal_jetonlari SET kullanildi_at = now() WHERE ozet = ? AND kullanildi_at IS NULL
             RETURNING app, email, son_kullanma < now() AS dolmus',
            [hash('sha256', $jeton)],
        ));
        if (false === $satir || !\is_string($satir['app']) || !\is_string($satir['email'])) {
            return 'gecersiz';
        }
        if (true === $satir['dolmus']) {
            return 'suresi_doldu';
        }
        $musteri = $this->portal->musteri($satir['app'], $satir['email']);
        if (null === $musteri) {
            return 'abonelik_yok';
        }
        $sonuc = $this->portal->musteriIcin($musteri, $satir['app']);

        return \is_array($sonuc) ? $sonuc : 'portal_hatasi';
    }
}
