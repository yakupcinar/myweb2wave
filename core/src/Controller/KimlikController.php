<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\MusteriPortali;
use App\Kimlik\DogrulamaKodu;
use App\Kimlik\GirisBaglantisi;
use App\Kimlik\Hak;
use App\Kimlik\UygulamaYetkisi;
use App\RevenueCat\Aktarim;
use App\Yasal\Taslaklar;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Uygulamanın SUNUCUSU için uçlar (A5): bağlama kodu, doğrulama, giriş
 * bağlantısı jetonu (Ç46), hak, müşteri portalı (M6b).
 * Hepsi uygulama anahtarı ister; tarayıcıya açık değil (CORS yok).
 */
final readonly class KimlikController
{
    public function __construct(
        private UygulamaYetkisi $yetki,
        private DogrulamaKodu $kodlar,
        private GirisBaglantisi $giris,
        private Hak $hak,
        private Aktarim $revenuecat,
        private MusteriPortali $portal,
    ) {
    }

    #[Route('/apps/{app}/baglama/kod', name: 'baglama_kod', methods: ['POST'])]
    public function kod(Request $request, string $app): Response
    {
        if (!$this->yetki->izinli($request, $app)) {
            return self::hata('unauthorized', 401);
        }
        $govde = $request->toArray();
        $email = $govde['email'] ?? null;
        if (!\is_string($email) || false === filter_var(DogrulamaKodu::normal($email), \FILTER_VALIDATE_EMAIL)) {
            return self::hata('invalid_request', 422);
        }
        // E1a: uygulamanın dili (isteğe bağlı); bilinmeyen tr.
        $dil = \in_array($govde['dil'] ?? null, Taslaklar::DILLER, true) ? $govde['dil'] : 'tr';

        return 'cok_istek' === $this->kodlar->iste($app, $email, $dil)
            ? self::hata('too_many_requests', 429)
            : new Response('', 202);
    }

    #[Route('/apps/{app}/baglama/dogrula', name: 'baglama_dogrula', methods: ['POST'])]
    public function dogrula(Request $request, string $app): Response
    {
        if (!$this->yetki->izinli($request, $app)) {
            return self::hata('unauthorized', 401);
        }
        $govde = $request->toArray();
        $email = $govde['email'] ?? null;
        $kod = $govde['kod'] ?? null;
        $kullanici = $govde['kullanici'] ?? null;
        if (!\is_string($email) || !\is_string($kod) || !\is_string($kullanici) || '' === $kullanici
            || 1 !== preg_match('/^\d{6}$/', $kod)) {
            return self::hata('invalid_request', 422);
        }

        $sonuc = $this->kodlar->dogrula($app, $email, $kod, $kullanici);
        if (isset($sonuc['kisi'])) {
            $this->revenuecat->gonder(); // A9: işlemden sonra; atmaz
        }

        return isset($sonuc['kisi'])
            ? new JsonResponse(['kisi' => $sonuc['kisi']])
            : self::hata($sonuc['sonuc'], 422);
    }

    #[Route('/apps/{app}/giris/kullan', name: 'giris_kullan', methods: ['POST'])]
    public function girisKullan(Request $request, string $app): Response
    {
        if (!$this->yetki->izinli($request, $app)) {
            return self::hata('unauthorized', 401);
        }
        $govde = $request->toArray();
        $jeton = $govde['jeton'] ?? null;
        $kullanici = $govde['kullanici'] ?? null;
        if (!\is_string($jeton) || '' === $jeton || !\is_string($kullanici) || '' === $kullanici) {
            return self::hata('invalid_request', 422);
        }

        $sonuc = $this->giris->kullan($app, $jeton, $kullanici);
        if (isset($sonuc['kisi'])) {
            $this->revenuecat->gonder(); // A9: işlemden sonra; atmaz
        }

        return isset($sonuc['kisi'])
            ? new JsonResponse(['kisi' => $sonuc['kisi']])
            : self::hata($sonuc['sonuc'], 422);
    }

    #[Route('/apps/{app}/kullanicilar/{kullanici}/hak', name: 'hak', methods: ['GET'])]
    public function hak(Request $request, string $app, string $kullanici): Response
    {
        if (!$this->yetki->izinli($request, $app)) {
            return self::hata('unauthorized', 401);
        }
        $hak = $this->hak->sorgula($app, $kullanici);

        return null === $hak ? self::hata('unknown_user', 404) : new JsonResponse($hak);
    }

    /** M6b (A19): uygulamadaki "Aboneliği yönet" — kişinin Stripe portal oturumu. */
    #[Route('/apps/{app}/kullanicilar/{kullanici}/portal', name: 'portal', methods: ['POST'])]
    public function portal(Request $request, string $app, string $kullanici): Response
    {
        if (!$this->yetki->izinli($request, $app)) {
            return self::hata('unauthorized', 401);
        }
        $sonuc = $this->portal->oturum($app, $kullanici);

        return match ($sonuc) {
            'unknown_user', 'no_subscription' => self::hata($sonuc, 404),
            'portal_not_configured', 'stripe_error' => self::hata($sonuc, 503),
            default => new JsonResponse($sonuc, 201),
        };
    }

    private static function hata(string $kod, int $durum): JsonResponse
    {
        return new JsonResponse(['error' => $kod], $durum);
    }
}
