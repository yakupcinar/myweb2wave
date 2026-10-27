<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\UrunBaglami;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Panel: uygulamalar ve ürün bağlamı (A4) — sözleşme /panel/api/uygulamalar*.
 * Yazma CSRF ister (panel uygulamasının jetonu, panel-api; kabuktan gelir).
 */
#[Route('/panel/api/uygulamalar')]
final class PanelUygulamaController extends AbstractController
{
    public const JETON = 'panel-api';

    public function __construct(
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        private readonly UrunBaglami $baglam,
    ) {
    }

    #[Route('', name: 'panel_api_uygulamalar', methods: ['GET'])]
    public function liste(): JsonResponse
    {
        return self::korumali(new JsonResponse(['uygulamalar' => array_map(strval(...), array_keys($this->uygulamalar))]));
    }

    #[Route('/{app}/baglam', name: 'panel_api_baglam', methods: ['GET'])]
    public function oku(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }

        return self::korumali(new JsonResponse($this->baglam->oku($app)));
    }

    #[Route('/{app}/baglam', name: 'panel_api_baglam_kaydet', methods: ['PUT'])]
    public function kaydet(Request $request, string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        $hatalar = $this->baglam->kaydet($app, json_decode($request->getContent(), true));

        return self::korumali([] === $hatalar
            ? new JsonResponse($this->baglam->oku($app))
            : new JsonResponse(['hatalar' => $hatalar], 422));
    }

    private static function korumali(JsonResponse $yanit): JsonResponse
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $yanit->headers->set('X-Frame-Options', 'DENY');

        return $yanit;
    }
}
