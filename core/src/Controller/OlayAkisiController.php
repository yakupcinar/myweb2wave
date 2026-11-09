<?php

declare(strict_types=1);

namespace App\Controller;

use App\Kimlik\UygulamaYetkisi;
use App\Olay\Olaylar;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * M7c1 (A21): uygulamanın olay akışı — dış sistem (veri ambarı, Zapier)
 * webhook yerine imleçle çeker. Uygulama anahtarı (A5); kişisel veri yok
 * (sözlük, Ç90). İmleç her yanıtta son olayın yeri, devam = hemen ardından
 * daha var; yarım işlemler beklenir (tuzak §53).
 */
final readonly class OlayAkisiController
{
    public const AZAMI = 500;

    public function __construct(
        private UygulamaYetkisi $yetki,
        private Olaylar $olaylar,
    ) {
    }

    #[Route('/apps/{app}/olaylar', name: 'olay_akisi', methods: ['GET'])]
    public function __invoke(Request $request, string $app): JsonResponse
    {
        if (!$this->yetki->izinli($request, $app)) {
            return new JsonResponse(['error' => 'unauthorized'], 401);
        }
        $imlec = $request->query->get('imlec');
        $sinir = $request->query->getInt('sinir', 100);
        $sonuc = $this->olaylar->akis($app, \is_string($imlec) && '' !== $imlec ? $imlec : null, max(1, min(self::AZAMI, $sinir)));
        if (null === $sonuc) {
            return new JsonResponse(['error' => 'invalid_request'], 422);
        }
        $sonuc['olaylar'] = array_map(static fn (array $o): array => ['veri' => (object) $o['veri']] + $o, $sonuc['olaylar']);

        return new JsonResponse($sonuc, 200, ['Cache-Control' => 'no-store']);
    }
}
