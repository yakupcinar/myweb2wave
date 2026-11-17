<?php

declare(strict_types=1);

namespace App\Huni\Ekran;

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
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

    /**
     * V1: görünür adımın yerleşimi. Boşluk = içeriğin altından yapışık
     * düğmenin üstüne (düğme yoksa ekranın altına); içerik altı boyutlu
     * YAPRAK öğelerin en alt kenarı (kart rengi tüm ekranı kaplayan kabı
     * saymasın). Yetim satır: yalnız yaprak metin öğesi (seçenekte emoji
     * metnin üstünde ayrı satır gibi ölçülüyordu), çok satırlı ve son satır
     * genişliğin %20'sinden kısa.
     */
    private const OLCUM = <<<'JS'
        (() => {
            const vh = innerHeight;
            const adim = document.querySelector('[data-adim]:not([hidden])');
            const eylem = adim.querySelector('.eylem');
            let alt = 0;
            for (const el of adim.querySelectorAll('*')) {
                if (eylem?.contains(el)) continue;
                const yaprak = el.children.length === 0 || ['IMG', 'BUTTON', 'INPUT', 'TEXTAREA', 'svg'].includes(el.tagName);
                const r = el.getBoundingClientRect();
                if (yaprak && r.width > 0 && r.height > 0) alt = Math.max(alt, r.bottom);
            }
            const yetim = [];
            for (const el of adim.querySelectorAll('*')) {
                const metin = el.textContent.trim();
                if (el.children.length > 0 || !metin || el.closest('[hidden]')) continue;
                const aralik = document.createRange();
                aralik.selectNodeContents(el);
                const satirlar = [...aralik.getClientRects()].reduce((s, r) => {
                    const son = s.at(-1);
                    if (son && Math.abs(son.ust - r.top) < 4) { son.sol = Math.min(son.sol, r.left); son.sag = Math.max(son.sag, r.right); }
                    else s.push({ ust: r.top, sol: r.left, sag: r.right });
                    return s;
                }, []);
                const g = el.getBoundingClientRect().width;
                const son = satirlar.at(-1);
                if (satirlar.length > 1 && son && (son.sag - son.sol) < g * 0.2) yetim.push(metin.slice(0, 60));
            }
            const dugme = adim.querySelector('.eylem button, .paywall button');
            const dr = dugme?.getBoundingClientRect();
            const taban = eylem ? Math.min(vh, eylem.getBoundingClientRect().top) : vh;
            let kucuk = 0, tasan = 0;
            for (const el of adim.querySelectorAll('button, input, textarea')) {
                const r = el.getBoundingClientRect();
                if (r.height > 0 && r.height < 44 && el.type !== 'checkbox') kucuk++;
            }
            for (const el of adim.querySelectorAll('h1, h2, p, li, button, span')) {
                if (el.scrollWidth > el.clientWidth + 1 && getComputedStyle(el).overflowX !== 'visible') tasan++;
            }
            return JSON.stringify({
                // Yükleme ekranı CSS'te ortalı; altındaki boşluk bilinçli, eklenecek blok yok.
                bosAlt: adim.querySelector('section.yukleme') ? 0 : Math.max(0, (taban - alt) / vh),
                kaydirma: document.documentElement.scrollHeight > vh + 1,
                dugmeGorunur: dr ? dr.bottom <= vh : null,
                yetimSatirlar: yetim,
                kucukHedefler: kucuk,
                tasanlar: tasan,
            });
        })()
        JS;

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire('%env(default::EKRAN_CDP)%')]
        private ?string $cdp,
    ) {
    }

    public function cek(string $adres): EkranCekimi
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
            // Hareket azaltma: huni bu tercihte animasyonları kapatır — çekim
            // ara kare değil son, durağan hâli gösterir (yükleme çubuğu boş,
            // maddeler soluk çıkıyordu; mobil-gorunum-3-5.md).
            $sayfa->getSession()->sendMessageSync(new Message('Emulation.setEmulatedMedia', ['features' => [['name' => 'prefers-reduced-motion', 'value' => 'reduce']]]), self::SURE_MS);
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
            $gorsel = (\is_string($b64) ? base64_decode($b64, true) : false) ?: throw new \RuntimeException('görüntü çözülemedi');
            $json = $sayfa->evaluate(self::OLCUM)->getReturnValue();
            /** @var array{bosAlt: float, kaydirma: bool, dugmeGorunur: bool|null, yetimSatirlar: list<string>, kucukHedefler: int, tasanlar: int} $olcum */
            $olcum = \is_string($json) ? json_decode($json, true, flags: \JSON_THROW_ON_ERROR) : throw new \RuntimeException('ölçüm okunamadı');
            $olcum['bosAlt'] = round((float) $olcum['bosAlt'], 2);

            return new EkranCekimi($gorsel, $olcum);
        } finally {
            // Tarayıcıyı değil yalnız sekmeyi kapat: tarayıcı paylaşılan servis.
            $sayfa->close();
            $tarayici->getConnection()->disconnect();
        }
    }
}
