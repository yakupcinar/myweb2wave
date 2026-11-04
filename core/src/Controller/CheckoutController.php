<?php

declare(strict_types=1);

namespace App\Controller;

use App\Atif\Ziyaretler;
use App\Billing\Planlar;
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
        private Ziyaretler $ziyaretler,
        private Planlar $planlar,
        /** @var array<string, array<string, string>> */
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
        $prices = $this->planlar->fiyatKimlikleri($app);
        if (!\is_string($plan) || !isset($prices[$plan])) {
            return new JsonResponse(['error' => 'unknown_plan'], 422);
        }

        // M1b: teklif istemciden DEĞİL katalogdan — istemci yalnız planı seçer.
        $teklif = $this->planlar->teklifler($app)[$plan] ?? null;
        // Türün alanları DB kısıtıyla zorunlu (planlar_teklif_tutarli): giris/indirimde Stripe kimliği, deneme/giriste gün.
        $stripeTeklif = (string) ($teklif['stripe'] ?? '');
        $gun = (int) ($teklif['gun'] ?? 0);
        $teklifAlanlari = match ($teklif['tur'] ?? null) {
            'deneme' => ['subscription_data' => ['trial_period_days' => $gun]],
            // Ücretli giriş: deneme günleri + ilk faturada tek seferlik ücret (kullanıcı kararı 2026-11-04).
            'giris' => ['subscription_data' => ['trial_period_days' => $gun], 'line_items' => [['price' => $stripeTeklif, 'quantity' => 1]]],
            'indirim' => ['discounts' => [['coupon' => $stripeTeklif]]],
            default => ['subscription_data' => [], 'line_items' => []],
        };

        // E1: huninin e-posta adımında yazılan adres ödeme sayfasında hazır.
        $ziyaret = $govde['ziyaret'] ?? null;
        $eposta = \is_string($ziyaret) ? $this->ziyaretler->eposta($ziyaret, $app) : null;

        try {
            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'subscription',
                ...(null === $eposta ? [] : ['customer_email' => $eposta]),
                'line_items' => [['price' => $prices[$plan], 'quantity' => 1], ...($teklifAlanlari['line_items'] ?? [])],
                ...(isset($teklifAlanlari['discounts']) ? ['discounts' => $teklifAlanlari['discounts']] : []),
                // Deneme kartlıdır: kart ilk günden alınır, süre bitince ücret kendiliğinden çekilir.
                'payment_method_collection' => 'always',
                'success_url' => $this->funnelBaseUrl.'/'.$app.'/basarili?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->funnelBaseUrl.'/'.$app.'/iptal',
                // Webhook aboneliğin uygulamasını buradan okur (A4); oturuma
                // ve aboneliğe ayrı ayrı yazılmalı — olaylar ikisinden de gelir.
                'metadata' => ['app' => $app],
                'subscription_data' => ['metadata' => ['app' => $app]] + ($teklifAlanlari['subscription_data'] ?? []),
            ]);
        } catch (ApiErrorException $e) {
            // Ç16: ağ, Stripe 5xx ya da reddedilen istek — huni mesaj gösterir.
            // Ayrıntı günlüğe; istemciye iç hata metni gitmez.
            $this->logger->error('Stripe Checkout oturumu açılamadı', ['exception' => $e]);

            return new JsonResponse(['error' => 'payment_provider_unavailable'], 502);
        }

        // Atıf (A6): huni ziyaret kimliği gönderdiyse oturum ona bağlanır;
        // yoksa ya da tanınmazsa ödeme yine açılır.
        if (\is_string($ziyaret)) {
            $this->ziyaretler->oturumBagla($ziyaret, $app, $session->id, $request->getClientIp(), $request->headers->get('User-Agent'));
        }

        return new JsonResponse(['url' => $session->url], 201);
    }
}
