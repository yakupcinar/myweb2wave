<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\PortalBaglantisi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * M6b2 (A19): web yolundan müşteri portalı. Huni sayfası e-postayı yollar
 * (herkese açık, hesap sızdırmaz); e-postadaki bağlantı önce onay sayfası
 * açar — e-posta tarayıcıları (Safe Links) GET ile önceden açıp tek
 * kullanımlık jetonu yakmasın; jeton düğmenin POST'unda kullanılır.
 */
final class PortalBaglantisiController extends AbstractController
{
    public function __construct(
        private readonly PortalBaglantisi $baglanti,
        /** @var array<string, array<string, string>> */
        #[Autowire('%uygulamalar%')]
        private readonly array $uygulamalar,
        #[Autowire('%env(FUNNEL_BASE_URL)%')]
        private readonly string $huniAdresi,
    ) {
    }

    #[Route('/huni/portal-baglantisi', name: 'huni_portal_baglantisi', methods: ['POST'])]
    public function iste(Request $request): JsonResponse
    {
        $govde = $request->toArray();
        $app = $govde['app'] ?? null;
        $email = $govde['eposta'] ?? null;
        if (!\is_string($app) || !isset($this->uygulamalar[$app])) {
            return new JsonResponse(['error' => 'unknown_app'], 422);
        }
        if (!\is_string($email) || mb_strlen($email) > 254 || false === filter_var(trim($email), \FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'invalid_request'], 422);
        }
        $this->baglanti->iste($app, $email, fn (string $jeton): string => $this->generateUrl('portal_jeton', ['jeton' => $jeton], UrlGeneratorInterface::ABSOLUTE_URL));

        // Abonelik olsun olmasın aynı yanıt (hesap sızdırmaz).
        return new JsonResponse(null, 202);
    }

    #[Route('/portal/{jeton}', name: 'portal_jeton', requirements: ['jeton' => '[A-Za-z0-9_-]{43}'], methods: ['GET', 'POST'])]
    public function ac(Request $request, string $jeton): Response
    {
        if ('GET' === $request->getMethod()) {
            return $this->sayfa(200, null);
        }
        $sonuc = $this->baglanti->kullan($jeton);
        if (\is_array($sonuc)) {
            return new RedirectResponse($sonuc['adres'], 303);
        }

        return $this->sayfa('portal_hatasi' === $sonuc ? 503 : 410, match ($sonuc) {
            'suresi_doldu' => 'Bağlantının süresi doldu.',
            'abonelik_yok' => 'Bu e-postaya bağlı bir abonelik bulunamadı.',
            'portal_hatasi' => 'Şu an açılamadı, birazdan yeniden deneyin.',
            default => 'Bağlantı geçersiz ya da daha önce kullanıldı.',
        });
    }

    private function sayfa(int $durum, ?string $hata): Response
    {
        $yanit = $this->render('portal.html.twig', ['hata' => $hata, 'yeniden' => rtrim($this->huniAdresi, '/').'/keepchat/abonelik/'], new Response(status: $durum));
        // Adres jeton taşır: önbelleğe, başvuru adresine, çerçeveye girmesin.
        $yanit->headers->set('Cache-Control', 'no-store');
        $yanit->headers->set('Referrer-Policy', 'no-referrer');
        $yanit->headers->set('X-Frame-Options', 'DENY');

        return $yanit;
    }
}
