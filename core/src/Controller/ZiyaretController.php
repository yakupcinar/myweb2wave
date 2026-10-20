<?php

declare(strict_types=1);

namespace App\Controller;

use App\Atif\Ziyaretler;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Huni sayfası açılınca tarayıcı buraya hangi reklamdan geldiğini yazar
 * (A6). Anahtarsız ve herkese açık — huni statik; CORS yalnız huninin kökeni.
 */
#[Route('/huni/ziyaretler', name: 'huni_ziyaret', methods: ['POST'])]
final readonly class ZiyaretController
{
    public function __construct(
        private Ziyaretler $ziyaretler,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private array $uygulamalar,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $govde = $request->toArray();
        $app = $govde['app'] ?? null;
        if (!\is_string($app) || !isset($this->uygulamalar[$app])) {
            return new JsonResponse(['error' => 'unknown_app'], 422);
        }

        $alanlar = [];
        foreach (Ziyaretler::ALANLAR as $ad) {
            $deger = $govde[$ad] ?? null;
            if (null !== $deger && (!\is_string($deger) || mb_strlen($deger) > Ziyaretler::AZAMI_UZUNLUK)) {
                return new JsonResponse(['error' => 'invalid_request'], 422);
            }
            $alanlar[$ad] = '' === $deger ? null : $deger;
        }

        return new JsonResponse(['ziyaret' => $this->ziyaretler->kaydet($app, $alanlar)], 201);
    }
}
