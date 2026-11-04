<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\EkUrunler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * M3c: panelden ek ürün kataloğu (ödeme sonrası tek tık upsell'in sattığı
 * tek seferlik ürünler). Yazma kabuğun jetonunu ister (planlar gibi).
 */
#[Route('/panel/api/uygulamalar/{app}/ek-urunler')]
final class PanelEkUrunController extends AbstractController
{
    public function __construct(
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        private readonly EkUrunler $ekUrunler,
    ) {
    }

    #[Route('', name: 'panel_api_ek_urunler', methods: ['GET'])]
    public function liste(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }

        return self::korumali(new JsonResponse(['ekUrunler' => $this->satirlar($app)]));
    }

    #[Route('', name: 'panel_api_ek_urun_olustur', methods: ['POST'])]
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
        $hatalar = $this->ekUrunler->olustur($app, $g['anahtar'] ?? null, $g['ad'] ?? null, $g['tutar'] ?? null, $g['paraBirimi'] ?? null);

        return [] === $hatalar
            ? self::korumali(new JsonResponse(['ekUrunler' => $this->satirlar($app)], 201))
            : self::korumali(new JsonResponse(['hatalar' => $hatalar], 422));
    }

    /** @return list<array{anahtar: string, ad: string, stripeFiyat: string, fiyat: array{tutar: int|float, paraBirimi: string}|null}> */
    private function satirlar(string $app): array
    {
        return array_map(function (array $u) use ($app): array {
            $f = $this->ekUrunler->fiyat($app, $u['anahtar']);

            return $u + ['fiyat' => null === $f ? null : ['tutar' => $f['tutar'], 'paraBirimi' => $f['paraBirimi']]];
        }, $this->ekUrunler->liste($app));
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
