<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\EkUrunler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * M3c: ödeme sonrası ekranında tek tık ek ürün. Anahtarsız (başarı sayfası
 * statik); yetki Stripe oturumunun kendisi — ödenmiş, bu uygulamanın, yakın
 * zamanlı (EkUrunler::sat). 3D Secure'dan dönen istemci aynı isteği yineler.
 */
#[Route('/huni/ek-satis', name: 'huni_ek_satis', methods: ['POST'])]
final readonly class EkSatisController
{
    public function __construct(
        private EkUrunler $ekUrunler,
        /** @var array<string, array<string, string>> */
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%uygulamalar%')]
        private array $uygulamalar,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $g = $request->toArray();
        $app = $g['app'] ?? null;
        $oturum = $g['oturum'] ?? null;
        $urun = $g['urun'] ?? null;
        if (!\is_string($app) || !isset($this->uygulamalar[$app]) || !\is_string($oturum) || 1 !== preg_match('/^cs_[A-Za-z0-9_]{1,250}$/', $oturum)
            || !\is_string($urun) || 1 !== preg_match(EkUrunler::ANAHTAR, $urun) || [] !== array_diff(array_keys($g), ['app', 'oturum', 'urun'])) {
            return new JsonResponse(['error' => 'invalid_request'], 422);
        }
        $s = $this->ekUrunler->sat($app, $oturum, $urun);
        if (isset($s['hata'])) {
            return new JsonResponse(['error' => $s['hata']], 'payment_provider_unavailable' === $s['hata'] ? 502 : 422);
        }

        return new JsonResponse($s);
    }
}
