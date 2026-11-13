<?php

declare(strict_types=1);

namespace App\Controller;

use App\Huni\Deneyler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * M4a (A17): panelden deneyler — taslak yazılır, durum geçişiyle başlar,
 * durur, biter. Yazma kabuğun jetonunu ister (planlar gibi).
 */
#[Route('/panel/api/uygulamalar/{app}/deneyler')]
final class PanelDeneyController extends AbstractController
{
    public function __construct(
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        private readonly Deneyler $deneyler,
    ) {
    }

    #[Route('', name: 'panel_api_deneyler', methods: ['GET'])]
    public function liste(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }

        return self::korumali(new JsonResponse(['deneyler' => $this->deneyler->liste($app)]));
    }

    #[Route('', name: 'panel_api_deney_kaydet', methods: ['POST'])]
    public function kaydet(Request $request, string $app): JsonResponse
    {
        if (null !== ($red = $this->kapi($request, $app))) {
            return $red;
        }
        $g = json_decode($request->getContent(), true);
        $g = \is_array($g) ? $g : [];
        $hatalar = $this->deneyler->kaydet($app, $g['ad'] ?? null, $g['tur'] ?? null, $g['huni'] ?? null, $g['varyantlar'] ?? null);

        return [] === $hatalar
            ? self::korumali(new JsonResponse(['deneyler' => $this->deneyler->liste($app)], 201))
            : self::korumali(new JsonResponse(['hatalar' => $hatalar], 422));
    }

    #[Route('/{ad}/durum', name: 'panel_api_deney_durum', methods: ['PUT'])]
    public function durum(Request $request, string $app, string $ad): JsonResponse
    {
        if (null !== ($red = $this->kapi($request, $app))) {
            return $red;
        }
        $g = json_decode($request->getContent(), true);
        $hatalar = $this->deneyler->durum($app, $ad, \is_array($g) ? ($g['durum'] ?? null) : null);
        if (null === $hatalar) {
            return self::hata("deney yok: {$ad}", 404);
        }

        return [] === $hatalar
            ? self::korumali(new JsonResponse(['deneyler' => $this->deneyler->liste($app)]))
            : self::korumali(new JsonResponse(['hatalar' => $hatalar], 422));
    }

    /** M4b: varyant başına sayılar + kazanma olasılığı (abone olma oranı). */
    #[Route('/{ad}/sonuclar', name: 'panel_api_deney_sonuclar', methods: ['GET'])]
    public function sonuclar(string $app, string $ad): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }
        $sonuc = $this->deneyler->sonuclar($app, $ad);

        return null === $sonuc ? self::hata("deney yok: {$ad}", 404) : self::korumali(new JsonResponse($sonuc));
    }

    private function kapi(Request $request, string $app): ?JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }
        if (!$this->isCsrfTokenValid(PanelUygulamaController::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::hata('Oturum jetonu geçersiz — sayfayı yenileyin.', 403);
        }

        return null;
    }

    private static function hata(string $mesaj, int $durum): JsonResponse
    {
        return self::korumali(new JsonResponse(['hatalar' => [$mesaj]], $durum));
    }

    private static function korumali(JsonResponse $yanit): JsonResponse
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $yanit->headers->set('X-Frame-Options', 'DENY');

        return $yanit;
    }
}
