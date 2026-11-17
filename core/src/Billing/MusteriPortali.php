<?php

declare(strict_types=1);

namespace App\Billing;

use App\Kimlik\Hak;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * M6b (A19): Stripe Customer Portal. Ayar kodda (YAPILANDIRMA): iptal dönem
 * sonunda — panelle aynı kural —, kart güncelleme, fatura geçmişi; plan
 * değiştirme ve müşteri bilgisi düzenleme kapalı. Stripe'ta metadata ile
 * bulunur; app:stripe-portal:kur kurar / günceller.
 */
final readonly class MusteriPortali
{
    public const ETIKET = ['kurulum' => 'myweb2wave-m6b'];

    public function __construct(
        private Connection $db,
        private StripeClient $stripe,
        private LoggerInterface $logger,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private string $huniAdresi,
    ) {
    }

    /**
     * @return array{features: array{subscription_cancel: array{enabled: true, mode: 'at_period_end', proration_behavior: 'none'},
     *               payment_method_update: array{enabled: true}, invoice_history: array{enabled: true},
     *               subscription_update: array{enabled: false}, customer_update: array{enabled: false}},
     *               default_return_url: string, metadata: array{kurulum: string}}
     */
    public function yapilandirma(): array
    {
        return [
            'features' => [
                'subscription_cancel' => ['enabled' => true, 'mode' => 'at_period_end', 'proration_behavior' => 'none'],
                'payment_method_update' => ['enabled' => true],
                'invoice_history' => ['enabled' => true],
                'subscription_update' => ['enabled' => false],
                'customer_update' => ['enabled' => false],
            ],
            'default_return_url' => rtrim($this->huniAdresi, '/').'/',
            'metadata' => self::ETIKET,
        ];
    }

    /**
     * Etiketli ayar varsa günceller, yoksa kurar.
     *
     * @return array{id: string, yeni: bool}
     *
     * @throws ApiErrorException
     */
    public function kur(): array
    {
        $var = $this->bul();
        if (null !== $var) {
            $this->stripe->billingPortal->configurations->update($var, $this->yapilandirma());

            return ['id' => $var, 'yeni' => false];
        }

        return ['id' => $this->stripe->billingPortal->configurations->create($this->yapilandirma())->id, 'yeni' => true];
    }

    /**
     * Kişinin bu uygulamadaki aboneliğinin müşterisi için portal oturumu.
     * Müşteri: erişim veren abonelik önce (Hak ile aynı sıra), yoksa en son.
     *
     * @return array{adres: string}|'unknown_user'|'no_subscription'|'portal_not_configured'|'stripe_error'
     */
    public function oturum(string $app, string $kullanici): array|string
    {
        $email = $this->db->fetchOne(
            'SELECT k.email FROM baglantilar b JOIN kisiler k ON k.id = b.kisi_id WHERE b.app = ? AND b.harici_kullanici = ?',
            [$app, $kullanici],
        );
        if (!\is_string($email)) {
            return 'unknown_user';
        }
        $musteri = $this->musteri($app, $email);

        return null === $musteri ? 'no_subscription' : $this->musteriIcin($musteri, $app);
    }

    /** E-postanın bu uygulamadaki aboneliğinin müşterisi: erişim veren önce (Hak ile aynı sıra), yoksa en son. */
    public function musteri(string $app, string $email): ?string
    {
        $musteri = $this->db->fetchOne(
            "SELECT stripe_customer_id FROM subscriptions WHERE app = ? AND lower(email) = lower(?) AND stripe_customer_id IS NOT NULL
             ORDER BY status IN ('".implode("', '", Hak::ERISIM_VEREN)."') DESC NULLS LAST, updated_at DESC LIMIT 1",
            [$app, $email],
        );

        return \is_string($musteri) ? $musteri : null;
    }

    /** @return array{adres: string}|'portal_not_configured'|'stripe_error' */
    public function musteriIcin(string $musteri, string $app): array|string
    {
        try {
            $ayar = $this->bul();
            if (null === $ayar) {
                return 'portal_not_configured';
            }
            $oturum = $this->stripe->billingPortal->sessions->create([
                'customer' => $musteri,
                'configuration' => $ayar,
                'return_url' => rtrim($this->huniAdresi, '/').'/'.rawurlencode($app).'/',
            ]);
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe portal oturumu açılamadı', ['exception' => $e]);

            return 'stripe_error';
        }

        return ['adres' => $oturum->url];
    }

    /** @throws ApiErrorException */
    private function bul(): ?string
    {
        foreach ($this->stripe->billingPortal->configurations->all(['active' => true, 'limit' => 100])->data as $a) {
            $m = $a->metadata?->toArray() ?? [];
            if (self::ETIKET['kurulum'] === ($m['kurulum'] ?? null)) {
                return $a->id;
            }
        }

        return null;
    }
}
