# V4 — hareket katmanı (+ Ç101) (2026-11-03)

**İstek:** kullanıcı 2026-11-03: "V4'e devam". Rakip: FunnelFox popup
animasyonu, Lottie; bizde tanımda hareket alanı yoktu (Ç99 ölçümü: 4 sabit
animasyon).

## Önce: Ç101 (V4 öncesine bağlanmıştı)

1. **Düzenleyicide tam ekran istatistik koyu** — sebep ÖLÇÜLDÜ: Puck'ta
   `.huni .bloklar > [data-puck-component] > p.istatistik` (0,4,1) tam ekran
   kuralından `.huni section[data-duzen="tamEkran"] .bloklar p` (0,3,2)
   özgül; hesaplanmış renk `rgb(238,240,255)` (tema metni) vs paragraf
   `rgba(255,255,255,.9)`. `…p.istatistik` (0,4,2) eklendi → düzenleyicide
   `rgba(255,255,255,.9)` (tarayıcıda ölçüldü).
2. **Tam ekran kapakta koyu düğme kaybolur** — düğme kapağın %88 siyah
   karartmasında; arka plan siyah…#1f1f1f. Tanım kuralı: ana renk ikisine
   karşı en az 3:1 (WCAG 1.4.11), yoksa kayıt hatası (ne yapılacağını
   söyler). Ölçüm: varsayılan 3,19 geçer; hazır temalardan Lacivert 2,46,
   Lavanta 2,89, Gül 2,73, Sade 1,08 tam ekranla birlikte reddedilir (tema
   kendi başına geçerli kalır). Elenen: her düğmeye beyaz halka (iyi temaları
   da değiştirirdi).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Tema `hareket`: yok · sakin (varsayılan = bugünkü) · canli | serbest süre/eğri alanları | Ön ayar (G7 ilkesi); varsayılan değişmez → ölçülmüş ekranlar aynı |
| 2 | Canlı: adım geçişinde başlık 60 ms, bloklar 120…480 ms (60 ms arayla, 8 kademe), düğme 300 ms, 420 ms yukarı kayarak; çoklu seçimde basılan kart ve işaret "pop" | blok başına animasyon seçimi | Katalog (D1: serbest CSS/JS yok); AI ve yönetici tek seçim yapar |
| 3 | Yok: adım kayması, çubuk ve düğme geçişi kapalı; yükleme çubuğu/maddeleri kalır | yüklemeyi de durdurmak | Yükleme bilgi taşıyor |
| 4 | Hepsi `prefers-reduced-motion: no-preference` içinde; ekran aracı azaltmayı taklit eder → AI görüntüsü durağan | — | Erişilebilirlik (H4 kuralı, test bekçisi var) |
| 5 | Öznitelik `.huni`'nin KENDİSİNDE (`body.huni` / düzenleyicide `div.huni`), seçici `.huni[data-hareket=…]` | `html[data-hareket]` | Panel yalıtım bekçisi (her seçici `.huni` ile başlar, §43) — gevşetmek yerine öznitelik taşındı |
| 6 | Sayan istatistik (canlı): ilk tam sayı ekrana girince 0'dan 900 ms'de (ease-out) sayar; binlik ayırıcı ve önek/sonek korunur ("10.000+", "%87"); ondalıklı değer sayılmaz; rakamlar sabit genişlik | CSS sayaç (`@property`) | Önek/sonekli metin ve Türkçe binlik ayırıcı CSS'le yazılamaz |
| 7 | Sayacak ortamda ilk çizim 0 — **ölçülen hata**: önce son değer görünüp geri sıçrıyordu ("3 dk" → "1 dk", 80 ms örneklerle); gözlemcisiz/hareketsiz ortamda son değer | — | Titreme yok, önizleme ve ekran görüntüsü doğru |

## Gerçek tarayıcı ölçümleri (Chrome, `document.getAnimations()`)

- canlı, adım geçişi: `huni-adim-ileri@0, huni-giris@60 (h1), @120, @180, @300 (düğme)`.
- canlı, çoklu seçimde tıklama: `huni-sec` (basılı düğme) + `huni-isaret`.
- sayaç örnekleri (80 ms): `0 dk, 0 dk, 1 dk, 2 dk, 2 dk, 2 dk, 3 dk…`.
- yok: seçim + adım değişimi (aliskanlik → telefon) **0** animasyon. (İlk
  ölçüm geçersizdi: çoklu adımda düğme kapalıydı, adım değişmemişti —
  "animasyon yok" bir şey kanıtlamıyordu; seçimle yinelendi.)
- Öznitelik `body`'ye taşındıktan sonra canlı ve yok yeniden ölçüldü.

## Kırma (12/12 kırmızı)

H1 ondalık reddi · H2 binlik ayırıcı · H3 sıfırdan başlama · H4 hareket
azaltma · H5 gözlemcisiz ortam · H6 seçim efekti CSS · H7 oynatıcı
özniteliği · H8 düzenleyici eşlemesi · H9 kapak düğme kuralı · H10 iki
arka planın en kötüsü · H11 Ç101 istatistik rengi · H12 hareket değerleri.

`bin/kontrol` YEŞİL: 483 çekirdek testi / 3858 iddia, 232 sn.
