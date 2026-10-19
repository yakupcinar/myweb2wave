<?php

declare(strict_types=1);

namespace App\Kimlik;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * E-posta koduyla bağlama (A5). Kod düz saklanmaz (HMAC özeti), 10 dk
 * geçerli, 5 yanlış denemede kilitlenir, tek kullanımlık; yeni kod eskisini
 * geçersiz kılar; aynı (uygulama, e-posta) için 10 dk'da en çok 3 kod.
 */
final readonly class DogrulamaKodu
{
    public const GECERLILIK_DK = 10;
    public const AZAMI_DENEME = 5;
    public const PENCERE_AZAMI_KOD = 3;

    public function __construct(
        private Connection $db,
        private MailerInterface $mailer,
        #[Autowire('%kernel.secret%')]
        private string $sir,
        #[Autowire('%env(MAIL_GONDEREN)%')]
        private string $gonderen,
    ) {
    }

    /** @return 'gonderildi'|'cok_istek' */
    public function iste(string $app, string $email): string
    {
        $email = self::normal($email);
        $son = $this->db->fetchOne(
            "SELECT count(*) FROM dogrulama_kodlari WHERE app = ? AND email = ? AND created_at > now() - interval '10 minutes'",
            [$app, $email],
        );
        if (is_numeric($son) && (int) $son >= self::PENCERE_AZAMI_KOD) {
            return 'cok_istek';
        }

        $kod = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->db->transactional(function (Connection $db) use ($app, $email, $kod): void {
            $db->executeStatement(
                'UPDATE dogrulama_kodlari SET gecersiz_at = now() WHERE app = ? AND email = ? AND kullanildi_at IS NULL AND gecersiz_at IS NULL',
                [$app, $email],
            );
            $db->executeStatement(
                "INSERT INTO dogrulama_kodlari (app, email, kod_ozeti, son_kullanma) VALUES (?, ?, ?, now() + interval '".self::GECERLILIK_DK." minutes')",
                [$app, $email, $this->ozet($app, $email, $kod)],
            );
        });

        $this->mailer->send((new Email())
            ->from($this->gonderen)
            ->to($email)
            ->subject('Doğrulama kodunuz')
            ->text("Kodunuz: {$kod}\n\nKod ".self::GECERLILIK_DK.' dakika geçerlidir. Bu isteği siz yapmadıysanız bu e-postayı yok sayın.'));

        return 'gonderildi';
    }

    /**
     * @return array{sonuc: 'baglandi', kisi: string}|array{sonuc: 'invalid_code'|'expired_code'|'too_many_attempts'}
     */
    public function dogrula(string $app, string $email, string $kod, string $kullanici): array
    {
        $email = self::normal($email);

        /* @var array{sonuc: 'baglandi', kisi: string}|array{sonuc: 'invalid_code'|'expired_code'|'too_many_attempts'} */
        return $this->db->transactional(function (Connection $db) use ($app, $email, $kod, $kullanici): array {
            $satir = $db->fetchAssociative(
                'SELECT id, kod_ozeti, deneme, son_kullanma < now() AS dolmus FROM dogrulama_kodlari
                 WHERE app = ? AND email = ? AND kullanildi_at IS NULL AND gecersiz_at IS NULL
                 ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$app, $email],
            );
            if (false === $satir) {
                return ['sonuc' => 'invalid_code'];
            }
            $deneme = is_numeric($satir['deneme']) ? (int) $satir['deneme'] : self::AZAMI_DENEME;
            if ($deneme >= self::AZAMI_DENEME) {
                return ['sonuc' => 'too_many_attempts'];
            }
            if (true === $satir['dolmus']) {
                return ['sonuc' => 'expired_code'];
            }
            $kayitli = \is_string($satir['kod_ozeti']) ? $satir['kod_ozeti'] : '';
            if ('' === $kayitli || !hash_equals($kayitli, $this->ozet($app, $email, $kod))) {
                $db->executeStatement('UPDATE dogrulama_kodlari SET deneme = deneme + 1 WHERE id = ?', [$satir['id']]);

                return ['sonuc' => 'invalid_code'];
            }

            $db->executeStatement('UPDATE dogrulama_kodlari SET kullanildi_at = now() WHERE id = ?', [$satir['id']]);
            $kisi = $db->fetchOne(
                'INSERT INTO kisiler (email) VALUES (?) ON CONFLICT (email) DO UPDATE SET email = EXCLUDED.email RETURNING id',
                [$email],
            );
            if (!\is_string($kisi)) {
                throw new \LogicException('kisiler INSERT … RETURNING id dizge döndürmedi');
            }
            $db->executeStatement(
                'INSERT INTO baglantilar (app, harici_kullanici, kisi_id) VALUES (?, ?, ?)
                 ON CONFLICT (app, harici_kullanici) DO UPDATE SET kisi_id = EXCLUDED.kisi_id',
                [$app, $kullanici, $kisi],
            );

            return ['sonuc' => 'baglandi', 'kisi' => $kisi];
        });
    }

    public static function normal(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function ozet(string $app, string $email, string $kod): string
    {
        return hash_hmac('sha256', $app."\n".$email."\n".$kod, $this->sir);
    }
}
