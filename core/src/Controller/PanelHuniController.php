<?php

declare(strict_types=1);

namespace App\Controller;

use App\Huni\Huniler;
use App\Huni\Tema;
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
        #[Autowire('%kernel.project_dir%/public/varliklar/huni-duzenleyici.js')]
        private readonly string $duzenleyiciDosyasi,
        #[Autowire('%kernel.project_dir%/public/varliklar/editor/.vite/manifest.json')]
        private readonly string $editorManifesti,
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
                'huniler' => $this->huniler->huniler($app),
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

    #[Route('/{app}/{ad}/varsayilan', name: 'panel_huni_varsayilan', methods: ['POST'])]
    public function varsayilan(Request $request, string $app, string $ad): RedirectResponse
    {
        $this->uygulama($app);
        $this->islem($request, $app, fn (): array => $this->huniler->varsayilanYap($app, $ad) ? [] : ["'{$ad}' yok"], "Varsayılan huni artık '{$ad}'.");

        return $this->redirectToRoute('panel_huniler');
    }

    #[Route('/{app}/{ad}', name: 'panel_huni', methods: ['GET'])]
    public function duzenle(string $app, string $ad): Response
    {
        $this->uygulama($app);
        $taslak = $this->huniler->taslak($app, $ad) ?? throw new NotFoundHttpException();

        return self::korumali($this->render('panel/huni.html.twig', [
            // 'app' DEĞİL: Twig'in global app'ini ezer, flaşlar üretimde sessizce kaybolur (tuzak §29).
            'uygulama' => $app,
            'huni' => $ad,
            'taslak' => $taslak,
            'planlar' => array_keys($this->uygulamalar[$app]),
            'tema_varsayilan' => Tema::VARSAYILAN,
            'yazi_tipleri' => Tema::YAZI_TIPLERI,
            'surumler' => $this->huniler->surumler($app, $ad),
            'farkli' => $this->huniler->taslakFarkli($app, $ad),
            'onizleme' => $this->funnelBaseUrl.'/'.$app.'/?huni='.$ad.'&onizleme='.$this->huniler->onizlemeAnahtari($app, $ad, time()),
            'huni_adresi' => $this->funnelBaseUrl.'/'.$app.'/?huni='.$ad,
            // Dosyanın Cache-Control'ü yok: tarayıcı tahminle eski kopyayı
            // çalıştırıyordu (ölçüldü, tuzak §30). İçerik özeti değişince adres değişir.
            'duzenleyici_surumu' => substr((string) md5_file($this->duzenleyiciDosyasi), 0, 10),
        ]));
    }

    /**
     * Sürükle-bırak düzenleyici (E2.4, React + Puck). Betik/stil adresleri
     * Vite manifestinden — içerik özetli adlar (tuzak §30). Derlenmemişse
     * sessizce boş sayfa değil, açık hata.
     */
    #[Route('/{app}/{ad}/surukle', name: 'panel_huni_surukle', methods: ['GET'])]
    public function surukle(string $app, string $ad): Response
    {
        $this->uygulama($app);
        $taslak = $this->huniler->taslak($app, $ad) ?? throw new NotFoundHttpException();
        $manifest = is_file($this->editorManifesti) ? json_decode((string) file_get_contents($this->editorManifesti), true) : null;
        $giris = \is_array($manifest) && \is_array($manifest['src/main.tsx'] ?? null) ? $manifest['src/main.tsx'] : null;
        if (null === $giris || !\is_string($giris['file'] ?? null)) {
            return self::korumali(new Response('Sürükle-bırak düzenleyici derlenmemiş: docker compose run --rm editor npm run build', 503));
        }

        return self::korumali($this->render('panel/huni-surukle.html.twig', [
            'uygulama' => $app,
            'huni' => $ad,
            'taslak' => $taslak,
            'planlar' => array_keys($this->uygulamalar[$app]),
            'tema_varsayilan' => Tema::VARSAYILAN,
            'yazi_tipleri' => Tema::YAZI_TIPLERI,
            'betik' => '/varliklar/editor/'.$giris['file'],
            'stiller' => array_map(static fn (mixed $c): string => '/varliklar/editor/'.(\is_string($c) ? $c : ''), \is_array($giris['css'] ?? null) ? $giris['css'] : []),
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
        $hatalar = $this->huniler->taslakKaydet($app, $ad, $request->toArray()['tanim'] ?? null);

        return new JsonResponse(['hatalar' => $hatalar], [] === $hatalar ? 200 : 422);
    }

    #[Route('/{app}/{ad}/yayinla', name: 'panel_huni_yayinla', methods: ['POST'])]
    public function yayinla(Request $request, string $app, string $ad): RedirectResponse
    {
        $this->uygulama($app);
        $surum = 0;
        $tamam = $this->islem($request, $app, function () use ($app, $ad, &$surum): array {
            $sonuc = $this->huniler->yayinla($app, $ad);
            if (\is_array($sonuc)) {
                return $sonuc;
            }
            $surum = $sonuc;

            return [];
        }, null);
        if ($tamam) {
            $this->addFlash('tamam', "{$ad} v{$surum} yayında. Ziyaretçiler en geç 1 dakika içinde görür.");
        }

        return $this->redirectToRoute('panel_huni', ['app' => $app, 'ad' => $ad]);
    }

    #[Route('/{app}/{ad}/surumler/{surum}/taslaga', name: 'panel_huni_geri_yukle', methods: ['POST'], requirements: ['surum' => '\d+'])]
    public function geriYukle(Request $request, string $app, string $ad, int $surum): RedirectResponse
    {
        $this->uygulama($app);
        $this->islem($request, $app, fn (): array => $this->huniler->surumuTaslagaAl($app, $ad, $surum) ? [] : ["v{$surum} bulunamadı."], "v{$surum} taslağa alındı. Yayınlamak için \"Yayınla\".");

        return $this->redirectToRoute('panel_huni', ['app' => $app, 'ad' => $ad]);
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
