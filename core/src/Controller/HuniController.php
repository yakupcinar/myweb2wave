<?php

declare(strict_types=1);

namespace App\Controller;

use App\Huni\Huniler;
use App\Huni\Tanim;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Huni sayfası tanımı buradan okur: hangi huni olacağını kurallar seçer
 * (A8), yayınlanmış en son sürüm döner (A7); ya da imzalı önizleme
 * anahtarıyla bir huninin taslağı. Anahtarsız ve herkese açık.
 */
final readonly class HuniController
{
    /** Yayın en geç bu kadar sonra görünür (tarayıcı/CDN önbelleği). */
    private const ONBELLEK_SN = 60;

    public function __construct(
        private Huniler $huniler,
    ) {
    }

    /**
     * Önbellek anahtarı adrestir: huni sayfası yalnız seçim parametrelerini
     * gönderir (fbclid her tıklamada farklı — gönderilseydi önbellek tutmazdı).
     */
    #[Route('/huni/{app}', name: 'huni_yayinda', methods: ['GET'], requirements: ['app' => '[a-z0-9_-]+'])]
    public function yayinda(Request $request, string $app): JsonResponse
    {
        $parametreler = [];
        foreach ([...Huniler::SECIM_PARAMETRELERI, 'huni'] as $ad) {
            $deger = $request->query->get($ad);
            if (\is_string($deger) && '' !== $deger && mb_strlen($deger) <= 200) {
                $parametreler[$ad] = $deger;
            }
        }
        $huni = $this->huniler->sec($app, $parametreler);
        $yayin = null === $huni ? null : $this->huniler->yayinda($app, $huni);
        if (null === $huni || null === $yayin) {
            return new JsonResponse(['error' => 'unknown_app'], 404);
        }
        $yanit = new JsonResponse(['huni' => $huni] + $yayin);
        $yanit->setPublic();
        $yanit->setMaxAge(self::ONBELLEK_SN);

        return $yanit;
    }

    #[Route('/huni/{app}/taslak', name: 'huni_taslak', methods: ['GET'], requirements: ['app' => '[a-z0-9_-]+'])]
    public function taslak(Request $request, string $app): JsonResponse
    {
        $ad = $request->query->getString('huni');
        $anahtar = $request->query->getString('anahtar');
        $taslak = 1 === preg_match(Tanim::KIMLIK, $ad) ? $this->huniler->taslak($app, $ad) : null;
        if (null === $taslak || !$this->huniler->anahtarGecerli($app, $ad, $anahtar, time())) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }
        $yanit = new JsonResponse(['huni' => $ad, 'surum' => null, 'tanim' => $taslak]);
        $yanit->headers->set('Cache-Control', 'no-store');

        return $yanit;
    }
}
