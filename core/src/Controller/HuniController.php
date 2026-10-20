<?php

declare(strict_types=1);

namespace App\Controller;

use App\Huni\Huniler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Huni sayfası tanımı buradan okur (A7): yayınlanmış en son sürüm ya da,
 * imzalı önizleme anahtarıyla, taslak. Anahtarsız ve herkese açık.
 */
final readonly class HuniController
{
    /** Yayın en geç bu kadar sonra görünür (tarayıcı/CDN önbelleği). */
    private const ONBELLEK_SN = 60;

    public function __construct(
        private Huniler $huniler,
    ) {
    }

    #[Route('/huni/{app}', name: 'huni_yayinda', methods: ['GET'], requirements: ['app' => '[a-z0-9_-]+'])]
    public function yayinda(string $app): JsonResponse
    {
        $yayin = $this->huniler->yayinda($app);
        if (null === $yayin) {
            return new JsonResponse(['error' => 'unknown_app'], 404);
        }
        $yanit = new JsonResponse($yayin);
        $yanit->setPublic();
        $yanit->setMaxAge(self::ONBELLEK_SN);

        return $yanit;
    }

    #[Route('/huni/{app}/taslak', name: 'huni_taslak', methods: ['GET'], requirements: ['app' => '[a-z0-9_-]+'])]
    public function taslak(Request $request, string $app): JsonResponse
    {
        $anahtar = $request->query->getString('anahtar');
        $taslak = $this->huniler->taslak($app);
        if (null === $taslak || !$this->huniler->anahtarGecerli($app, $anahtar, time())) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }
        $yanit = new JsonResponse(['surum' => null, 'tanim' => $taslak]);
        $yanit->headers->set('Cache-Control', 'no-store');

        return $yanit;
    }
}
