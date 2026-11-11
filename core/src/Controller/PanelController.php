<?php

declare(strict_types=1);

namespace App\Controller;

use App\Panel\Kullanicilar;
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
    /** P7a2: büyüyen tablolar sayfalı (kullanıcı kararı; web2wave gibi sunucuda, numaralı). */
    public const SAYFA_BOYUTU = 25;

    /**
     * Panel uygulaması (B5): React kabuğu. Sayfaları istemci yönlendirir;
     * veriler /panel/api/* uçlarından. Derlenmemişse açık hata.
     */
    #[Route('/panel', name: 'panel', methods: ['GET'])]
    #[Route('/panel/huniler', name: 'panel_huniler', methods: ['GET'])]
    #[Route('/panel/kullanicilar', name: 'panel_kullanicilar', methods: ['GET'])]
    #[Route('/panel/planlar', name: 'panel_planlar', methods: ['GET'])]
    #[Route('/panel/deneyler', name: 'panel_deneyler', methods: ['GET'])]
    #[Route('/panel/entegrasyonlar', name: 'panel_entegrasyonlar', methods: ['GET'])]
    #[Route('/panel/ayarlar', name: 'panel_ayarlar', methods: ['GET'])]
    #[Route('/panel/yasal', name: 'panel_yasal', methods: ['GET'])]
    public function uygulama(Varliklar $varliklar): Response
    {
        $giris = $varliklar->giris('src/uygulama/main.tsx');
        if (null === $giris) {
            return self::korumali(new Response('Panel derlenmemiş: docker compose run --rm panel npm run build', 503));
        }

        return self::korumali($this->render('panel/uygulama.html.twig', $giris));
    }

    /**
     * P7d (A25): Webhook'lar, API anahtarları ve Ürün bağlamı tek sayfada
     * (Entegrasyonlar). Eski adres yer imi bozulmasın diye ilgili bölüme gider.
     */
    #[Route('/panel/webhooklar', name: 'panel_webhooklar', methods: ['GET'], defaults: ['bolum' => 'webhooklar'])]
    #[Route('/panel/anahtarlar', name: 'panel_anahtarlar', methods: ['GET'], defaults: ['bolum' => 'anahtarlar'])]
    #[Route('/panel/baglam', name: 'panel_baglam', methods: ['GET'], defaults: ['bolum' => 'baglam'])]
    public function eskiEntegrasyon(string $bolum): Response
    {
        return $this->redirect('/panel/entegrasyonlar#'.$bolum, Response::HTTP_MOVED_PERMANENTLY);
    }

    /** P5: abonelikler (CRM) — e-posta maskeli; arama ve durum süzgeci. P7a2: sayfalı, sıralama sunucuda. */
    #[Route('/panel/api/kullanicilar', name: 'panel_api_kullanicilar', methods: ['GET'])]
    public function kullanicilar(Request $request, Kullanicilar $kullanicilar): JsonResponse
    {
        $ara = mb_substr($request->query->getString('ara'), 0, 100);
        $durum = mb_substr($request->query->getString('durum'), 0, 40);
        $sayfa = self::sayfa($request);

        return self::korumali(new JsonResponse([
            ...self::sayfali($sayfa, $kullanicilar->say($ara, $durum)),
            'durumlar' => $kullanicilar->durumlar(),
            'satirlar' => $kullanicilar->liste($ara, $durum, null, $sayfa, self::SAYFA_BOYUTU,
                $request->query->getString('sirala'), 'azalan' !== $request->query->getString('yon')),
        ]));
    }

    /** Rapor verisi (sözleşme: PanelRaporu). */
    #[Route('/panel/api/rapor', name: 'panel_api_rapor', methods: ['GET'])]
    public function rapor(Request $request, Rapor $rapor): JsonResponse
    {
        $gun = self::gun($request);

        return self::korumali(new JsonResponse([
            'gun' => $gun,
            'gunler' => Rapor::GUNLER,
            'sayimlar' => $rapor->sayimlar($gun),
            // P7b: Özet kartları önceki dönemle karşılaştırır (taslak: "Önceki 30 güne göre").
            'gelir' => $rapor->gelir($gun),
            'onceki' => ['sayimlar' => $rapor->sayimlar($gun, null, $gun), 'gelir' => $rapor->gelir($gun, $gun)],
            'izinler' => $rapor->izinler($gun),
            'adimlar' => $rapor->adimlar($gun),
            'cevaplar' => $rapor->cevaplar($gun),
            'kohort' => $rapor->kohort($gun),
            'kampanyalar' => $rapor->kampanyalar($gun),
            'gunluk' => $rapor->gunluk($gun),
        ]));
    }

    /**
     * P7a2: raporun büyüyen üç listesi ayrı ve sayfalı — her tablonun kendi
     * sayfası olur, raporun geri kalanı sayfa değişince yeniden hesaplanmaz.
     */
    #[Route('/panel/api/rapor/{liste}', name: 'panel_api_rapor_liste', requirements: ['liste' => 'ziyaretler|kaynaklar|serbestYanitlar'], methods: ['GET'])]
    public function raporListesi(Request $request, Rapor $rapor, string $liste): JsonResponse
    {
        $gun = self::gun($request);
        $sayfa = self::sayfa($request);

        return self::korumali(new JsonResponse([
            ...self::sayfali($sayfa, $rapor->toplam($liste, $gun)),
            'satirlar' => match ($liste) {
                'ziyaretler' => $rapor->satirlar($gun, $sayfa, self::SAYFA_BOYUTU),
                'kaynaklar' => $rapor->utm($gun, null, $sayfa, self::SAYFA_BOYUTU),
                default => $rapor->metinCevaplari($gun, $sayfa, self::SAYFA_BOYUTU),
            },
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

    private static function gun(Request $request): int
    {
        $gun = $request->query->getInt('gun', 30);

        return \in_array($gun, Rapor::GUNLER, true) ? $gun : 30;
    }

    /** 1'den başlar; bozuk ya da aşırı değer 1'e / üst sınıra çekilir (OFFSET taşmasın). */
    private static function sayfa(Request $request): int
    {
        return max(1, min(10000, $request->query->getInt('sayfa', 1)));
    }

    /** @return array{sayfa: int, boyut: int, toplam: int} */
    private static function sayfali(int $sayfa, int $toplam): array
    {
        return ['sayfa' => $sayfa, 'boyut' => self::SAYFA_BOYUTU, 'toplam' => $toplam];
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
