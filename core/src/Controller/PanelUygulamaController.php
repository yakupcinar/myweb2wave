<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\UrunBaglami;
use App\Mcp\ApiAnahtarlari;
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
        private readonly ApiAnahtarlari $anahtarlar,
        /** A5a: MCP istemcisine verilecek adres (herkese açık adres gelince ortamdan). */
        #[Autowire('%env(MCP_GENEL_ADRES)%')]
        private readonly string $mcpAdresi,
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

    /** A5a: kişisel API anahtarları (MCP) — liste; anahtarın kendisi yok, önek var. */
    #[Route('/{app}/anahtarlar', name: 'panel_api_anahtarlar', methods: ['GET'])]
    public function anahtarlar(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }

        return self::korumali(new JsonResponse(['mcpAdresi' => $this->mcpAdresi, 'anahtarlar' => $this->anahtarlar->liste($app)]));
    }

    /** Yeni anahtar: kendisi YALNIZ bu yanıtta (bir kez); sonra yalnız önek görünür. */
    #[Route('/{app}/anahtarlar', name: 'panel_api_anahtar_olustur', methods: ['POST'])]
    public function anahtarOlustur(Request $request, string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        $govde = json_decode($request->getContent(), true);
        $ad = \is_array($govde) && \is_string($govde['ad'] ?? null) ? trim($govde['ad']) : '';
        if ('' === $ad || mb_strlen($ad) > 100) {
            return self::korumali(new JsonResponse(['hatalar' => ['Ad 1–100 karakter olmalı (ör. "Claude Code — dizüstü").']], 422));
        }
        $yeni = $this->anahtarlar->olustur($app, $ad);

        return self::korumali(new JsonResponse(['id' => $yeni['id'], 'ad' => $ad, 'anahtar' => $yeni['anahtar']], 201));
    }

    /** Geri alınamaz; çağrı kaydı kalır. */
    #[Route('/{app}/anahtarlar/{id}', name: 'panel_api_anahtar_iptal', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function anahtarIptal(Request $request, string $app, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }

        return self::korumali($this->anahtarlar->iptal($app, $id)
            ? new JsonResponse(null, 204)
            : new JsonResponse(['hatalar' => ['Anahtar yok ya da zaten iptal edilmiş.']], 404));
    }

    private static function korumali(JsonResponse $yanit): JsonResponse
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $yanit->headers->set('X-Frame-Options', 'DENY');

        return $yanit;
    }
}
