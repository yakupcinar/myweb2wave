<?php

declare(strict_types=1);

/*
 * P7f: panel ekran döngüsü — bin/panel-ekran çağırır (çekirdek kabında).
 * `ekran` kabındaki başsız Chrome'a CDP ile bağlanır, panel-ekran kabına
 * test kullanıcısıyla girer, her sayfayı iki tema × iki genişlikte çizip
 * tam sayfa JPEG ve yerleşim ölçümü yazar (var/ekran/panel/).
 * Kimlik var/ekran/panel.env'den (git dışı, bin/panel-ekran üretir).
 */

use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use HeadlessChromium\Page;

require \dirname(__DIR__).'/vendor/autoload.php';

const ADRES = 'http://panel-ekran';
const SURE_MS = 20000;
const SAYFALAR = [
    'rapor' => '/panel',
    'huniler' => '/panel/huniler',
    'kullanicilar' => '/panel/kullanicilar',
    'planlar' => '/panel/planlar',
    'deneyler' => '/panel/deneyler',
    'ayarlar' => '/panel/ayarlar',
    'yasal' => '/panel/yasal',
    'entegrasyonlar' => '/panel/entegrasyonlar',
];
/** genişlik => [yükseklik, telefon mu] */
const BOYUTLAR = [1440 => [900, false], 390 => [844, true]];
const TEMALAR = ['acik', 'koyu'];

/** Sayfa çizildi: ana alan var, "Yükleniyor…" kalmadı, yazı tipleri hazır. */
const HAZIR = <<<'JS'
    (() => {
        const m = document.querySelector('main');
        return !!m && m.innerText.trim() !== '' && !/Yükleniyor/.test(m.innerText)
            && document.fonts.status === 'loaded';
    })()
    JS;

/**
 * Testin göremediği yerleşim kusurları: sayfanın yatay kayması, içeriğini
 * kesen öğe (kaydırma kabı olan tablolar ve 1 px'lik ekran okuyucu
 * etiketleri hariç), telefonda 36 px'ten kısa dokunma hedefi (panel düğmesi
 * min-h-9). Genişlik innerWidth'ten değil istenen boyuttan: telefon kipinde
 * tarayıcı görünür alanı taşan içeriğe göre genişletir, innerWidth de onunla
 * büyür (ilk koşuda Aboneler 435 px'e açıldı, ölçüm "kayma yok" dedi).
 */
const OLCUM = <<<'JS'
    ((G) => {
        const ad = (e) => (e.getAttribute('aria-label') || e.textContent || e.getAttribute('placeholder') || e.tagName).trim().slice(0, 40);
        const tasanlar = [...document.querySelectorAll('main *')].filter((e) => {
            if (e.scrollWidth <= e.clientWidth + 1) return false;
            const s = getComputedStyle(e);
            return s.overflowX !== 'auto' && s.overflowX !== 'scroll' && s.display !== 'inline' && e.clientWidth > 1;
        }).map(ad);
        const kucuk = G < 600 ? [...document.querySelectorAll('button, a, input, select, textarea')].filter((e) => {
            const r = e.getBoundingClientRect();
            return r.height > 0 && r.height < 36 && e.type !== 'checkbox' && e.type !== 'radio' && !e.closest('p, td, li span');
        }).map(ad) : [];
        return JSON.stringify({
            sayfaGenisligi: document.documentElement.scrollWidth,
            yatayKayma: document.documentElement.scrollWidth > G + 1,
            tasanlar: [...new Set(tasanlar)].slice(0, 8),
            kucukHedefler: [...new Set(kucuk)].slice(0, 8),
        });
    })
    JS;

$ortam = @file_get_contents(\dirname(__DIR__).'/var/ekran/panel.env') ?: throw new RuntimeException('var/ekran/panel.env yok — bin/panel-ekran ile koşun');
preg_match("/^EKRAN_SIFRE='([^']+)'$/m", $ortam, $m) ?: throw new RuntimeException('EKRAN_SIFRE okunamadı');
$sifre = $m[1];
$secilen = array_slice($argv, 1);
$sayfalar = [] === $secilen ? SAYFALAR : array_intersect_key(SAYFALAR, array_flip($secilen));
if ([] === $sayfalar) {
    fwrite(STDERR, 'bilinmeyen sayfa; seçenekler: '.implode(' ', array_keys(SAYFALAR))."\n");
    exit(2);
}
$cikti = \dirname(__DIR__).'/var/ekran/panel';
@mkdir($cikti, 0o755, true);

// DevTools yalnız IP ya da localhost Host başlığını kabul eder (ChromeEkranCekici).
$ip = gethostbyname('ekran');
$surum = json_decode((string) file_get_contents("http://{$ip}:9222/json/version"), true);
$ws = is_array($surum) && is_string($surum['webSocketDebuggerUrl'] ?? null) ? $surum['webSocketDebuggerUrl'] : throw new RuntimeException('tarayıcıya ulaşılamadı');
$tarayici = BrowserFactory::connectToBrowser((string) preg_replace('#^ws://[^/]+#', "ws://{$ip}:9222", $ws), ['sendSyncDefaultTimeout' => SURE_MS]);
$sayfa = $tarayici->createPage();
$olcumler = [];
try {
    $sayfa->getSession()->sendMessageSync(new Message('Emulation.setEmulatedMedia', ['features' => [['name' => 'prefers-reduced-motion', 'value' => 'reduce']]]), SURE_MS);
    $sayfa->navigate(ADRES.'/panel/giris')->waitForNavigation(Page::LOAD, SURE_MS);
    $sayfa->evaluate(sprintf(
        "document.querySelector('[name=_username]').value = 'ekran'; document.querySelector('[name=_password]').value = %s; document.querySelector('form.giris').submit();",
        json_encode($sifre, \JSON_THROW_ON_ERROR),
    ))->waitForPageReload(Page::LOAD, SURE_MS);
    $yer = $sayfa->evaluate('location.pathname')->getReturnValue();
    if ('/panel/giris' === $yer) {
        throw new RuntimeException('giriş olmadı (panel-ekran kabı panel.env ile mi başladı?)');
    }

    foreach (BOYUTLAR as $genislik => [$yukseklik, $telefon]) {
        $sayfa->setDeviceMetricsOverride(['width' => $genislik, 'height' => $yukseklik, 'deviceScaleFactor' => 1, 'mobile' => $telefon])->await();
        foreach (TEMALAR as $tema) {
            $sayfa->evaluate(sprintf("localStorage.setItem('panel-tema', '%s')", $tema));
            foreach ($sayfalar as $ad => $yol) {
                $sayfa->navigate(ADRES.$yol)->waitForNavigation(Page::LOAD, SURE_MS);
                $son = microtime(true) + SURE_MS / 1000;
                while (true !== $sayfa->evaluate(HAZIR)->getReturnValue()) {
                    if (microtime(true) > $son) {
                        fwrite(STDERR, "{$ad} {$tema} {$genislik}: çizilmedi (zaman aşımı), yine de çekiliyor\n");
                        break;
                    }
                    usleep(150_000);
                }
                usleep(400_000);
                $dosya = "{$ad}-{$tema}-{$genislik}.jpg";
                $sayfa->screenshot(['format' => 'jpeg', 'quality' => 80, 'captureBeyondViewport' => true, 'clip' => $sayfa->getFullPageClip()])->saveToFile("{$cikti}/{$dosya}", SURE_MS);
                $json = $sayfa->evaluate(OLCUM."({$genislik})")->getReturnValue();
                $olcumler[$dosya] = is_string($json) ? json_decode($json, true) : null;
                echo $dosya, ' ', is_string($json) ? $json : 'ölçüm yok', "\n";
            }
        }
    }
} finally {
    file_put_contents("{$cikti}/olcum.json", json_encode($olcumler, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));
    $sayfa->close();
    $tarayici->getConnection()->disconnect();
}
