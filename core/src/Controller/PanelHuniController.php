<?php

declare(strict_types=1);

namespace App\Controller;

use App\Huni\Huniler;
use App\Huni\Tema;
use App\Panel\Varliklar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Panelde huniler (A7, A8): uygulama başına huniler, yönlendirme kuralları,
 * varsayılan; huni başına taslak, yayınla, sürümler, önizleme. Giriş
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

    #[Route('', name: 'panel_huniler', methods: ['GET'])]
    public function liste(): Response
    {
        $uygulamalar = [];
        foreach (array_keys($this->uygulamalar) as $app) {
            $app = (string) $app;
            $uygulamalar[] = [
                'ad' => $app,
                'adres' => $this->funnelBaseUrl.'/'.$app.'/',
                // "Aç": yayınlanmışsa canlı adres (ziyaret sayılır), değilse taslağın süreli önizlemesi (ölçümsüz).
                'huniler' => array_map(fn (array $h): array => $h + ['ac' => $this->funnelBaseUrl.'/'.$app.'/?huni='.$h['ad']
                    .(null === $h['yayinda'] ? '&onizleme='.$this->huniler->onizlemeAnahtari($app, $h['ad'], time()) : '')], $this->huniler->huniler($app)),
                'kurallar' => $this->huniler->kurallar($app),
            ];
        }

        return self::korumali($this->render('panel/huniler.html.twig', [
            'uygulamalar' => $uygulamalar,
            'parametreler' => Huniler::SECIM_PARAMETRELERI,
        ]));
    }

    #[Route('/{app}/yeni', name: 'panel_huni_yeni', methods: ['POST'])]
    public function yeni(Request $request, string $app): RedirectResponse
    {
        $this->uygulama($app);
        $ad = trim($request->request->getString('ad'));
        $kaynak = $request->request->getString('kaynak');
        $tamam = $this->islem($request, $app, fn (): array => $this->huniler->yeniHuni($app, $ad, '' === $kaynak ? null : $kaynak), "'{$ad}' hunisi açıldı. Düzenleyip yayınla.");

        return $tamam ? $this->redirectToRoute('panel_huni', ['app' => $app, 'ad' => $ad]) : $this->redirectToRoute('panel_huniler');
    }

    #[Route('/{app}/kurallar', name: 'panel_kural_ekle', methods: ['POST'])]
    public function kuralEkle(Request $request, string $app): RedirectResponse
    {
        $this->uygulama($app);
        $this->islem($request, $app, fn (): array => $this->huniler->kuralEkle(
            $app,
            $request->request->getString('parametre'),
            $request->request->getString('deger'),
            $request->request->getString('huni'),
        ), 'Kural eklendi.');

        return $this->redirectToRoute('panel_huniler');
    }

    #[Route('/{app}/kurallar/{id}/sil', name: 'panel_kural_sil', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function kuralSil(Request $request, string $app, int $id): RedirectResponse
    {
        $this->uygulama($app);
        $this->islem($request, $app, fn (): array => $this->huniler->kuralSil($app, $id) ? [] : ['kural yok'], 'Kural silindi.');

        return $this->redirectToRoute('panel_huniler');
    }

    #[Route('/{app}/kurallar/{id}/tasi', name: 'panel_kural_tasi', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function kuralTasi(Request $request, string $app, int $id): RedirectResponse
    {
        $this->uygulama($app);
        $yon = 'yukari' === $request->request->getString('yon') ? -1 : 1;
        $this->islem($request, $app, fn (): array => $this->huniler->kuralTasi($app, $id, $yon) ? [] : ['taşınamadı'], null);

        return $this->redirectToRoute('panel_huniler');
    }

    /** Ç41: ziyaretsiz huni silinir, ziyaretli arşivlenir; varsayılan kaldırılamaz. */
    #[Route('/{app}/{ad}/kaldir', name: 'panel_huni_kaldir', methods: ['POST'])]
    public function kaldir(Request $request, string $app, string $ad): RedirectResponse
    {
        $this->uygulama($app);
        $sonuc = '';
        if ($this->islem($request, $app, function () use ($app, $ad, &$sonuc): array {
            $sonuc = $this->huniler->kaldir($app, $ad);

            return \in_array($sonuc, ['silindi', 'arsivlendi'], true) ? [] : [$sonuc];
        }, null)) {
            $this->addFlash('tamam', 'silindi' === $sonuc
                ? "'{$ad}' silindi."
                : "'{$ad}' arşivlendi — ziyaret verisi raporda kalır, kuralları kaldırıldı.");
        }

        return $this->redirectToRoute('panel_huniler');
    }

    #[Route('/{app}/{ad}/varsayilan', name: 'panel_huni_varsayilan', methods: ['POST'])]
    public function varsayilan(Request $request, string $app, string $ad): RedirectResponse
    {
        $this->uygulama($app);
        $this->islem($request, $app, fn (): array => $this->huniler->varsayilanYap($app, $ad) ? [] : ["'{$ad}' yok"], "Varsayılan huni artık '{$ad}'.");

        return $this->redirectToRoute('panel_huniler');
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
     *
     * @param callable(): list<string> $islem hatalar; boşsa başarılı
     */
    private function islem(Request $request, string $app, callable $islem, ?string $tamam): bool
    {
        if (!$this->isCsrfTokenValid('huni-'.$app, $request->request->getString('_token'))) {
            $this->addFlash('hata', 'Oturum jetonu geçersiz — sayfayı yenileyin.');

            return false;
        }
        $hatalar = $islem();
        if ([] !== $hatalar) {
            $this->addFlash('hata', implode(' · ', $hatalar));

            return false;
        }
        if (null !== $tamam) {
            $this->addFlash('tamam', $tamam);
        }

        return true;
    }

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
