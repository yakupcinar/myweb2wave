<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\SubscriptionSync;
use App\Kimlik\GirisBaglantisi;
use App\RevenueCat\Aktarim;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * İmzası doğrulanmayan hiçbir gövde işlenmez: herkese açık uç, abonelik
 * durumunu yazıyor.
 */
#[Route('/webhooks/stripe', name: 'stripe_webhook', methods: ['POST'])]
final readonly class StripeWebhookController
{
    public function __construct(
        private SubscriptionSync $sync,
        private Aktarim $revenuecat,
        private GirisBaglantisi $giris,
        #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')]
        private string $secret,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->headers->get('Stripe-Signature'),
                $this->secret,
            );
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            return new Response('', 400);
        }

        $this->sync->apply($event);
        // A9: işlem kapandıktan sonra; başarısızlık atmaz, satır bekler.
        $this->revenuecat->gonder();
        // Ç46: ödeme sonrası giriş bağlantısı e-postası; atmaz, gitmeyen bekler.
        $this->giris->epostalariGonder();

        return new Response('', 200);
    }
}
