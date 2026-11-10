<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ai\UrunBaglami;
use App\Huni\Varsayilanlar;
use App\Mcp\ApiAnahtarlari;
use App\Uygulama\UygulamaAyarlari;
use App\Yasal\Taslaklar;
use App\Yasal\YasalMetinler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Panel: uygulamalar ve ürün bağlamı (A4) — sözleşme /panel/api/uygulamalar*.
 * Yazma CSRF ister (panel uygulamasının jetonu, panel-api; kabuktan gelir).
 */
#[Route('/panel/api/uygulamalar')]
final class PanelUygulamaController extends AbstractController
{
    public const JETON = 'panel-api';

    public function __construct(
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        private readonly UrunBaglami $baglam,
        private readonly UygulamaAyarlari $ayarlar,
        private readonly YasalMetinler $yasal,
        private readonly Varsayilanlar $varsayilanlar,
        private readonly ApiAnahtarlari $anahtarlar,
        /** A5a: MCP istemcisine verilecek adres (herkese açık adres gelince ortamdan). */
        #[Autowire('%env(MCP_GENEL_ADRES)%')]
        private readonly string $mcpAdresi,
    ) {
    }

    #[Route('', name: 'panel_api_uygulamalar', methods: ['GET'])]
    public function liste(): JsonResponse
    {
        return self::korumali(new JsonResponse(['uygulamalar' => array_map(strval(...), array_keys($this->uygulamalar))]));
    }

    #[Route('/{app}/baglam', name: 'panel_api_baglam', methods: ['GET'])]
    public function oku(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }

        return self::korumali(new JsonResponse($this->baglam->oku($app)));
    }

    #[Route('/{app}/baglam', name: 'panel_api_baglam_kaydet', methods: ['PUT'])]
    public function kaydet(Request $request, string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        $hatalar = $this->baglam->kaydet($app, json_decode($request->getContent(), true));

        return self::korumali([] === $hatalar
            ? new JsonResponse($this->baglam->oku($app))
            : new JsonResponse(['hatalar' => $hatalar], 422));
    }

    /** M9a (A23): uygulama kimliği — ad, URL şeması, mağaza adresi, destek e-postası. */
    #[Route('/{app}/ayarlar', name: 'panel_api_ayarlar', methods: ['GET'])]
    public function ayarlar(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }

        return self::korumali(new JsonResponse($this->ayarlar->oku($app)));
    }

    #[Route('/{app}/ayarlar', name: 'panel_api_ayarlar_kaydet', methods: ['PUT'])]
    public function ayarlariKaydet(Request $request, string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        $hatalar = $this->ayarlar->kaydet($app, json_decode($request->getContent(), true));

        return self::korumali([] === $hatalar
            ? new JsonResponse($this->ayarlar->oku($app))
            : new JsonResponse(['hatalar' => $hatalar], 422));
    }

    /** M9c (A23): yeni hunilere kopyalanan görünüm — hangi huniden, ne zaman, neler. */
    #[Route('/{app}/varsayilanlar', name: 'panel_api_varsayilanlar', methods: ['GET'])]
    public function varsayilanlar(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }

        return self::korumali(new JsonResponse($this->varsayilanOzeti($app)));
    }

    #[Route('/{app}/varsayilanlar', name: 'panel_api_varsayilanlar_kaldir', methods: ['DELETE'])]
    public function varsayilanlariKaldir(Request $request, string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        $this->varsayilanlar->kaldir($app);

        return self::korumali(new JsonResponse($this->varsayilanOzeti($app)));
    }

    /** @return array{kaynak: string|null, alindi: string|null, tema: bool, logo: bool, paywall: bool} */
    private function varsayilanOzeti(string $app): array
    {
        $v = $this->varsayilanlar->oku($app) ?? [];

        return [
            'kaynak' => \is_string($v['kaynak'] ?? null) ? $v['kaynak'] : null,
            'alindi' => \is_string($v['alindi'] ?? null) ? $v['alindi'] : null,
            'tema' => isset($v['tema']), 'logo' => isset($v['logo']), 'paywall' => isset($v['paywall']),
        ];
    }

    /** M9b1 (A23): yasal veriler + belge × dil metinleri (kaydedilmemişse taslak) ve herkese açık adresleri. */
    #[Route('/{app}/yasal', name: 'panel_api_yasal', methods: ['GET'])]
    public function yasal(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }

        return self::korumali(new JsonResponse($this->yasalYaniti($app)));
    }

    #[Route('/{app}/yasal/veriler', name: 'panel_api_yasal_veriler', methods: ['PUT'])]
    public function yasalVeriler(Request $request, string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        $hatalar = $this->yasal->verileriKaydet($app, json_decode($request->getContent(), true));

        return self::korumali([] === $hatalar ? new JsonResponse($this->yasalYaniti($app)) : new JsonResponse(['hatalar' => $hatalar], 422));
    }

    /** Kaydetmek = yayınlamak (A23: taslak yayında değildir). */
    #[Route('/{app}/yasal/{belge}/{dil}', name: 'panel_api_yasal_metin', methods: ['PUT'])]
    public function yasalMetin(Request $request, string $app, string $belge, string $dil): JsonResponse
    {
        if (!isset($this->uygulamalar[$app]) || !\in_array($belge, Taslaklar::BELGELER, true) || !\in_array($dil, Taslaklar::DILLER, true)) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen belge: {$app}/{$belge}/{$dil}"]], 404));
        }
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        $hatalar = $this->yasal->kaydet($app, $belge, $dil, json_decode($request->getContent(), true));

        return self::korumali([] === $hatalar ? new JsonResponse($this->yasalYaniti($app)) : new JsonResponse(['hatalar' => $hatalar], 422));
    }

    /** @return array{veriler: array{unvan: string, adres: string, kayitNo: string}, belgeler: list<array<string, mixed>>} */
    private function yasalYaniti(string $app): array
    {
        return [
            'veriler' => $this->yasal->veriler($app),
            'belgeler' => array_map(fn (array $b): array => $b + [
                'adres' => $this->generateUrl('yasal_sayfa', ['app' => $app, 'belge' => $b['belge'], 'dil' => $b['dil']], UrlGeneratorInterface::ABSOLUTE_URL),
            ], $this->yasal->belgeler($app)),
        ];
    }

    /** A5a: kişisel API anahtarları (MCP) — liste; anahtarın kendisi yok, önek var. */
    #[Route('/{app}/anahtarlar', name: 'panel_api_anahtarlar', methods: ['GET'])]
    public function anahtarlar(string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }

        return self::korumali(new JsonResponse(['mcpAdresi' => $this->mcpAdresi, 'anahtarlar' => $this->anahtarlar->liste($app)]));
    }

    /** Yeni anahtar: kendisi YALNIZ bu yanıtta (bir kez); sonra yalnız önek görünür. */
    #[Route('/{app}/anahtarlar', name: 'panel_api_anahtar_olustur', methods: ['POST'])]
    public function anahtarOlustur(Request $request, string $app): JsonResponse
    {
        if (!isset($this->uygulamalar[$app])) {
            return self::korumali(new JsonResponse(['hatalar' => ["bilinmeyen uygulama: {$app}"]], 404));
        }
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }
        $govde = json_decode($request->getContent(), true);
        $ad = \is_array($govde) && \is_string($govde['ad'] ?? null) ? trim($govde['ad']) : '';
        if ('' === $ad || mb_strlen($ad) > 100) {
            return self::korumali(new JsonResponse(['hatalar' => ['Ad 1–100 karakter olmalı (ör. "Claude Code — dizüstü").']], 422));
        }
        // M7c2 (A21): yönetim izni yalnız açıkça true ise.
        $yonetim = true === ($govde['yonetim'] ?? null);
        $yeni = $this->anahtarlar->olustur($app, $ad, $yonetim);

        return self::korumali(new JsonResponse(['id' => $yeni['id'], 'ad' => $ad, 'anahtar' => $yeni['anahtar'], 'yonetim' => $yonetim], 201));
    }

    /** Geri alınamaz; çağrı kaydı kalır. */
    #[Route('/{app}/anahtarlar/{id}', name: 'panel_api_anahtar_iptal', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function anahtarIptal(Request $request, string $app, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid(self::JETON, $request->headers->get('X-CSRF-Token', ''))) {
            return self::korumali(new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403));
        }

        return self::korumali($this->anahtarlar->iptal($app, $id)
            ? new JsonResponse(null, 204)
            : new JsonResponse(['hatalar' => ['Anahtar yok ya da zaten iptal edilmiş.']], 404));
    }

    private static function korumali(JsonResponse $yanit): JsonResponse
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $yanit->headers->set('X-Frame-Options', 'DENY');

        return $yanit;
    }
}
