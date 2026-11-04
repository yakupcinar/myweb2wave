<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\Fiyatlar;
use App\Billing\Planlar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Panel: fiyat kataloğu (M1a) — sözleşme /panel/api/uygulamalar/{app}/planlar*.
 * Listede tutar Stripe'tan (Fiyatlar, L10); okunamazsa `fiyat: null`.
 * Yazma CSRF ister (panel-api, PanelUygulamaController ile aynı jeton).
 */
#[Route('/panel/api/uygulamalar/{app}/planlar')]
final class PanelPlanController extends AbstractController
{
    public function __construct(
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        private readonly Planlar $planlar,
        private readonly Fiyatlar $fiyatlar,
    ) {
    }

    #[Route('', name: 'panel_api_planlar', methods: ['GET'])]
    public function liste(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }

        return self::korumali(new JsonResponse(['planlar' => $this->satirlar($app)]));
    }

    #[Route('', name: 'panel_api_plan_olustur', methods: ['POST'])]
    public function olustur(Request $request, string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }
        if (!$this->isCsrfTokenValid(PanelUygulamaController::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::hata('Oturum jetonu geçersiz — sayfayı yenileyin.', 403);
        }
        $g = json_decode($request->getContent(), true);
        $g = \is_array($g) ? $g : [];
        $hatalar = $this->planlar->olustur($app, $g['anahtar'] ?? null, $g['ad'] ?? null, $g['tutar'] ?? null, $g['paraBirimi'] ?? null, $g['aralik'] ?? null);

        return [] === $hatalar
            ? self::korumali(new JsonResponse(['planlar' => $this->satirlar($app)], 201))
            : self::korumali(new JsonResponse(['hatalar' => $hatalar], 422));
    }

    #[Route('/{anahtar}/fiyat', name: 'panel_api_plan_fiyat', methods: ['PUT'])]
    public function fiyat(Request $request, string $app, string $anahtar): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }
        if (!$this->isCsrfTokenValid(PanelUygulamaController::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::hata('Oturum jetonu geçersiz — sayfayı yenileyin.', 403);
        }
        $g = json_decode($request->getContent(), true);
        $hatalar = $this->planlar->fiyatDegistir($app, $anahtar, \is_array($g) ? ($g['tutar'] ?? null) : null);
        if (null === $hatalar) {
            return self::hata("'{$anahtar}' planı yok", 404);
        }

        return [] === $hatalar
            ? self::korumali(new JsonResponse(['planlar' => $this->satirlar($app)]))
            : self::korumali(new JsonResponse(['hatalar' => $hatalar], 422));
    }

    /** M1c: planın ek para birimleri (para birimi → tutar); boş eşleme kaldırır. */
    #[Route('/{anahtar}/para-birimleri', name: 'panel_api_plan_para_birimleri', methods: ['PUT'])]
    public function paraBirimleri(Request $request, string $app, string $anahtar): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }
        if (!$this->isCsrfTokenValid(PanelUygulamaController::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::hata('Oturum jetonu geçersiz — sayfayı yenileyin.', 403);
        }
        $g = json_decode($request->getContent(), true);
        $hatalar = $this->planlar->paraBirimleri($app, $anahtar, \is_array($g) ? ($g['tutarlar'] ?? null) : null);
        if (null === $hatalar) {
            return self::hata("'{$anahtar}' planı yok", 404);
        }

        return [] === $hatalar
            ? self::korumali(new JsonResponse(['planlar' => $this->satirlar($app)]))
            : self::korumali(new JsonResponse(['hatalar' => $hatalar], 422));
    }

    /** M1b: planın teklifi; gövdede olmayan alan null. */
    #[Route('/{anahtar}/teklif', name: 'panel_api_plan_teklif', methods: ['PUT'])]
    public function teklif(Request $request, string $app, string $anahtar): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }
        if (!$this->isCsrfTokenValid(PanelUygulamaController::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::hata('Oturum jetonu geçersiz — sayfayı yenileyin.', 403);
        }
        $g = json_decode($request->getContent(), true);
        $g = \is_array($g) ? $g : [];
        $hatalar = $this->planlar->teklifAyarla($app, $anahtar, $g['tur'] ?? null, $g['gun'] ?? null, $g['tutar'] ?? null, $g['yuzde'] ?? null, $g['bitis'] ?? null);
        if (null === $hatalar) {
            return self::hata("'{$anahtar}' planı yok", 404);
        }

        return [] === $hatalar
            ? self::korumali(new JsonResponse(['planlar' => $this->satirlar($app)]))
            : self::korumali(new JsonResponse(['hatalar' => $hatalar], 422));
    }

    /** @return list<array{anahtar: string, ad: string, stripeFiyat: string, fiyat: array<string, mixed>|null}> */
    private function satirlar(string $app): array
    {
        $fiyatlar = $this->fiyatlar->tum($app);

        return array_map(static fn (array $p): array => [...$p, 'fiyat' => $fiyatlar[$p['anahtar']] ?? null], $this->planlar->liste($app));
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
