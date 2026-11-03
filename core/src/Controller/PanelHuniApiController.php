<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\Planlar;
use App\Huni\Huniler;
use App\Huni\Tanim;
use App\Huni\Tema;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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
        private readonly Planlar $planlar,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private readonly string $funnelBaseUrl,
        private readonly CsrfTokenManagerInterface $jetonlar,
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

    /**
     * P3: düzenleyici sayfasının açılışı. Jeton düzenleyici uçlarınınki
     * (huni-<app>; kaydet, durum, medya) — bu uçlar P3'te değişmedi.
     */
    #[Route('/huniler/{ad}/duzenleyici', name: 'panel_api_huni_duzenleyici', methods: ['GET'])]
    public function duzenleyici(string $app, string $ad): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::hata("bilinmeyen uygulama: {$app}", 404);
        }
        $taslak = $this->huniler->taslak($app, $ad);
        if (null === $taslak) {
            return self::hata("'{$ad}' hunisi yok", 404);
        }

        return self::korumali(new JsonResponse([
            'baslik' => "{$app} · {$ad}",
            'taslak' => $taslak,
            'imza' => $this->huniler->taslakImzasi($app, $ad),
            'planlar' => array_map(strval(...), array_keys($this->planlar->fiyatKimlikleri($app))),
            'temaVarsayilan' => Tema::VARSAYILAN,
            'yaziTipleri' => Tema::YAZI_TIPLERI,
            'ozellikler' => Tanim::kosulOzellikleri(),
            'jeton' => $this->jetonlar->getToken('huni-'.$app)->getValue(),
            'adresler' => [
                'kaydet' => $this->generateUrl('panel_huni_taslak', ['app' => $app, 'ad' => $ad]),
                'durum' => $this->generateUrl('panel_huni_durum', ['app' => $app, 'ad' => $ad]),
                'medya' => $this->generateUrl('panel_medya_liste', ['app' => $app]),
                'aiDurum' => $this->generateUrl('panel_api_ai_durum'),
                'aiSohbet' => $this->generateUrl('panel_api_ai_sohbet', ['app' => $app, 'huni' => $ad]),
            ],
        ]));
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
