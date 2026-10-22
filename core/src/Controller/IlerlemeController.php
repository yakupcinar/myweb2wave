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
        $cevapVar = \array_key_exists('cevap', $govde);
        $cevap = $govde['cevap'] ?? null;
        $cevaplarVar = \array_key_exists('cevaplar', $govde);
        $cevaplar = $cevaplarVar ? self::cevaplariDogrula($govde['cevaplar']) : null;
        if (!\is_int($sira) || $sira < 0 || $sira > Ziyaretler::AZAMI_SIRA
            || !\is_string($adim) || 1 !== preg_match(Ziyaretler::KIMLIK, $adim)
            || ($cevapVar && (!\is_string($cevap) || 1 !== preg_match(Ziyaretler::KIMLIK, $cevap)))
            || ($cevaplarVar && null === $cevaplar)
            || ($cevapVar && $cevaplarVar)) {
            return new JsonResponse(['error' => 'invalid_request'], 422);
        }

        $secenekler = \is_string($cevap) ? [$cevap] : $cevaplar;

        return $this->ziyaretler->ilerle($ziyaret, $sira, $adim, $secenekler)
            ? new Response('', 204)
            : new JsonResponse(['error' => 'unknown_visit'], 404);
    }

    /** @return list<string>|null */
    private static function cevaplariDogrula(mixed $cevaplar): ?array
    {
        if (!\is_array($cevaplar) || !array_is_list($cevaplar) || \count($cevaplar) < 1 || \count($cevaplar) > 8) {
            return null;
        }

        $dogrulanan = [];
        foreach ($cevaplar as $cevap) {
            if (!\is_string($cevap) || 1 !== preg_match(Ziyaretler::KIMLIK, $cevap)) {
                return null;
            }
            $dogrulanan[] = $cevap;
        }

        return \count(array_unique($dogrulanan)) === \count($dogrulanan) ? $dogrulanan : null;
    }
}
