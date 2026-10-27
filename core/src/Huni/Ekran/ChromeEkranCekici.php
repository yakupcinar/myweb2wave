<?php

declare(strict_types=1);

namespace App\Huni\Ekran;

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ç72: ayrı kaptaki başsız Chrome'a (compose `ekran`, CDP) bağlanır, adresi
 * telefon boyutunda açar, adım çizilince JPEG çeker. Tarayıcı çekirdekte
 * değil: imaj küçük kalır, sayfa çökerse çekirdek etkilenmez.
 * EKRAN_CDP boşsa kapalı (araç açık hatayla döner).
 */
#[AsAlias(EkranCekici::class)]
final readonly class ChromeEkranCekici implements EkranCekici
{
    /** iPhone 12–15 mantıksal boyutu; 2× piksel — model ayrıntıyı okusun, JPEG ~55 KB. */
    private const GENISLIK = 390;
    private const YUKSEKLIK = 844;
    private const SURE_MS = 15000;

    /**
     * Adım çizildi mi: "Yükleniyor…" (#durum) yok, görünür adım var, görseller
     * ve yazı tipleri yüklendi. Astro geliştirme çubuğu (yalnız yerelde) çıkarılır.
     */
    private const HAZIR = <<<'JS'
        (() => {
            document.querySelector('astro-dev-toolbar')?.remove();
            return !document.querySelector('#durum')
                && !!document.querySelector('[data-adim]:not([hidden])')
                && [...document.images].every((i) => i.complete)
                && document.fonts.status === 'loaded';
        })()
        JS;

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(default::EKRAN_CDP)%')]
        private ?string $cdp,
    ) {
    }

    public function cek(string $adres): string
    {
        if (null === $this->cdp || '' === $this->cdp) {
            throw new \RuntimeException('ekran servisi kapalı (EKRAN_CDP boş)');
        }
        [$ad, $port] = explode(':', $this->cdp, 2) + [1 => '9222'];
        // DevTools yalnız IP ya da localhost Host başlığını kabul eder (ölçüldü).
        $ip = gethostbyname($ad);
        try {
            /** @var array{webSocketDebuggerUrl: string} $surum */
            $surum = $this->http->request('GET', "http://{$ip}:{$port}/json/version", ['timeout' => 3])->toArray();
        } catch (\Throwable) {
            throw new \RuntimeException("tarayıcı servisine ulaşılamadı ({$this->cdp})");
        }
        // Chrome kendi iç adresini (127.0.0.1:9223) yazar; dışarıdan ulaşılan adres bu.
        $ws = (string) preg_replace('#^ws://[^/]+#', "ws://{$ip}:{$port}", $surum['webSocketDebuggerUrl']);

        $tarayici = BrowserFactory::connectToBrowser($ws, ['sendSyncDefaultTimeout' => self::SURE_MS]);
        $sayfa = $tarayici->createPage();
        try {
            $sayfa->setDeviceMetricsOverride(['width' => self::GENISLIK, 'height' => self::YUKSEKLIK, 'deviceScaleFactor' => 2, 'mobile' => true])->await();
            $sayfa->navigate($adres)->waitForNavigation(Page::LOAD, self::SURE_MS);
            $son = microtime(true) + self::SURE_MS / 1000;
            while (true !== $sayfa->evaluate(self::HAZIR)->getReturnValue()) {
                if (microtime(true) > $son) {
                    $durum = $sayfa->evaluate("document.querySelector('#durum')?.textContent ?? ''")->getReturnValue();
                    throw new \RuntimeException('sayfa çizilmedi'.(\is_string($durum) && '' !== $durum ? ": {$durum}" : ''));
                }
                usleep(100_000);
            }

            $b64 = $sayfa->screenshot(['format' => 'jpeg', 'quality' => 80])->getBase64(self::SURE_MS);

            return (\is_string($b64) ? base64_decode($b64, true) : false) ?: throw new \RuntimeException('görüntü çözülemedi');
        } finally {
            // Tarayıcıyı değil yalnız sekmeyi kapat: tarayıcı paylaşılan servis.
            $sayfa->close();
            $tarayici->getConnection()->disconnect();
        }
    }
}
