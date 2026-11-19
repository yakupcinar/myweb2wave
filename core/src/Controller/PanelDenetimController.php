<?php

declare(strict_types=1);

namespace App\Controller;

use App\Panel\PanelDenetimi;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Ç152: panel yazma denetimini panelde okur (İşlem geçmişi sayfası) —
 * son 200, yeniden eskiye. Yalnız okuma; panel güvenlik duvarının içinde.
 */
final readonly class PanelDenetimController
{
    public const SON = 200;

    public function __construct(private PanelDenetimi $denetim)
    {
    }

    #[Route('/panel/api/denetim', name: 'panel_api_denetim', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        // Parametresiz rota (giriş) boş dizi: JSON'da {} olsun, [] değil.
        $kayitlar = array_map(static fn (array $k): array => ['kaynak' => (object) $k['kaynak']] + $k, $this->denetim->son(self::SON));
        $yanit = new JsonResponse(['kayitlar' => $kayitlar]);
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $yanit;
    }
}
