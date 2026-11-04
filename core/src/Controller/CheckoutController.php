<?php

declare(strict_types=1);

namespace App\Controller;

use App\Atif\Ziyaretler;
use App\Billing\Fiyatlar;
use App\Billing\ParaBirimi;
use App\Billing\Planlar;
use App\Billing\UlkeBulucu;
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
        private Fiyatlar $fiyatlar,
        private UlkeBulucu $ulkeler,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private array $uygulamalar,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private string $funnelBaseUrl,
        // M2d: boşsa gömülü ödeme kapalı — istek ne derse desin Stripe sayfasına yönlendirme.
        #[Autowire('%env(STRIPE_PUBLISHABLE_KEY)%')]
        private string $yayimlanabilirAnahtar,
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

        // M1c: para birimi paywall'daki seçimin aynısı (ülke → planın ek para
        // birimi, yoksa ana). Zorlanır: Stripe kendi konum tahminine göre başka
        // para birimi açmasın; fiyat okunamadıysa Stripe'ın ana para birimi.
        // M2d ölçümü: ülke bilinmese de ZORLANIR — zorlanmayan çok para birimli
        // fiyatı Stripe kendi konum tahminiyle yerelleştiriyor (test e-postası
        // +location_TR ile TRY açtı, paywall EUR diyordu). M1c'deki "okuma yok,
        // Stripe ana birimi açar" varsayımı yanlıştı.
        $ulkeninki = ParaBirimi::ulkeninki($this->ulkeler->ulke($request->getClientIp(), $govde['ulke'] ?? null));
        $paraBirimi = $this->fiyatlar->uygulama($app, $ulkeninki)[$plan]['paraBirimi'] ?? null;

        $onay = true === ($govde['sartOnayi'] ?? null) ? ['sart_onayi' => '1'] : [];
        // M2d: ödeme paywall sayfasında, gömülü (A14); Stripe.js yüklenemezse istemci gomulu'suz ister.
        $gomulu = true === ($govde['gomulu'] ?? null) && '' !== $this->yayimlanabilirAnahtar;
        $donus = $this->funnelBaseUrl.'/'.$app.'/basarili?session_id={CHECKOUT_SESSION_ID}';

        // E1: huninin e-posta adımında yazılan adres ödeme sayfasında hazır.
        $ziyaret = $govde['ziyaret'] ?? null;
        $eposta = \is_string($ziyaret) ? $this->ziyaretler->eposta($ziyaret, $app) : null;

        try {
            $session = $this->stripe->checkout->sessions->create([
                'mode' => 'subscription',
                ...(null === $paraBirimi ? [] : ['currency' => strtolower($paraBirimi)]),
                // A14: Adaptive Pricing kapalı — otomatik çeviri (€18,47 gibi, %4 ek) paywall'daki tutarla ayrışırdı (Ç19).
                'adaptive_pricing' => ['enabled' => false],
                ...(null === $eposta ? [] : ['customer_email' => $eposta]),
                'line_items' => [['price' => $prices[$plan], 'quantity' => 1], ...($teklifAlanlari['line_items'] ?? [])],
                ...(isset($teklifAlanlari['discounts']) ? ['discounts' => $teklifAlanlari['discounts']] : []),
                // Deneme kartlıdır: kart ilk günden alınır, süre bitince ücret kendiliğinden çekilir.
                'payment_method_collection' => 'always',
                // Gömülüde iptal sayfası yok: ziyaretçi paneli kapatıp paywall'da kalır.
                ...($gomulu
                    ? ['ui_mode' => 'embedded_page', 'return_url' => $donus]
                    : ['success_url' => $donus, 'cancel_url' => $this->funnelBaseUrl.'/'.$app.'/iptal']),
                // Webhook aboneliğin uygulamasını buradan okur (A4); oturuma
                // ve aboneliğe ayrı ayrı yazılmalı — olaylar ikisinden de gelir.
                // M2b: paywall şart onay kutusu işaretlendiyse kanıtı Stripe'ta (oturum + abonelik).
                'metadata' => ['app' => $app] + $onay,
                'subscription_data' => ['metadata' => ['app' => $app] + $onay] + ($teklifAlanlari['subscription_data'] ?? []),
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

        if ($gomulu) {
            if (!\is_string($session->client_secret) || '' === $session->client_secret) {
                $this->logger->error('Gömülü Stripe oturumunda client_secret yok', ['oturum' => $session->id]);

                return new JsonResponse(['error' => 'payment_provider_unavailable'], 502);
            }

            return new JsonResponse(['clientSecret' => $session->client_secret, 'anahtar' => $this->yayimlanabilirAnahtar], 201);
        }

        return new JsonResponse(['url' => $session->url], 201);
    }
}
