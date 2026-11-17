<?php

declare(strict_types=1);

namespace App\Controller;

use App\Atif\Ziyaretler;
use App\Kimlik\DogrulamaKodu;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * E1: huninin e-posta adımı. Anahtarsız (ilerleme ucuyla aynı); adres
 * doğrulanmaz — kişiye bağlanmaz (A5 yalnız doğrulanmış adresle bağlar),
 * yalnız ödeme sayfasını önceden doldurur ve izinliyse yeniden ulaşmaya
 * (M8) saklanır.
 */
#[Route('/huni/ziyaretler/{ziyaret}/eposta', name: 'huni_eposta', methods: ['POST'])]
final readonly class EpostaController
{
    public function __construct(
        private Ziyaretler $ziyaretler,
    ) {
    }

    public function __invoke(Request $request, string $ziyaret): Response
    {
        $govde = $request->toArray();
        $eposta = $govde['eposta'] ?? null;
        $izin = $govde['pazarlamaIzni'] ?? false;
        if (!\is_string($eposta) || mb_strlen($eposta) > 254 || !\is_bool($izin)
            || false === filter_var(DogrulamaKodu::normal($eposta), \FILTER_VALIDATE_EMAIL)
            || [] !== array_diff(array_keys($govde), ['eposta', 'pazarlamaIzni'])) {
            return new JsonResponse(['error' => 'invalid_request'], 422);
        }

        return $this->ziyaretler->epostaKaydet($ziyaret, DogrulamaKodu::normal($eposta), $izin)
            ? new Response('', 204)
            : new JsonResponse(['error' => 'unknown_visit'], 404);
    }
}
