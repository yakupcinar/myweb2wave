# L8 — Logo + masaüstü düzeni (Ç61 (5))

2026-10-26 · karar `pre-setup.md` A13.

## Ölçüm (önce)
1200 px'te içerik 520 px'lik sütunda sayfanın en üstüne yapışık, çevresi
boş; logo/marka alanı yoktu (tarayıcı, 2026-10-26).

## Karar
- Tanıma isteğe bağlı üst düzey `logo` (görsel kimliği, 64-hex) —
  sözleşme `HuniTanimi.logo` + `Tanim.php` (`gorsel` doğrulaması). **Temaya
  değil:** tema "özel tema" anahtarına bağlı; logo ondan bağımsız
  olmalı. Alt metin boş: başlık ürünü zaten adlandırıyor (L9 alt metin
  kararı ayrı).
- Ortak `Logo` bileşeni (`img.logo`, en çok 160×40); huni oynatıcısı
  sayfanın başında, Puck kök önizlemesi aynı bileşenle çizer. Puck kökte
  "Logo (isteğe bağlı)" görsel alanı; boşsa tanıma yazılmaz.
- `huni.css` `@media (min-width: 768px)`: `.huni` üstten 8vh, 32/40 px iç
  boşluk, ince kenar, `2×köşe` yuvarlak → kart. Dar ekranda değişmez.
  Kural `.huni` kapsamında: Puck önizlemesi geniş görünümde aynı kartı
  gösterir (H3 eşliği).
- **Reddedilen:** dikey tam ortalama (uzun adımda üst taşar, kaydırma
  zıplar) · gölge (koyu temada görünmez, kenar iki temada da okunur).

## Test
Çekirdek 69 HuniTest (+3: geçersiz/boş logo, yayından sözleşmeye uyan
logo). Bileşen 29 (+2: `Logo` çıktısı, masaüstü kural bloğu; sınıf listesi
`logo`). Huni 48 (+1: varsa başta, yoksa yok). Düzenleyici 42 (+1:
gidiş-dönüş, boşken yazılmaz). React 19 kökteki tek `img` için statik
çıktıya `preload` ekliyor → logo testi `toContain`.

## Kırma
Testler uygulamayla aynı turda yazıldı (önce kırmızısı görülmedi) — ölçtüğü
kırmayla kanıtlandı:

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | logo doğrulanmaz | 🔴 geçersiz + boş logo |
| K2 | `logo` üst alan olarak reddedilir | 🔴 geçerli logo + sözleşme testi |
| K3 | masaüstü kenarı yok | 🔴 masaüstü CSS testi |
| K4 | oynatıcı logoyu çizmez | 🔴 huni logo testi |
| K5 | Puck logoyu tanıma yazmaz | 🔴 gidiş-dönüş |
| K6 | boş logo da yazılır | 🔴 8 düzenleyici testi |
| K7 | sınıf `kapak` | 🔴 logo + sınıf testi |

## Tarayıcı (Chrome, yerel; deneme hunisi `b1deneme`)
Taslak yedeklendi, ilk denemede tablo adı yanlış (`medyalar`, doğrusu
`medya`) → boş `logo` yazıldı, hemen yedekten geri yüklendi. Gerçek görsel
kimliğiyle (`/medya/…webp` 200) önizleme: 1200 px'te logo 55×40, gövde üst
boşluk 51 px, 1 px kenar, 32/40 iç boşluk — kart; 500 px'te üst boşluk 0,
kenar 0, iç boşluk 16, yatay kaydırma yok. Taslak geri yüklendi, JSON
**AYNI**.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 219 çekirdek testi / 1.365 doğrulama.
