<?php

declare(strict_types=1);

namespace App\Controller;

use App\Panel\Rapor;
use App\Panel\Varliklar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * İç panel (A6/2): reklamdan mı geldi, ödedi mi. Giriş security.yaml'da;
 * buradaki her sayfa önbelleğe alınmaz ve çerçeveye gömülemez.
 */
final class PanelController extends AbstractController
{
    /**
     * Panel uygulaması (B5): React kabuğu. Sayfaları istemci yönlendirir;
     * veriler /panel/api/* uçlarından. Derlenmemişse açık hata.
     */
    #[Route('/panel', name: 'panel', methods: ['GET'])]
    public function uygulama(Varliklar $varliklar): Response
    {
        $giris = $varliklar->giris('src/uygulama/main.tsx');
        if (null === $giris) {
            return self::korumali(new Response('Panel derlenmemiş: docker compose run --rm panel npm run build', 503));
        }

        return self::korumali($this->render('panel/uygulama.html.twig', $giris));
    }

    /** Rapor verisi (sözleşme: PanelRaporu). */
    #[Route('/panel/api/rapor', name: 'panel_api_rapor', methods: ['GET'])]
    public function rapor(Request $request, Rapor $rapor): JsonResponse
    {
        $gun = $request->query->getInt('gun', 30);
        if (!\in_array($gun, Rapor::GUNLER, true)) {
            $gun = 30;
        }

        return self::korumali(new JsonResponse([
            'gun' => $gun,
            'gunler' => Rapor::GUNLER,
            'azami' => Rapor::AZAMI_SATIR,
            'sayimlar' => $rapor->sayimlar($gun),
            'izinler' => $rapor->izinler($gun),
            'satirlar' => $rapor->satirlar($gun),
            'adimlar' => $rapor->adimlar($gun),
            'cevaplar' => $rapor->cevaplar($gun),
            'metinCevaplari' => $rapor->metinCevaplari($gun),
        ]));
    }

    #[Route('/panel/giris', name: 'panel_giris', methods: ['GET', 'POST'])]
    public function giris(AuthenticationUtils $kimlik): Response
    {
        // Hangi alanın yanlış olduğu söylenmez (kullanıcı adı tahmini zorlaşsın).
        $hata = $kimlik->getLastAuthenticationError();

        return self::korumali($this->render('panel/giris.html.twig', [
            'hata' => match (true) {
                null === $hata => null,
                $hata instanceof TooManyLoginAttemptsAuthenticationException => 'Çok fazla deneme. Bir dakika sonra tekrar deneyin.',
                default => 'Kullanıcı adı ya da şifre yanlış.',
            },
            'son_ad' => $kimlik->getLastUsername(),
        ]));
    }

    #[Route('/panel/cikis', name: 'panel_cikis', methods: ['GET'])]
    public function cikis(): never
    {
        // Güvenlik duvarının logout dinleyicisi bu isteği buraya gelmeden karşılar.
        throw new \LogicException('logout güvenlik duvarında');
    }

    /**
     * @template T of Response
     *
     * @param T $yanit
     *
     * @return T
     */
    private static function korumali(Response $yanit): Response
    {
        $yanit->headers->set('Cache-Control', 'no-store');
        // Oturum açıkken Symfony Cache-Control'ü sessizce "max-age=0,
        // must-revalidate, private" ile EZİYOR (ölçüldü, panel.md); bu başlık
        // ezmeyi kapatır ve yanıttan çıkarılır.
        $yanit->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        $yanit->headers->set('X-Frame-Options', 'DENY');

        return $yanit;
    }
}
