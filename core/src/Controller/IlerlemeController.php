<?php

declare(strict_types=1);

namespace App\Controller;

use App\Atif\Ziyaretler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Huni anketi (A6): tarayıcı her adıma geçişte ve her cevapta buraya
 * yazar. Anahtarsız (huni statik); ziyaret kimliği tahmin edilemez UUID.
 */
#[Route('/huni/ziyaretler/{ziyaret}/ilerleme', name: 'huni_ilerleme', methods: ['POST'])]
final readonly class IlerlemeController
{
    public function __construct(
        private Ziyaretler $ziyaretler,
    ) {
    }

    public function __invoke(Request $request, string $ziyaret): Response
    {
        $govde = $request->toArray();
        $sira = $govde['sira'] ?? null;
        $adim = $govde['adim'] ?? null;
        $cevap = $govde['cevap'] ?? null;
        if (!\is_int($sira) || $sira < 0 || $sira > Ziyaretler::AZAMI_SIRA
            || !\is_string($adim) || 1 !== preg_match(Ziyaretler::KIMLIK, $adim)
            || (null !== $cevap && (!\is_string($cevap) || 1 !== preg_match(Ziyaretler::KIMLIK, $cevap)))) {
            return new JsonResponse(['error' => 'invalid_request'], 422);
        }

        return $this->ziyaretler->ilerle($ziyaret, $sira, $adim, $cevap)
            ? new Response('', 204)
            : new JsonResponse(['error' => 'unknown_visit'], 404);
    }
}
