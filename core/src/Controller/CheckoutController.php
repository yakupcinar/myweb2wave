<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Huninin paywall'u buradan Stripe Checkout oturumu açar. İstemci yalnız
 * uygulama ve plan ADINI gönderir; fiyat kimliği ve dönüş adresleri sunucu
 * yapılandırmasından gelir — istemciden dönüş adresi almak açık yönlendirme.
 */
#[Route('/checkout/sessions', name: 'checkout_session_create', methods: ['POST'])]
final readonly class CheckoutController
{
    public function __construct(
        private StripeClient $stripe,
        private LoggerInterface $logger,
        /** @var array<string, array<string, string>> uygulama → plan → Stripe fiyatı */
        #[Autowire('%uygulamalar%')]
        private array $uygulamalar,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private string $funnelBaseUrl,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        // Bozuk JSON: toArray() Symfony'nin JsonException'ını atar, çekirdek
        // onu 400'e çevirir (\JsonException DEĞİL — PHPStan ölü catch buldu).
        $govde = $request->toArray();
        $app = $govde['app'] ?? null;
        $plan = $govde['plan'] ?? null;

        if (!\is_string($app) || !isset($this->uygulamalar[$app])) {
            return new JsonResponse(['error' => 'unknown_app'], 422);
        }
        $prices = $this->uygulamalar[$app];
        if (!\is_string($plan) || !isset($prices[$plan])) {
            return new JsonResponse(['error' => 'unknown_plan'], 422);
        }

        try {
            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'subscription',
                'line_items' => [['price' => $prices[$plan], 'quantity' => 1]],
                'success_url' => $this->funnelBaseUrl.'/'.$app.'/basarili?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->funnelBaseUrl.'/'.$app.'/iptal',
                // Webhook aboneliğin uygulamasını buradan okur (A4); oturuma
                // ve aboneliğe ayrı ayrı yazılmalı — olaylar ikisinden de gelir.
                'metadata' => ['app' => $app],
                'subscription_data' => ['metadata' => ['app' => $app]],
            ]);
        } catch (ApiErrorException $e) {
            // Ç16: ağ, Stripe 5xx ya da reddedilen istek — huni mesaj gösterir.
            // Ayrıntı günlüğe; istemciye iç hata metni gitmez.
            $this->logger->error('Stripe Checkout oturumu açılamadı', ['exception' => $e]);

            return new JsonResponse(['error' => 'payment_provider_unavailable'], 502);
        }

        return new JsonResponse(['url' => $session->url], 201);
    }
}
