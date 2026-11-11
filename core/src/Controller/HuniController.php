<?php

declare(strict_types=1);

namespace App\Controller;

use App\Billing\Fiyatlar;
use App\Billing\ParaBirimi;
use App\Billing\UlkeBulucu;
use App\Huni\Deneyler;
use App\Huni\Huniler;
use App\Huni\Tanim;
use App\Yasal\YasalMetinler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Huni sayfası tanımı buradan okur: hangi huni olacağını kurallar seçer
 * (A8), yayınlanmış en son sürüm döner (A7); ya da imzalı önizleme
 * anahtarıyla bir huninin taslağı. Anahtarsız ve herkese açık.
 */
final readonly class HuniController
{
    /** Huni metinlerinin dili (sayfa lang="tr"); yasal adresler bu dilde. */
    private const DIL = 'tr';

    /** Yayın en geç bu kadar sonra görünür (tarayıcı/CDN önbelleği). */
    private const ONBELLEK_SN = 60;

    public function __construct(
        private Huniler $huniler,
        private Fiyatlar $fiyatlar,
        private UlkeBulucu $ulkeler,
        private \App\Billing\EkUrunler $ekUrunler,
        private Deneyler $deneyler,
        private YasalMetinler $yasal,
        private UrlGeneratorInterface $adresler,
    ) {
    }

    /**
     * Önbellek anahtarı adrestir: huni sayfası yalnız seçim parametrelerini
     * gönderir (fbclid her tıklamada farklı — gönderilseydi önbellek tutmazdı).
     */
    #[Route('/huni/{app}', name: 'huni_yayinda', methods: ['GET'], requirements: ['app' => '[a-z0-9_-]+'])]
    public function yayinda(Request $request, string $app): JsonResponse
    {
        $parametreler = [];
        foreach ([...Huniler::SECIM_PARAMETRELERI, 'huni'] as $ad) {
            $deger = $request->query->get($ad);
            if (\is_string($deger) && '' !== $deger && mb_strlen($deger) <= 200) {
                $parametreler[$ad] = $deger;
            }
        }
        // M9d1: ülke ve dil de kurala girer (adresteki ?ulke= / ?dil= testi önce).
        $ulke = $this->ulkeler->ulke($request->getClientIp(), $request->query->get('ulke'));
        if (null !== $ulke) {
            $parametreler['ulke'] = $ulke;
        }
        $dil = self::dil($request);
        if (null !== $dil) {
            $parametreler['dil'] = $dil;
        }
        $huni = $this->huniler->sec($app, $parametreler);
        $yayin = null === $huni ? null : $this->huniler->yayinda($app, $huni);
        if (null === $huni || null === $yayin) {
            return new JsonResponse(['error' => 'unknown_app'], 404);
        }
        // M4a (A17): tohumsuz (depo engelli) ziyaretçi deneye girmez; ?huni=
        // doğrudan test adresidir, yönlendirme deneyi onu ezmez.
        $tohum = $request->query->getString('tohum');
        $atama = [];
        if (1 === preg_match(Deneyler::TOHUM, $tohum)) {
            if (!isset($parametreler['huni'])) {
                $y = $this->deneyler->yonlendir($app, $huni, $tohum);
                $varyant = $y['huni'] === $huni ? $yayin : $this->huniler->yayinda($app, $y['huni']);
                if (null !== $varyant) {
                    [$huni, $yayin, $atama] = [$y['huni'], $varyant, $y['atama']];
                }
            }
            $atama += $this->deneyler->icerik($app, $huni, $tohum);
        }
        $yanit = new JsonResponse(['huni' => $huni] + $yayin + ([] === $atama ? [] : ['deneyler' => $atama]) + $this->fiyatAlani($app, $request) + $this->ekUrunAlani($app, $yayin['tanim']) + $this->yasalAlani($app));
        // M1c: fiyat ziyaretçinin ülkesine (IP) göre — ortak önbellek (CDN) bir
        // ülkenin yanıtını ötekine verirdi; yalnız ziyaretçinin tarayıcısı tutar.
        $yanit->setPrivate();
        $yanit->setMaxAge(self::ONBELLEK_SN);
        // M9d1: dil kuralı tarayıcının diline göre başka huni seçebilir.
        $yanit->setVary('Accept-Language');

        return $yanit;
    }

    /** M9d1: ziyaretçinin dili — ?dil= (test, dil değiştirici), yoksa Accept-Language'in ilki; iki harf. */
    private static function dil(Request $request): ?string
    {
        $test = $request->query->get('dil');
        if (\is_string($test) && 1 === preg_match('/^[a-z]{2}$/', $test)) {
            return $test;
        }
        $ilk = $request->getLanguages()[0] ?? null;

        return null !== $ilk && 1 === preg_match('/^([a-z]{2})(_|$)/i', $ilk, $m) ? strtolower($m[1]) : null;
    }

    /**
     * M9b2 (A23): yalnız YAYINDAKİ (kaydedilmiş) yasal sayfaların adresi — paywall
     * adımında adres yoksa ve izin şeridinde kullanılır. Dil şimdilik huninin
     * dili (tr); dile göre seçim M9d / M11.
     *
     * @return array{yasal?: non-empty-array<string, string>}
     */
    private function yasalAlani(string $app): array
    {
        $adresler = [];
        foreach ($this->yasal->yayindakiler($app, self::DIL) as $belge) {
            $adresler[$belge] = $this->adresler->generate('yasal_sayfa', ['app' => $app, 'belge' => $belge, 'dil' => self::DIL], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return [] === $adresler ? [] : ['yasal' => $adresler];
    }

    /** @return array{fiyatlar?: non-empty-array<string, array<string, mixed>>} */
    private function fiyatAlani(string $app, Request $request): array
    {
        $ulke = $this->ulkeler->ulke($request->getClientIp(), $request->query->get('ulke'));
        $fiyatlar = $this->fiyatlar->uygulama($app, ParaBirimi::ulkeninki($ulke));

        return [] === $fiyatlar ? [] : ['fiyatlar' => $fiyatlar];
    }

    /**
     * M3c: ödeme sonrası ekranın upsell ürünü — ad ve Stripe tutarı (başarı
     * sayfası gösterir); okunamazsa alan yok, upsell gösterilmez.
     *
     * @param array<mixed> $tanim
     *
     * @return array{ekUrunler?: array<string, array{ad: string, tutar: int|float, paraBirimi: string}>}
     */
    private function ekUrunAlani(string $app, array $tanim): array
    {
        $urun = \is_array($tanim['sonrasi'] ?? null) && \is_array($tanim['sonrasi']['upsell'] ?? null) ? ($tanim['sonrasi']['upsell']['urun'] ?? null) : null;
        $fiyat = \is_string($urun) ? $this->ekUrunler->fiyat($app, $urun) : null;

        return null === $fiyat ? [] : ['ekUrunler' => [$urun => $fiyat]];
    }

    #[Route('/huni/{app}/taslak', name: 'huni_taslak', methods: ['GET'], requirements: ['app' => '[a-z0-9_-]+'])]
    public function taslak(Request $request, string $app): JsonResponse
    {
        $ad = $request->query->getString('huni');
        $anahtar = $request->query->getString('anahtar');
        $taslak = 1 === preg_match(Tanim::KIMLIK, $ad) ? $this->huniler->taslak($app, $ad) : null;
        if (null === $taslak || !$this->huniler->anahtarGecerli($app, $ad, $anahtar, time())) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }
        // Ç70: yayin=1 aynı anahtarla yayındaki sürümü gösterir — panel "Aç"
        // canlıya bakarken ziyaret açmasın (önizlemede oynatıcı ölçmez).
        $sonuc = '1' === $request->query->getString('yayin') ? $this->huniler->yayinda($app, $ad) : ['surum' => null, 'tanim' => $taslak];
        if (null === $sonuc) {
            return new JsonResponse(['error' => 'forbidden'], 403);
        }
        $yanit = new JsonResponse(['huni' => $ad] + $sonuc + $this->fiyatAlani($app, $request) + $this->ekUrunAlani($app, $sonuc['tanim']) + $this->yasalAlani($app));
        $yanit->headers->set('Cache-Control', 'no-store');

        return $yanit;
    }
}
