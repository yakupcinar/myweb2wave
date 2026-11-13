<?php

declare(strict_types=1);

namespace App\Controller;

use App\Olay\Olaylar;
use App\Olay\WebhookHedefleri;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * M7b (A20): panelden giden webhook hedefleri — liste, ekle (sır bir kez),
 * etkin / pasif, sil, teslim günlüğü, yeniden gönder. Yazma kabuğun
 * jetonunu ister (deneyler gibi).
 */
#[Route('/panel/api/uygulamalar/{app}/webhooklar')]
final class PanelWebhookController extends AbstractController
{
    private const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    public function __construct(
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        private readonly WebhookHedefleri $hedefler,
    ) {
    }

    #[Route('', name: 'panel_api_webhooklar', methods: ['GET'])]
    public function liste(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }

        return self::korumali(new JsonResponse(['hedefler' => $this->hedefler->liste($app), 'olaylar' => array_keys(Olaylar::SOZLUK)]));
    }

    #[Route('', name: 'panel_api_webhook_ekle', methods: ['POST'])]
    public function ekle(Request $request, string $app): JsonResponse
    {
        if (null !== ($red = $this->kapi($request, $app))) {
            return $red;
        }
        $g = json_decode($request->getContent(), true);
        $g = \is_array($g) ? $g : [];
        $sonuc = $this->hedefler->ekle($app, $g['adres'] ?? null, $g['olaylar'] ?? null);

        return isset($sonuc['sir'])
            ? self::korumali(new JsonResponse($sonuc, 201))
            : self::korumali(new JsonResponse(['hatalar' => $sonuc], 422));
    }

    #[Route('/{id}', name: 'panel_api_webhook_durum', requirements: ['id' => self::UUID], methods: ['PUT'])]
    public function durum(Request $request, string $app, string $id): JsonResponse
    {
        if (null !== ($red = $this->kapi($request, $app))) {
            return $red;
        }
        $g = json_decode($request->getContent(), true);
        if (!\is_array($g) || !\is_bool($g['aktif'] ?? null)) {
            return self::hata('aktif: true | false', 422);
        }

        return $this->hedefler->durum($app, $id, $g['aktif']) ? self::korumali(new JsonResponse(null, 204)) : self::hata('hedef yok', 404);
    }

    #[Route('/{id}', name: 'panel_api_webhook_sil', requirements: ['id' => self::UUID], methods: ['DELETE'])]
    public function sil(Request $request, string $app, string $id): JsonResponse
    {
        if (null !== ($red = $this->kapi($request, $app))) {
            return $red;
        }

        return $this->hedefler->sil($app, $id) ? self::korumali(new JsonResponse(null, 204)) : self::hata('hedef yok', 404);
    }

    #[Route('/{id}/teslimler', name: 'panel_api_webhook_teslimler', requirements: ['id' => self::UUID], methods: ['GET'])]
    public function teslimler(string $app, string $id): JsonResponse
    {
        $t = isset($this->uygulamalar[$app]) ? $this->hedefler->teslimler($app, $id) : null;

        return null === $t ? self::hata('hedef yok', 404) : self::korumali(new JsonResponse(['teslimler' => $t]));
    }

    #[Route('/{id}/teslimler/{teslim}/yeniden', name: 'panel_api_webhook_yeniden', requirements: ['id' => self::UUID, 'teslim' => '\d{1,18}'], methods: ['POST'])]
    public function yeniden(Request $request, string $app, string $id, string $teslim): JsonResponse
    {
        if (null !== ($red = $this->kapi($request, $app))) {
            return $red;
        }

        return $this->hedefler->yenidenGonder($app, $id, (int) $teslim) ? self::korumali(new JsonResponse(null, 204)) : self::hata('teslim yok', 404);
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
