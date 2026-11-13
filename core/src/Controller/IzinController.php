<?php

declare(strict_types=1);

namespace App\Controller;

use App\Atif\Ziyaretler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Onay şeridinde "Kabul" (H6a, A12): ziyaret açıldıktan sonra verilen izin
 * ve o anda adresteki tıklama kimlikleri. Anahtarsız; ziyaret kimliği
 * tahmin edilemez UUID (ilerleme ucuyla aynı).
 */
#[Route('/huni/ziyaretler/{ziyaret}/izin', name: 'huni_izin', methods: ['POST', 'DELETE'])]
final readonly class IzinController
{
    public function __construct(
        private Ziyaretler $ziyaretler,
    ) {
    }

    public function __invoke(Request $request, string $ziyaret): Response
    {
        if ($request->isMethod('DELETE')) {
            return $this->ziyaretler->izinGeriAl($ziyaret)
                ? new Response('', 204)
                : new JsonResponse(['error' => 'unknown_visit'], 404);
        }

        $govde = $request->toArray();
        $tiklama = [];
        foreach (Ziyaretler::TIKLAMA as $ad) {
            $deger = $govde[$ad] ?? null;
            if (null !== $deger && (!\is_string($deger) || '' === $deger || mb_strlen($deger) > Ziyaretler::AZAMI_UZUNLUK)) {
                return new JsonResponse(['error' => 'invalid_request'], 422);
            }
            $tiklama[$ad] = $deger;
        }
        if ([] !== array_diff(array_keys($govde), Ziyaretler::TIKLAMA)) {
            return new JsonResponse(['error' => 'invalid_request'], 422);
        }

        return $this->ziyaretler->izinVer($ziyaret, $tiklama)
            ? new Response('', 204)
            : new JsonResponse(['error' => 'unknown_visit'], 404);
    }
}
