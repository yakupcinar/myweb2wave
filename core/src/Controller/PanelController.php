<?php

declare(strict_types=1);

namespace App\Controller;

use App\Panel\Rapor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    #[Route('/panel', name: 'panel', methods: ['GET'])]
    public function index(Request $request, Rapor $rapor): Response
    {
        $gun = $request->query->getInt('gun', 30);
        if (!\in_array($gun, Rapor::GUNLER, true)) {
            $gun = 30;
        }

        return self::korumali($this->render('panel/index.html.twig', [
            'gun' => $gun,
            'gunler' => Rapor::GUNLER,
            'sayimlar' => $rapor->sayimlar($gun),
            'satirlar' => $rapor->satirlar($gun),
            'adimlar' => $rapor->adimlar($gun),
            'cevaplar' => $rapor->cevaplar($gun),
            'azami' => Rapor::AZAMI_SATIR,
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
