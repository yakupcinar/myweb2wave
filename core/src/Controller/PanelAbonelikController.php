<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\AbonelikYonetimi;
use App\Panel\PanelIslemleri;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

/**
 * M6a (A19): panelden dönem sonunda iptal / geri alma ve son faturanın
 * iadesi; M6c duraklat / sürdür. Yazma kabuğun jetonunu ister (deneyler gibi).
 * Ç151: her deneme (başarılı ya da reddedilen) panel_islemleri'ne yazılır.
 */
#[Route('/panel/api/abonelikler/{abonelik}', requirements: ['abonelik' => 'sub_[A-Za-z0-9]{1,250}'])]
final class PanelAbonelikController extends AbstractController
{
    public function __construct(private readonly AbonelikYonetimi $yonetim, private readonly PanelIslemleri $islemler)
    {
    }

    #[Route('/iptal', name: 'panel_api_abonelik_iptal', methods: ['POST', 'DELETE'])]
    public function iptal(Request $request, string $abonelik): JsonResponse
    {
        $iptal = 'POST' === $request->getMethod();

        return $this->yanit($request, $abonelik, $iptal ? 'iptal' : 'iptal_geri_al', fn (): ?string => $this->yonetim->donemSonundaIptal($abonelik, $iptal));
    }

    /** M6c (A28): POST {ay: 1|2|3} duraklatır, DELETE sürdürür. */
    #[Route('/duraklat', name: 'panel_api_abonelik_duraklat', methods: ['POST', 'DELETE'])]
    public function duraklat(Request $request, string $abonelik): JsonResponse
    {
        return $this->yanit($request, $abonelik, 'DELETE' === $request->getMethod() ? 'surdur' : 'duraklat', function () use ($request, $abonelik): ?string {
            if ('DELETE' === $request->getMethod()) {
                return $this->yonetim->duraklat($abonelik, null);
            }
            $govde = json_decode($request->getContent(), true);
            $ay = \is_array($govde) ? $govde['ay'] ?? null : null;

            return \is_int($ay) ? $this->yonetim->duraklat($abonelik, $ay) : 'süre 1, 2 ya da 3 ay olmalı';
        });
    }

    /** Ç150: POST {eposta, son4} — ödeme formunda yanlış yazılmış e-postayı düzelt. */
    #[Route('/eposta', name: 'panel_api_abonelik_eposta', methods: ['POST'])]
    public function eposta(Request $request, string $abonelik): JsonResponse
    {
        return $this->yanit($request, $abonelik, 'eposta_duzelt', function () use ($request, $abonelik): ?string {
            $govde = json_decode($request->getContent(), true);
            $eposta = \is_array($govde) ? $govde['eposta'] ?? null : null;
            $son4 = \is_array($govde) ? $govde['son4'] ?? null : null;

            return \is_string($eposta) && \is_string($son4) ? $this->yonetim->epostaDuzelt($abonelik, $eposta, $son4) : 'yeni e-posta ve kartın son 4 hanesi gerekli';
        });
    }

    #[Route('/iade', name: 'panel_api_abonelik_iade', methods: ['POST'])]
    public function iade(Request $request, string $abonelik): JsonResponse
    {
        return $this->yanit($request, $abonelik, 'iade', fn (): ?string => $this->yonetim->sonFaturayiIadeEt($abonelik));
    }

    /** Ç151: bu aboneliğe panelden yapılan işlemler, yeniden eskiye (son 50). */
    #[Route('/gecmis', name: 'panel_api_abonelik_gecmis', methods: ['GET'])]
    public function gecmis(string $abonelik): JsonResponse
    {
        return self::korumali(new JsonResponse(['islemler' => $this->islemler->abonelik($abonelik)]));
    }

    /** @param \Closure(): ?string $is işlem; hata metni ya da null */
    private function yanit(Request $request, string $abonelik, string $islem, \Closure $is): JsonResponse
    {
        if (!$this->isCsrfTokenValid(PanelUygulamaController::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        $hata = $is();
        $this->islemler->yaz($this->getUser()?->getUserIdentifier() ?? '', $abonelik, $islem, $hata);

        return self::korumali(null === $hata ? new JsonResponse(null, 204) : new JsonResponse(['hatalar' => [$hata]], 'bilinmeyen abonelik' === $hata ? 404 : 422));
    }

    private static function korumali(JsonResponse $yanit): JsonResponse
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $yanit->headers->set('X-Frame-Options', 'DENY');

        return $yanit;
    }
}
