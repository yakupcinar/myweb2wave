<?php

declare(strict_types=1);

namespace App\Controller;

use App\Kimlik\GirisBaglantisi;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Başarı sayfası ödeme oturumu kimliğiyle (cs_…) tek kullanımlık giriş
 * bağlantısı ister (Ç46). Anahtarsız ve herkese açık — huni statik; cs_
 * kimliği tek başına yetmez, pencere ve sayı sınırı GirisBaglantisi'nde.
 */
#[Route('/huni/giris-baglantisi', name: 'huni_giris_baglantisi', methods: ['POST'])]
final readonly class GirisBaglantisiController
{
    public function __construct(
        private GirisBaglantisi $giris,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private array $uygulamalar,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $govde = $request->toArray();
        $app = $govde['app'] ?? null;
        $oturum = $govde['oturum'] ?? null;
        if (!\is_string($app) || !isset($this->uygulamalar[$app])) {
            return new JsonResponse(['error' => 'unknown_app'], 422);
        }
        if (!\is_string($oturum) || 1 !== preg_match('/^cs_[A-Za-z0-9_]{1,250}$/', $oturum)) {
            return new JsonResponse(['error' => 'invalid_request'], 422);
        }

        $sonuc = $this->giris->sayfaIcin($app, $oturum);

        return match ($sonuc) {
            // Webhook henüz gelmedi (ya da böyle bir ödeme yok); sayfa yeniden dener.
            'hazir_degil' => new JsonResponse(['error' => 'not_ready'], 404),
            'pencere_kapandi' => new JsonResponse(['error' => 'link_window_closed'], 410),
            'cok_istek' => new JsonResponse(['error' => 'too_many_requests'], 429),
            default => new JsonResponse($sonuc, 201),
        };
    }
}
