<?php

declare(strict_types=1);

namespace App\Controller;

use App\Medya\Medyalar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Huni görselleri (E2.3): herkese açık sunum + panelde yükleme/listeleme.
 * Panel uçları /panel altında (security.yaml girişi ister) ve CSRF ister.
 */
final class MedyaController extends AbstractController
{
    public function __construct(
        private readonly Medyalar $medyalar,
    ) {
    }

    /** İçerik özetli ad → dosya hiç değişmez → sonsuz önbellek. */
    #[Route('/medya/{id}.webp', name: 'medya', methods: ['GET'], requirements: ['id' => '[0-9a-f]{64}'])]
    public function sun(string $id): Response
    {
        $yol = $this->medyalar->dosya($id) ?? throw new NotFoundHttpException();
        $yanit = new BinaryFileResponse($yol);
        $yanit->headers->set('Content-Type', 'image/webp');
        $yanit->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        $yanit->headers->set('X-Content-Type-Options', 'nosniff');

        return $yanit;
    }

    #[Route('/panel/medya/{app}', name: 'panel_medya_liste', methods: ['GET'])]
    public function liste(string $app): JsonResponse
    {
        $this->uygulama($app);

        return new JsonResponse(['gorseller' => $this->medyalar->listele($app)]);
    }

    #[Route('/panel/medya/{app}', name: 'panel_medya_yukle', methods: ['POST'])]
    public function yukle(Request $request, string $app): JsonResponse
    {
        $this->uygulama($app);
        if (!$this->isCsrfTokenValid('huni-'.$app, $request->headers->get('X-CSRF-Token', ''))) {
            return new JsonResponse(['hatalar' => ['Oturum jetonu geçersiz — sayfayı yenileyin.']], 403);
        }
        $dosya = $request->files->get('dosya');
        if ($dosya instanceof UploadedFile && \in_array($dosya->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
            return new JsonResponse(['hatalar' => ['dosya en çok 5 MB olmalı']], 422);
        }
        if (!$dosya instanceof UploadedFile || !$dosya->isValid()) {
            return new JsonResponse(['hatalar' => ['dosya gelmedi ya da yükleme yarıda kaldı']], 422);
        }
        $sonuc = $this->medyalar->yukle($app, $dosya->getPathname(), $dosya->getClientOriginalName());

        return isset($sonuc['id'])
            ? new JsonResponse(['id' => $sonuc['id']], 201)
            : new JsonResponse(['hatalar' => $sonuc], 422);
    }

    private function uygulama(string $app): void
    {
        /** @var array<string, mixed> $uygulamalar */
        $uygulamalar = $this->getParameter('uygulamalar');
        if (!isset($uygulamalar[$app])) {
            throw new NotFoundHttpException();
        }
    }
}
