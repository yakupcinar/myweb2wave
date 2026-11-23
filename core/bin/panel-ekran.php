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
    'rapor-anket' => '/panel?sekme=anket',
    'rapor-kaynaklar' => '/panel?sekme=kaynaklar',
    'rapor-gelir' => '/panel?sekme=gelir',
    'rapor-ziyaretler' => '/panel?sekme=ziyaretler',
    // R1b: süzgeç satırı seçiliyken.
    'rapor-suzgecli' => '/panel?kaynak=meta&deney=baslik',
    'huniler' => '/panel/huniler',
    'kullanicilar' => '/panel/kullanicilar',
    'planlar' => '/panel/planlar',
    'deneyler' => '/panel/deneyler',
    'ayarlar' => '/panel/ayarlar',
    'yasal' => '/panel/yasal',
    'epostalar' => '/panel/epostalar',
    'entegrasyonlar' => '/panel/entegrasyonlar',
    // P8: huni düzenleyicisi (Puck) — panel diline uyumu.
    'duzenleyici' => '/panel/huniler/keepchat/genel',
];
/** Açılır öğelerin görünümü (P7g): sayfa çizilince bu betik koşar, sonra çekilir. Ad açıkça verilince. */
const DURUMLAR = [
    'huniler-menu' => ['/panel/huniler', "document.querySelector('[aria-haspopup=menu]').click()"],
    // P7h: kutunun boş, seçili ve yarım hâli (Ödeme'den tek olay → Tümü yarım; Huni'nin Tümü'sü seçili).
    // P8: düzenleyicinin çekmecesi açık (Puck bazen kapalı açıyor) — uzun adım adları kesilmesin.
    'duzenleyici-bloklar' => ['/panel/huniler/keepchat/genel', "(() => { const acik = [...document.querySelectorAll('body *')].some((e) => e.children.length === 0 && e.textContent === 'Ekran (bloklardan)' && e.checkVisibility()); if (!acik) [...document.querySelectorAll('button')].find((b) => b.textContent.trim() === 'Bloklar')?.click(); })()"],
    'entegrasyonlar-kutu' => ['/panel/entegrasyonlar', "(() => { const g = (ad) => document.querySelector(`[role=group][aria-label=\"\${ad}\"]`); g('Ödeme').querySelectorAll('input')[1].click(); g('Huni').querySelector('input').click(); g('Huni').scrollIntoView({block: 'center'}); })()"],
];
/** genişlik => [yükseklik, telefon mu] */
const BOYUTLAR = [1440 => [900, false], 390 => [844, true]];
const TEMALAR = ['acik', 'koyu'];

/** Sayfa çizildi: ana alan var, "Yükleniyor…" kalmadı, yazı tipleri hazır. */
const HAZIR = <<<'JS'
    (() => {
        // P8: düzenleyicide (Puck) main yok — Puck'ın kökü çizilince hazır.
        const m = document.querySelector('main') ?? document.querySelector('.puck-kok [class*=Puck]')?.closest('.puck-kok');
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
        const K = document.querySelector('main') ? 'main' : 'body';
        const ad = (e) => (e.getAttribute('aria-label') || e.textContent || e.getAttribute('placeholder') || `${e.tagName}.${String(e.className).split(' ')[0]}`).trim().slice(0, 40);
        const tasanlar = [...document.querySelectorAll(`${K} *`)].filter((e) => {
            // P8: alanın içinde uzun değer kaydırılır (yerleşim kusuru değil; E-postalar'da konu).
            if (e.scrollWidth <= e.clientWidth + 1 || ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.tagName)) return false;
            const s = getComputedStyle(e);
            return s.overflowX !== 'auto' && s.overflowX !== 'scroll' && s.display !== 'inline' && e.clientWidth > 1;
        }).map(ad);
        const kucuk = G < 600 ? [...document.querySelectorAll('button, a, input, select, textarea')].filter((e) => {
            const r = e.getBoundingClientRect();
            return r.height > 0 && r.height < 36 && e.type !== 'checkbox' && e.type !== 'radio' && !e.closest('p, td, li span');
        }).map(ad) : [];
        // P7g: içeriği yatay kaydırmanın arkasında kalan kaplar (tablo telefonda) ve
        // görünür alandan taşan açık menü.
        const kaydirilan = [...document.querySelectorAll(`${K} *`)].filter((e) => {
            const s = getComputedStyle(e);
            return (s.overflowX === 'auto' || s.overflowX === 'scroll') && e.scrollWidth > e.clientWidth + 1;
        }).map((e) => (e.querySelector('table')?.getAttribute('aria-label') || e.closest('[aria-label]')?.getAttribute('aria-label') || ad(e)));
        const menuDisari = [...document.querySelectorAll('[role=menu]')].some((m) => {
            const r = m.getBoundingClientRect();
            return r.left < 0 || r.right > G;
        });
        // P8: aynı görsel satırdaki denetimler (alan, seçim, düğme) aynı yükseklikte ve
        // alanlar aynı yazı boyutunda olmalı — satır içi formda yerel <select> farklı çiziliyordu.
        const denetimler = [...document.querySelectorAll(`${K} input, ${K} select, ${K} button`)].filter((e) => {
            const r = e.getBoundingClientRect();
            // Yalnız kutusu görünenler: çerçevesiz, zeminsiz simge düğmesinin yüksekliği gözle seçilmez (Puck'ın geri al 29 / panel düğmesi 32 px).
            const s = getComputedStyle(e);
            const kutulu = s.borderTopWidth !== '0px' || !['rgba(0, 0, 0, 0)', 'transparent'].includes(s.backgroundColor);
            return r.height > 0 && kutulu && e.checkVisibility({ contentVisibilityAuto: true, visibilityProperty: true }) && e.type !== 'checkbox' && e.type !== 'radio' && e.type !== 'hidden' && !e.closest('table, [role=tablist], [role=group]');
        });
        const esitsiz = [];
        denetimler.forEach((a, i) => denetimler.slice(i + 1).forEach((b) => {
            const ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
            if (a.closest('section') !== b.closest('section') || Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top) < 8) return;
            const fa = a.tagName === 'BUTTON' ? null : getComputedStyle(a).fontSize, fb = b.tagName === 'BUTTON' ? null : getComputedStyle(b).fontSize;
            if (Math.abs(ra.height - rb.height) > 1 || (fa && fb && fa !== fb)) {
                esitsiz.push(`${ad(a)} ${Math.round(ra.height)}px${fa ? '/' + fa : ''} ≠ ${ad(b)} ${Math.round(rb.height)}px${fb ? '/' + fb : ''}`);
            }
        }));
        // P8 (düzenleyici): görünen İngilizce arayüz yazısı (Puck'ın varsayılan sözlüğü) ve
        // tarayıcının kendi çizdiği denetim / bağlantı — panel dilinde değil.
        const INGILIZCE = ['Page', 'Blocks', 'Outline', 'Fields', 'Components', 'Publish', 'No items', 'Collapse all', 'Other',
            'Toggle left sidebar', 'Toggle right sidebar', 'Toggle menu bar', 'Zoom viewport in', 'Zoom viewport out', 'Duplicate', 'Delete', 'Select parent'];
        // checkVisibility: kapalı <details> içeriği content-visibility ile gizli ama boyutu var.
        const gorunur = (e) => { const r = e.getBoundingClientRect(); return r.width > 1 && r.height > 1 && e.checkVisibility({ contentVisibilityAuto: true, visibilityProperty: true }); };
        const ingilizce = [...document.querySelectorAll(`${K} *`)].filter(gorunur).flatMap((e) => [
            ...[...e.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent.trim()),
            e.getAttribute('aria-label') ?? '', e.getAttribute('title') ?? '',
        ]).filter((t) => INGILIZCE.includes(t) || /^Switch to /.test(t));
        const ham = [...document.querySelectorAll(`${K} input, ${K} select, ${K} button, ${K} a`)].filter(gorunur).filter((e) => {
            if (e.type === 'checkbox' || e.type === 'radio' || e.type === 'color' || e.closest('iframe')) return false;
            const s = getComputedStyle(e);
            return e.type === 'file' || (['inset', 'outset'].includes(s.borderTopStyle) && s.borderTopWidth !== '0px') || ['rgb(118, 118, 118)', 'rgb(133, 133, 133)', 'rgb(0, 0, 238)', 'rgb(85, 26, 139)'].includes(e.tagName === 'A' ? s.color : s.borderTopColor);
        }).map((e) => `${e.tagName.toLowerCase()}${e.type === 'file' ? '[file]' : ''}:${ad(e)}`);
        // P8: yazısı iki satıra kırılan düğme / rozet — en düşük yükseklik (32 px) iki satırı
        // da taşıdığı için eşitsizlik ölçümü görmüyordu (1280 px'te "Önizle ↗" kırıldı).
        // Yalnız yazı düğümlerinin satırları (simge, renk noktası ayrı satır sayılmasın); 4 px içi aynı satır.
        const satirSayisi = (e) => {
            const gez = document.createTreeWalker(e, NodeFilter.SHOW_TEXT);
            const ustler = [];
            for (let n = gez.nextNode(); n; n = gez.nextNode()) {
                if (!n.textContent.trim()) continue;
                const r = document.createRange(); r.selectNodeContents(n);
                ustler.push(...[...r.getClientRects()].filter((x) => x.width > 0).map((x) => x.top));
            }
            return ustler.sort((a, b) => a - b).filter((u, i, d) => i === 0 || u - d[i - 1] > 4).length;
        };
        const kirilan = [...document.querySelectorAll(`${K} button, ${K} summary, ${K} .puck-dugme, ${K} .puck-not`)]
            .filter((e) => gorunur(e) && !e.closest('table') && satirSayisi(e) > 1).map(ad);
        // P8 (düzenleyici): üst çubuk, sağ panel ve önizleme çerçevesinin zemini — koyu temada
        // panel rengi (yüzey #1c1c1e), önizleme her temada beyaz (huni kendi zeminini çizer).
        const zemin = (e) => { for (; e; e = e.parentElement) { const b = getComputedStyle(e).backgroundColor; if (!['rgba(0, 0, 0, 0)', 'transparent'].includes(b)) return b; } return null; };
        const cerceve = document.querySelector('iframe');
        const zeminler = K === 'body' ? {
            ust: zemin(document.elementFromPoint(G / 2, 60)),
            sag: zemin(document.elementFromPoint(G - 40, 400)),
            onizleme: cerceve ? zemin(cerceve.parentElement) : null,
        } : null;
        // Sayfanın ne kadarını hangi bölüm kaplıyor (uzun sayfayı bölmek için).
        const bolumler = Object.fromEntries([...document.querySelectorAll('main section[aria-label]')]
            .filter((b) => !b.parentElement.closest('section[aria-label]'))
            .map((b) => [b.getAttribute('aria-label'), Math.round(b.getBoundingClientRect().height)]));
        return JSON.stringify({
            bolumYukseklikleri: bolumler,
            sayfaYuksekligi: document.documentElement.scrollHeight,
            kaydirilanKaplar: [...new Set(kaydirilan)].slice(0, 8),
            menuEkranDisi: menuDisari,
            sayfaGenisligi: document.documentElement.scrollWidth,
            yatayKayma: document.documentElement.scrollWidth > G + 1,
            tasanlar: [...new Set(tasanlar)].slice(0, 8),
            kucukHedefler: [...new Set(kucuk)].slice(0, 8),
            esitsizSatirlar: [...new Set(esitsiz)].slice(0, 8),
            ingilizce: [...new Set(ingilizce)].slice(0, 12),
            hamDenetimler: [...new Set(ham)].slice(0, 12),
            zeminler,
            kirilanDugmeler: [...new Set(kirilan)].slice(0, 8),
        });
    })
    JS;

$ortam = @file_get_contents(\dirname(__DIR__).'/var/ekran/panel.env') ?: throw new RuntimeException('var/ekran/panel.env yok — bin/panel-ekran ile koşun');
preg_match("/^EKRAN_SIFRE='([^']+)'$/m", $ortam, $m) ?: throw new RuntimeException('EKRAN_SIFRE okunamadı');
$sifre = $m[1];
$secilen = array_slice($argv, 1);
$hepsi = SAYFALAR + array_map(static fn (array $d): string => $d[0], DURUMLAR);
$sayfalar = [] === $secilen ? SAYFALAR : array_intersect_key($hepsi, array_flip($secilen));
if ([] === $sayfalar) {
    fwrite(STDERR, 'bilinmeyen sayfa; seçenekler: '.implode(' ', array_keys($hepsi))."\n");
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
                if (isset(DURUMLAR[$ad])) {
                    $sayfa->evaluate(DURUMLAR[$ad][1]);
                    usleep(300_000);
                }
                usleep(400_000);
                $dosya = "{$ad}-{$tema}-{$genislik}.jpg";
                if (isset(DURUMLAR[$ad])) {
                    // Tam sayfa çekim görünür alanı yeniden boyutlar; boyut değişince kapanan
                    // açılır öğe (EylemMenusu) kaybolurdu (ölçüldü) — yalnız görünür alan.
                    echo $ad, ' açık menü: ', json_encode($sayfa->evaluate("!!document.querySelector('[role=menu]')")->getReturnValue()), "\n";
                    $sayfa->screenshot(['format' => 'jpeg', 'quality' => 80])->saveToFile("{$cikti}/{$dosya}", SURE_MS);
                } else {
                    $sayfa->screenshot(['format' => 'jpeg', 'quality' => 80, 'captureBeyondViewport' => true, 'clip' => $sayfa->getFullPageClip()])->saveToFile("{$cikti}/{$dosya}", SURE_MS);
                }
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
