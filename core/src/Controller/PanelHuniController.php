<?php

declare(strict_types=1);

namespace App\Controller;

use App\Huni\Huniler;
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
 * Panelde huni düzenleme (A7 / E1.2): taslak, yayınla, sürümler, önizleme.
 * Giriş security.yaml'da (/panel altı). Her yazma CSRF jetonu ister;
 * doğrulama çekirdekte (Huniler → Tanim), ekran yalnız hatayı gösterir.
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
    ) {
    }

    #[Route('', name: 'panel_huniler', methods: ['GET'])]
    public function liste(): Response
    {
        $huniler = [];
        foreach (array_keys($this->uygulamalar) as $app) {
            $app = (string) $app;
            $surumler = $this->huniler->surumler($app);
            $huniler[] = [
                'app' => $app,
                'yayinda' => $surumler[0]['surum'] ?? null,
                'taslak_var' => null !== $this->huniler->taslak($app),
                'farkli' => $this->huniler->taslakFarkli($app),
            ];
        }

        return self::korumali($this->render('panel/huniler.html.twig', ['huniler' => $huniler]));
    }

    #[Route('/{app}', name: 'panel_huni', methods: ['GET'])]
    public function duzenle(string $app): Response
    {
        $this->uygulama($app);

        return self::korumali($this->render('panel/huni.html.twig', [
            // 'app' DEĞİL: Twig'in global app'ini ezer, flaşlar üretimde sessizce kaybolur (tuzak §29).
            'uygulama' => $app,
            'taslak' => $this->huniler->taslak($app) ?? self::bos(),
            'planlar' => array_keys($this->uygulamalar[$app]),
            'surumler' => $this->huniler->surumler($app),
            'farkli' => $this->huniler->taslakFarkli($app),
            'onizleme' => $this->funnelBaseUrl.'/'.$app.'/?onizleme='.$this->huniler->onizlemeAnahtari($app, time()),
            'huni_adresi' => $this->funnelBaseUrl.'/'.$app.'/',
            // Dosyanın Cache-Control'ü yok: tarayıcı tahminle eski kopyayı
            // çalıştırıyordu (ölçüldü). İçerik özeti değişince adres değişir.
            'duzenleyici_surumu' => substr((string) md5_file($this->duzenleyiciDosyasi), 0, 10),
        ]));
    }

    /** Düzenleyicinin JSON kaydı: 200 ya da 422 + hatalar. */
    #[Route('/{app}/taslak', name: 'panel_huni_taslak', methods: ['POST'])]
    public function taslakKaydet(Request $request, string $app): JsonResponse
    {
        $this->uygulama($app);
        if (!$this->isCsrfTokenValid('huni-'.$app, $request->headers->get('X-CSRF-Token', ''))) {
            return new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403);
        }
        $hatalar = $this->huniler->taslakKaydet($app, $request->toArray()['tanim'] ?? null);

        return new JsonResponse(['hatalar' => $hatalar], [] === $hatalar ? 200 : 422);
    }

    #[Route('/{app}/yayinla', name: 'panel_huni_yayinla', methods: ['POST'])]
    public function yayinla(Request $request, string $app): RedirectResponse
    {
        $this->uygulama($app);
        if (!$this->isCsrfTokenValid('huni-'.$app, $request->request->getString('_token'))) {
            $this->addFlash('hata', 'Oturum jetonu geçersiz — sayfayı yenileyin.');
        } else {
            $sonuc = $this->huniler->yayinla($app);
            \is_int($sonuc)
                ? $this->addFlash('tamam', "v{$sonuc} yayında. Ziyaretçiler en geç 1 dakika içinde görür.")
                : $this->addFlash('hata', 'Yayınlanmadı: '.implode(' · ', $sonuc));
        }

        return $this->redirectToRoute('panel_huni', ['app' => $app]);
    }

    #[Route('/{app}/surumler/{surum}/taslaga', name: 'panel_huni_geri_yukle', methods: ['POST'], requirements: ['surum' => '\d+'])]
    public function geriYukle(Request $request, string $app, int $surum): RedirectResponse
    {
        $this->uygulama($app);
        if (!$this->isCsrfTokenValid('huni-'.$app, $request->request->getString('_token'))) {
            $this->addFlash('hata', 'Oturum jetonu geçersiz — sayfayı yenileyin.');
        } elseif ($this->huniler->surumuTaslagaAl($app, $surum)) {
            $this->addFlash('tamam', "v{$surum} taslağa alındı. Yayınlamak için \"Yayınla\".");
        } else {
            $this->addFlash('hata', "v{$surum} bulunamadı.");
        }

        return $this->redirectToRoute('panel_huni', ['app' => $app]);
    }

    private function uygulama(string $app): void
    {
        if (!isset($this->uygulamalar[$app])) {
            throw new NotFoundHttpException();
        }
    }

    /**
     * Hiç hunisi olmayan uygulama için başlangıç taslağı.
     *
     * @return array<string, mixed>
     */
    private static function bos(): array
    {
        return ['sayfaBasligi' => '', 'adimlar' => [
            ['id' => 'karsilama', 'tur' => 'tanitim', 'baslik' => '', 'metin' => '', 'dugme' => 'Başla'],
            ['id' => 'paywall', 'tur' => 'paywall', 'baslik' => '', 'metin' => '', 'dugme' => 'Abone ol', 'plan' => 'monthly'],
        ]];
    }

    private static function korumali(Response $yanit): Response
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set('X-Frame-Options', 'DENY');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $yanit;
    }
}
