<?php

declare(strict_types=1);

namespace App\Uygulama;

use App\Yasal\YasalMetinler;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * P7c: panelin "Canlıya geçiş" listesi — her adım çalışan sistemden ölçülür,
 * elle işaretlenmez. Anahtarların kendisi hiçbir yanıta girmez; yalnız dolu
 * mu, hangi kipte.
 */
final readonly class CanliyaGecis
{
    /**
     * Zamanlı komut => en uzun beklenen aralık (sn). Önerilen sıklığın
     * (uygulama-rehberi §4.2) birkaç katı: tek kaçan çalışma eksik saymasın.
     */
    public const GOREVLER = [
        'app:webhook:gonder' => 600,
        'app:posta:dizi' => 3600,
        'app:reklam-donusumleri:gonder' => 3600,
        'app:revenuecat:gonder' => 3600,
        'app:reklam-harcamalari:cek' => 2 * 86400,
        'app:huni-metin-cevaplari-sil' => 2 * 86400,
    ];
    /** Paywall'daki bağlantılar bunlar yayındayken çıkar (HuniController). */
    public const PAYWALL_BELGELERI = ['sartlar', 'gizlilik'];

    public function __construct(
        private Connection $db,
        private YasalMetinler $yasal,
        #[Autowire('%env(STRIPE_SECRET_KEY)%')]
        private string $stripeAnahtari,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private string $stripeWebhookGizli,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $postaDsn,
        #[Autowire('%env(META_AD_ACCOUNT_ID)%')]
        private string $metaHesap,
        #[Autowire('%env(META_INSIGHTS_ACCESS_TOKEN)%')]
        private string $metaAnahtar,
    ) {
    }

    /**
     * @return array{
     *     odeme: array{tamam: bool, kip: 'test'|'canli'|null},
     *     stripeBildirimleri: array{tamam: bool, son: string|null},
     *     eposta: array{tamam: bool},
     *     yasal: array{tamam: bool, eksikler: list<string>},
     *     metaReklam: array{tamam: bool},
     *     gorevler: array{tamam: bool, eksikler: list<string>}
     * }
     */
    public function durum(string $app, \DateTimeImmutable $simdi): array
    {
        $kip = '' === $this->stripeAnahtari ? null
            : (str_starts_with($this->stripeAnahtari, 'sk_live_') || str_starts_with($this->stripeAnahtari, 'rk_live_') ? 'canli' : 'test');
        // Gizli anahtar yoksa gelen imzalar doğrulanamaz; varsa en az bir olay işlenmiş olmalı (adres Stripe'a girilmiş mi).
        $son = '' === $this->stripeWebhookGizli ? null : $this->db->fetchOne('SELECT max(processed_at) FROM processed_webhook_events');
        $yayinda = $this->yasal->yayindakiler($app, 'tr');
        $yasalEksik = array_values(array_diff(self::PAYWALL_BELGELERI, $yayinda));
        $calisanlar = $this->db->fetchAllKeyValue('SELECT komut, son_calisma FROM zamanli_gorevler');
        $gorevEksik = [];
        foreach (self::GOREVLER as $komut => $aralik) {
            $zaman = $calisanlar[$komut] ?? null;
            if (!\is_string($zaman) || $simdi->getTimestamp() - (new \DateTimeImmutable($zaman))->getTimestamp() > $aralik) {
                $gorevEksik[] = $komut;
            }
        }

        return [
            'odeme' => ['tamam' => null !== $kip, 'kip' => $kip],
            'stripeBildirimleri' => ['tamam' => \is_string($son), 'son' => \is_string($son) ? (new \DateTimeImmutable($son))->format(\DATE_ATOM) : null],
            'eposta' => ['tamam' => str_starts_with($this->postaDsn, 'postmark+')],
            'yasal' => ['tamam' => [] === $yasalEksik, 'eksikler' => $yasalEksik],
            'metaReklam' => ['tamam' => '' !== $this->metaHesap && '' !== $this->metaAnahtar],
            'gorevler' => ['tamam' => [] === $gorevEksik, 'eksikler' => $gorevEksik],
        ];
    }
}
