<?php

declare(strict_types=1);

namespace App\Controller;

use App\Huni\Huniler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * P2: panelin Huniler sayfası (React) — uygulama başına huniler ve
 * yönlendirme kuralları (A8), sözleşme /panel/api/uygulamalar/{app}/huniler*
 * ve /kurallar*. Yazma kabuğun jetonunu ister (panel-api); doğrulama
 * çekirdekte (Huniler), sayfa yalnız hatayı gösterir.
 */
#[Route('/panel/api/uygulamalar/{app}')]
final class PanelHuniApiController extends AbstractController
{
    public function __construct(
        private readonly Huniler $huniler,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private readonly string $funnelBaseUrl,
    ) {
    }

    #[Route('/huniler', name: 'panel_api_huniler', methods: ['GET'])]
    public function liste(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }

        return self::korumali(new JsonResponse([
            'adres' => $this->funnelBaseUrl.'/'.$app.'/',
            'parametreler' => Huniler::SECIM_PARAMETRELERI,
            // "Aç": yayınlanmışsa yayındaki sürüm, değilse taslak — ikisi de süreli önizleme, ziyaret sayılmaz (Ç70).
            'huniler' => array_map(fn (array $h): array => $h + [
                'ac' => $this->funnelBaseUrl.'/'.$app.'/?huni='.$h['ad'].(null === $h['yayinda'] ? '' : '&yayin=1')
                    .'&onizleme='.$this->huniler->onizlemeAnahtari($app, $h['ad'], time()),
                'duzenle' => $this->generateUrl('panel_huni', ['app' => $app, 'ad' => $h['ad']]),
            ], $this->huniler->huniler($app)),
            'kurallar' => $this->huniler->kurallar($app),
        ]));
    }

    #[Route('/huniler', name: 'panel_api_huni_yeni', methods: ['POST'])]
    public function yeni(Request $request, string $app): JsonResponse
    {
        return $this->yaz($request, $app, function (array $g) use ($app): JsonResponse {
            $ad = \is_string($g['ad'] ?? null) ? trim($g['ad']) : null;
            $kaynak = $g['kaynak'] ?? null;
            if (null === $ad || (null !== $kaynak && !\is_string($kaynak))) {
                return self::hata('ad (ve isteğe bağlı kaynak) metin olmalı', 422);
            }
            $hatalar = $this->huniler->yeniHuni($app, $ad, $kaynak);

            return [] === $hatalar
                ? self::korumali(new JsonResponse(['ad' => $ad, 'duzenle' => $this->generateUrl('panel_huni', ['app' => $app, 'ad' => $ad])], 201))
                : self::korumali(new JsonResponse(['hatalar' => $hatalar], 422));
        });
    }

    /** Ç41: ziyaretsiz huni silinir, ziyaretli arşivlenir; varsayılan kaldırılamaz. */
    #[Route('/huniler/{ad}', name: 'panel_api_huni_kaldir', methods: ['DELETE'])]
    public function kaldir(Request $request, string $app, string $ad): JsonResponse
    {
        return $this->yaz($request, $app, function () use ($app, $ad): JsonResponse {
            $sonuc = $this->huniler->kaldir($app, $ad);

            return \in_array($sonuc, ['silindi', 'arsivlendi'], true)
                ? self::korumali(new JsonResponse(['sonuc' => $sonuc]))
                : self::hata($sonuc, 422);
        });
    }

    #[Route('/huniler/{ad}/varsayilan', name: 'panel_api_huni_varsayilan', methods: ['POST'])]
    public function varsayilan(Request $request, string $app, string $ad): JsonResponse
    {
        return $this->yaz($request, $app, fn (): JsonResponse => $this->huniler->varsayilanYap($app, $ad)
            ? self::korumali(new JsonResponse(null, 204))
            : self::hata("'{$ad}' yok", 404));
    }

    #[Route('/kurallar', name: 'panel_api_kural_ekle', methods: ['POST'])]
    public function kuralEkle(Request $request, string $app): JsonResponse
    {
        return $this->yaz($request, $app, function (array $g) use ($app): JsonResponse {
            $alan = static fn (string $ad): string => \is_string($g[$ad] ?? null) ? $g[$ad] : '';
            $hatalar = $this->huniler->kuralEkle($app, $alan('parametre'), $alan('deger'), $alan('huni'));

            return [] === $hatalar
                ? self::korumali(new JsonResponse(null, 204))
                : self::korumali(new JsonResponse(['hatalar' => $hatalar], 422));
        });
    }

    #[Route('/kurallar/{id}', name: 'panel_api_kural_sil', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function kuralSil(Request $request, string $app, int $id): JsonResponse
    {
        return $this->yaz($request, $app, fn (): JsonResponse => $this->huniler->kuralSil($app, $id)
            ? self::korumali(new JsonResponse(null, 204))
            : self::hata('kural yok', 404));
    }

    #[Route('/kurallar/{id}/tasi', name: 'panel_api_kural_tasi', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function kuralTasi(Request $request, string $app, int $id): JsonResponse
    {
        return $this->yaz($request, $app, function (array $g) use ($app, $id): JsonResponse {
            $yon = $g['yon'] ?? null;
            if (!\in_array($yon, ['yukari', 'asagi'], true)) {
                return self::hata('yon yukari ya da asagi olmalı', 422);
            }

            return $this->huniler->kuralTasi($app, $id, 'yukari' === $yon ? -1 : 1)
                ? self::korumali(new JsonResponse(null, 204))
                : self::hata('taşınamadı', 422);
        });
    }

    /**
     * Uygulama, jeton ve JSON gövdesi denetlenir; sonra işlem.
     *
     * @param callable(array<mixed>): JsonResponse $islem
     */
    private function yaz(Request $request, string $app, callable $islem): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }
        if (!$this->isCsrfTokenValid(PanelUygulamaController::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::hata('Oturum jetonu geçersiz — sayfayı yenileyin.', 403);
        }
        $govde = '' === $request->getContent() ? [] : json_decode($request->getContent(), true);

        return \is_array($govde) ? $islem($govde) : self::hata('gövde JSON nesnesi olmalı', 422);
    }

    private static function hata(string $hata, int $durum): JsonResponse
    {
        return self::korumali(new JsonResponse(['hatalar' => [$hata]], $durum));
    }

    private static function korumali(JsonResponse $yanit): JsonResponse
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $yanit->headers->set('X-Frame-Options', 'DENY');

        return $yanit;
    }
}
