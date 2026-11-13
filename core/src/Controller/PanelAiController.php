<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\AiAyarlari;
use App\Ai\HuniAsistani;
use App\Huni\Huniler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Panel içi AI asistanı (A2) — sözleşme: /panel/api/ai/*. Giriş security.yaml
 * (/panel altı); yazma CSRF jetonu ister (düzenleyicinin jetonu, huni-<app>).
 * Anahtar yoksa AI kapalı: 503, Claude'a hiçbir istek gitmez.
 */
#[Route('/panel/api/ai')]
final class PanelAiController extends AbstractController
{
    private const AZAMI_ISTEM = 4000;

    public function __construct(
        private readonly AiAyarlari $ayarlar,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
    ) {
    }

    #[Route('/durum', name: 'panel_api_ai_durum', methods: ['GET'])]
    public function durum(): JsonResponse
    {
        return self::korumali(new JsonResponse(['acik' => $this->ayarlar->acik(), 'model' => $this->ayarlar->model]));
    }

    /**
     * İstemi açık huninin taslağına uygular; ilerleme Server-Sent Events
     * olarak akar (olay türleri: metin, arac, arac_sonucu, taslak, hata, bitti).
     */
    #[Route('/huniler/{app}/{huni}/sohbet', name: 'panel_api_ai_sohbet', methods: ['POST'])]
    public function sohbet(Request $request, string $app, string $huni, Huniler $huniler, HuniAsistani $asistan, RateLimiterFactory $aiSohbetLimiter): Response
    {
        if (!isset($this->uygulamalar[$app]) || null === $huniler->taslak($app, $huni)) {
            return self::korumali(new JsonResponse(['hatalar' => ["'{$huni}' adında huni yok"]], 404));
        }
        if (!$this->isCsrfTokenValid('huni-'.$app, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        if (!$this->ayarlar->acik()) {
            return self::korumali(new JsonResponse(['hatalar' => ['AI kapalı: ANTHROPIC_API_KEY tanımlı değil.']], 503));
        }
        $govde = json_decode($request->getContent(), true);
        $istem = \is_array($govde) && \is_string($govde['istem'] ?? null) ? trim($govde['istem']) : '';
        if ('' === $istem || mb_strlen($istem) > self::AZAMI_ISTEM) {
            return self::korumali(new JsonResponse(['hatalar' => ['İstem 1–'.self::AZAMI_ISTEM.' karakter olmalı.']], 422));
        }
        $kimlik = $this->getUser()?->getUserIdentifier() ?? '';
        if (!$aiSohbetLimiter->create($kimlik)->consume()->isAccepted()) {
            return self::korumali(new JsonResponse(['hatalar' => ['Saatlik AI isteği sınırı doldu; biraz sonra yeniden deneyin.']], 429));
        }

        $yanit = new StreamedResponse(static function () use ($asistan, $app, $huni, $istem): void {
            $asistan->calistir($app, $huni, $istem, static function (string $tur, array $veri): void {
                // Sözleşme PanelAiOlayi: data olay türünü de taşır.
                echo "event: {$tur}\ndata: ".json_encode(['tur' => $tur] + $veri, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });
        });
        $yanit->headers->set('Content-Type', 'text/event-stream; charset=utf-8');
        // Ara vekil akışı tamponlamasın (Caddy/nginx).
        $yanit->headers->set('X-Accel-Buffering', 'no');

        return self::korumali($yanit);
    }

    /**
     * @template T of Response
     *
     * @param T $yanit
     *
     * @return T
     */
    private static function korumali(Response $yanit): Response
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $yanit->headers->set('X-Frame-Options', 'DENY');

        return $yanit;
    }
}
