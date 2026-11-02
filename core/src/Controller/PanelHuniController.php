<?php

declare(strict_types=1);

namespace App\Controller;

use App\Huni\Huniler;
use App\Huni\Tema;
use App\Panel\Varliklar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Panelde huni başına düzenleyici (A7): taslak, yayınla, sürümler, önizleme.
 * Liste, varsayılan ve kurallar P2'den beri panel uygulamasında
 * (PanelHuniApiController). Giriş
 * security.yaml'da (/panel altı). Her yazma CSRF jetonu ister; doğrulama
 * çekirdekte (Huniler → Tanim), ekran yalnız hatayı gösterir.
 */
#[Route('/panel/huniler')]
final class PanelHuniController extends AbstractController
{
    public function __construct(
        private readonly Huniler $huniler,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private readonly string $funnelBaseUrl,
        private readonly Varliklar $varliklar,
    ) {
    }

    /**
     * Huni düzenleyicisi (E2.4, React + Puck; b2: tek düzenleyici — klasik
     * form düzenleyicisi kaldırıldı, Ç53). Betik/stil: Varliklar (manifest).
     * Derlenmemişse sessizce boş sayfa değil, açık hata.
     */
    #[Route('/{app}/{ad}', name: 'panel_huni', methods: ['GET'])]
    public function duzenle(string $app, string $ad): Response
    {
        $this->uygulama($app);
        $taslak = $this->huniler->taslak($app, $ad) ?? throw new NotFoundHttpException();
        $giris = $this->varliklar->giris('src/main.tsx');
        if (null === $giris) {
            return self::korumali(new Response('Sürükle-bırak düzenleyici derlenmemiş: docker compose run --rm panel npm run build', 503));
        }

        return self::korumali($this->render('panel/huni-surukle.html.twig', [
            'uygulama' => $app,
            'huni' => $ad,
            'taslak' => $taslak,
            'imza' => $this->huniler->taslakImzasi($app, $ad),
            'planlar' => array_keys($this->uygulamalar[$app]),
            'tema_varsayilan' => Tema::VARSAYILAN,
            'yazi_tipleri' => Tema::YAZI_TIPLERI,
            'betik' => $giris['betik'],
            'stiller' => $giris['stiller'],
        ]));
    }

    /** Düzenleyicinin JSON kaydı: 200 ya da 422 + hatalar. */
    #[Route('/{app}/{ad}/taslak', name: 'panel_huni_taslak', methods: ['POST'])]
    public function taslakKaydet(Request $request, string $app, string $ad): JsonResponse
    {
        $this->uygulama($app);
        if (!$this->isCsrfTokenValid('huni-'.$app, $request->headers->get('X-CSRF-Token', ''))) {
            return new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403);
        }
        $govde = $request->toArray();
        // Ç39: imzasız kayıt da çakışma sayılır — düzenleyici hep yollar.
        $imza = \is_string($govde['imza'] ?? null) ? $govde['imza'] : '';
        $hatalar = $this->huniler->taslakKaydet($app, $ad, $govde['tanim'] ?? null, $imza);
        if ([Huniler::CAKISMA] === $hatalar) {
            return new JsonResponse(['hatalar' => $hatalar], 409);
        }

        return [] === $hatalar
            ? new JsonResponse(['hatalar' => [], 'imza' => $this->huniler->taslakImzasi($app, $ad)])
            : new JsonResponse(['hatalar' => $hatalar], 422);
    }

    /**
     * Sürükle-bırak düzenleyicinin üst çubuğu (b1): yayın durumu, sürümler,
     * taze önizleme bağlantısı. Rakiplerde yayınla/sürümler/önizleme
     * düzenleyicinin içinde (huni-editoru-arastirmasi.md § Ek 2026-10-21).
     */
    #[Route('/{app}/{ad}/durum', name: 'panel_huni_durum', methods: ['GET'])]
    public function durum(string $app, string $ad): Response
    {
        $this->uygulama($app);
        if (null === $this->huniler->taslak($app, $ad)) {
            throw new NotFoundHttpException();
        }

        // Önizleme anahtarı taşır: önbelleğe girmesin (no-store).
        return self::korumali(new JsonResponse([
            'surumler' => $this->huniler->surumler($app, $ad),
            'farkli' => $this->huniler->taslakFarkli($app, $ad),
            'onizleme' => $this->funnelBaseUrl.'/'.$app.'/?huni='.$ad.'&onizleme='.$this->huniler->onizlemeAnahtari($app, $ad, time()),
            'yayindaki' => $this->funnelBaseUrl.'/'.$app.'/?huni='.$ad,
        ]));
    }

    /** Kaydedilmiş taslağı yayınlar: 200 {surum} ya da 422 {hatalar}. */
    #[Route('/{app}/{ad}/durum/yayinla', name: 'panel_huni_durum_yayinla', methods: ['POST'])]
    public function durumYayinla(Request $request, string $app, string $ad): JsonResponse
    {
        $this->uygulama($app);
        if (!$this->isCsrfTokenValid('huni-'.$app, $request->headers->get('X-CSRF-Token', ''))) {
            return new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403);
        }
        $sonuc = $this->huniler->yayinla($app, $ad);

        return \is_int($sonuc) ? new JsonResponse(['surum' => $sonuc]) : new JsonResponse(['hatalar' => $sonuc], 422);
    }

    /** Eski sürümü taslağa alır ve yeni taslağı döner (düzenleyici onunla yeniden açılır). */
    #[Route('/{app}/{ad}/durum/surumler/{surum}/taslaga', name: 'panel_huni_durum_geri_yukle', methods: ['POST'], requirements: ['surum' => '\d+'])]
    public function durumGeriYukle(Request $request, string $app, string $ad, int $surum): JsonResponse
    {
        $this->uygulama($app);
        if (!$this->isCsrfTokenValid('huni-'.$app, $request->headers->get('X-CSRF-Token', ''))) {
            return new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403);
        }
        if (!$this->huniler->surumuTaslagaAl($app, $ad, $surum)) {
            return new JsonResponse(['hatalar' => ["v{$surum} bulunamadı."]], 404);
        }

        return new JsonResponse(['tanim' => $this->huniler->taslak($app, $ad), 'imza' => $this->huniler->taslakImzasi($app, $ad)]);
    }

    /**
     * CSRF'i denetler, işlemi koşar, sonucu flaş olarak yazar.
     */
    private function uygulama(string $app): void
    {
        if (!isset($this->uygulamalar[$app])) {
            throw new NotFoundHttpException();
        }
    }

    private static function korumali(Response $yanit): Response
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set('X-Frame-Options', 'DENY');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $yanit;
    }
}
