# Blok: H1 — ortak huni bileşenleri (B4) — 2026-10-21

## Ne yapıldı
Kökte `huni-bilesenleri/` açıldı: `Tanitim`, `Soru`, `Paywall` (React),
`tema.ts` (varsayılan tema, yazı tipi yığınları, WCAG kontrastı, düğme
yazısı, CSS değişkenleri) ve `huni.css` (huni sayfasının bütün stili).
Panel sürükle-bırak düzenleyicisinin (Puck) üç bileşeni artık satır içi
stille kendi kopyasını çizmiyor, bu bileşenleri ve `huni.css`'i kullanıyor.
Huni sayfası (`HuniKabugu.astro`) aynı CSS'i ve `tema.ts`'i kullanıyor. Kendi
`<style>` bloğu, `parlaklik` kopyası ve yazı tipi tablosu silindi. Huni
henüz React değil (H2). Tema formülü bugün üç yerde değil iki yerde:
`tema.ts` + `Tema.php`. İkisi aynı örnek dosyasıyla (`tema-ornekleri.json`)
ölçülüyor.

Ekranda: tarayıcıda varsayılan huni (`/keepchat/`) ve koyu temalı huni
(`?huni=fitness`) eskisi gibi. Panel önizlemesi temalı adımları huni
sayfasının sınıflarıyla çiziyor (bkz. Ölçüm).

## Neden böyle

| Karar | Elenen | Neden |
|---|---|---|
| Paketin **kendi bağımlılığı yok**; kullanan kabın `node_modules`'u compose ile `/huni-bilesenleri/node_modules`'a bağlanır | Pakete kendi `package.json` bağımlılıkları + ayrı birim | İkinci React kurulumu = iki React riski (Ç45: tek sürüm). Disk %98 dolu. Bağlama sayesinde `tsc`, Vitest ve Prettier paket dizininde ek ayar olmadan çalışıyor |
| Paketleyen tarafta `resolve.dedupe: ["react","react-dom"]` + `tek-react` bekçisi (`core/editor/vite.config.ts`) | Yalnız `dedupe` | Aynı birim iki yoldan görününce Vite iki ayrı modül sanıyor. Ölçüm: `dedupe` olmadan paket +346 B büyüyor, `react.transitional.element` 3→4 (ikinci `jsx-runtime`). Bugün zararsız; H2'de hook kullanınca ikinci React demek. Bekçi bunu derlemede kırmızı yapıyor |
| `tsconfig` `paths` / Vite takma adı yok; göreli içe aktarma (`../../../huni-bilesenleri/…`) | `@huni/*` takma adı | Kapta ve makinede aynı yola çıkıyor (`/huni-bilesenleri` ↔ depo kökü); üç araca (tsc, Vite, Astro) ayrı ayar gerekmiyor |
| CSS kuralları `.huni` altında, değişkenler `:root`'ta | Huni sayfasındaki gibi kapsamsız `button {…}` | Puck ana sayfanın stillerini önizleme iframe'ine kopyalıyor (`syncHostStyles`). Kapsamsız kural Puck'ın kendi düğmelerini boyardı. Ölçüldü: ana sayfada `12px 16px` dolgulu düğme 0/38 |
| Huni sayfasında `<body class="huni">` | `<main class="huni">` | Arka plan rengi `body`'den tuvale yayılıyor (önceki davranış). Panelde aynı işi dış kutu yapıyor |
| Bileşen props'u düz alanlar (`baslik`, `gorsel?`, `medyaKoku`); sözleşme tipine bağlı değil | Sözleşme tipini (`components["schemas"]…`) props yapmak | Sözleşme tipleri iki projeye üretiliyor (`funnel/src/sozlesme`, `core/editor/src/sozlesme.ts`); pakete üçüncü kopya gerekirdi. Uyum kullanan tarafın `tsc`'sinde ölçülüyor |
| Görsel kökü prop (`medyaKoku`) | React context | Hook yok, kural 6a; Puck'ta sabit `""`, H2'de çekirdek adresi |
| Bileşen testi `react-dom/server` `renderToStaticMarkup` (düğüm ortamı) | Testing Library + jsdom | H1 bileşenleri yalnız çizim yapıyor, etkileşim yok. Yeni bağımlılık gerekmiyor (disk). Etkileşim testleri H2'de |
| H1'de bileşenler yalnız veri alıyor (tıklama yok) | H2 için `onIleri` vb. şimdiden | Kural 6a; H2 ekleyecek |

## Testler — neyi ölçüyor
- `huni-bilesenleri/tema.test.ts` (11): `Tema.php` ile ortak örnekler
  (varsayılan, yazı tipleri, 4 kontrast), düğme yazısı (renkten bağımsız
  beklenti, tuzak §3), `huni.css :root` = `temaDegiskenleri(VARSAYILAN)`,
  her CSS kuralı `.huni` altında.
- `huni-bilesenleri/bilesenler.test.tsx` (7): D1 (HTML kaçışlanır, 8 alan),
  D2 (görsel adresi kökten + kimlikten; boş dize = görsel yok), D11 (`h1
  tabindex=-1`), DOM huni sayfasındakiyle aynı sınıflar. Her sınıf
  `huni.css`'te bir seçicinin **öznesi**.
- `core/tests/Huni/TemaTest.php` `testHuniBilesenleriyleOrtakOrnekler`: aynı
  JSON'u okuyor.
- `bin/kontrol`: `bilesen-bicim`, `bilesen-tip`, `bilesen-test`, `html-yok`
  (D1: üç dizinde ham HTML özniteliği yok).

## Kırma denemeleri (her biri yedek → boz → uygulandı mı → hedefli test → geri → `cmp`)

| # | Deneme | Sonuç |
|---|---|---|
| K1 | `tema.ts` parlaklık katsayısı 0.2126→0.2 | 🔴 3 test |
| K2 | `anaYazi` karşılaştırması ters | 🔴 5 test |
| K3 | `huni.css :root --ana-yazi` #000000 | 🔴 1 test |
| K4 | `YAZI_TIPLERI` `mono`→`kod` | 🔴 1 test |
| K5 | `Tema::VARSAYILAN` köşe 10→12 | 🔴 PHPUnit 1 |
| K6 | `Tema::YAZI_TIPLERI`'nden `mono` silindi | 🔴 PHPUnit 1 |
| K7 | `Soru` başlığı ham HTML ile basıldı | 🔴 D1 |
| K8 | görsel adresinden `medyaKoku` çıkarıldı | 🔴 2 test |
| K9 | `Tanitim` başlığından `tabIndex` silindi | 🔴 1 test |
| K10 | CSS `.huni .secenekler {` → `.secenek-listesi` | ⚪ **tutmadı** → test düzeltildi → 🔴 1 test |
| K10b | CSS `img.kapak` → `img.kapak-gorsel` | 🔴 1 test |
| K11 | `.huni button {` → `button {` (kapsamsız) | 🔴 1 test |
| K12 | `gorsel ? …` → `gorsel !== undefined ? …` (boş dize görsel sayılır) | 🔴 1 test |
| K13 | K7'nin değişikliği, `html-yok` adımına karşı | ⚠️ **sahte kırmızı** → yorum düzeltildi → 🔴 yalnız kod satırı |
| K14 | `vite.config.ts`'ten `dedupe` silindi | 🔴 derleme: "ikinci kopya pakete giriyor" |

**K10 neden tutmadı:** sınıf testi sınıfın `huni.css`'te *geçmesini*
istiyordu. `.huni .secenekler button` kuralı kaldığı için, `.secenekler`'in
kendi kuralı (grid) gidince de geçti. İddia "sınıf boyanıyor" değil,
"metin dosyada var" ölçüyordu. Düzeltme: sınıf bir seçicinin son parçası
(öznesi) olmalı.

**K13 neden sahteydi:** `grep` bileşen dosyasının **yorumundaki** öznitelik
adını da buluyordu. Yani `html-yok` adımı yeşil durumda da kırmızıydı (kırma
tablosundaki "aranan metin yorumda da geçiyor" kalıbı, bu kez kontrol
adımında). Yorum adı yazmadan anlatıyor; adım yeşil durumda 0 döndü.

## Ölçüm
- Paket 18/18 (Vitest), düzenleyici 5/5, çekirdek **169 test** (1034
  iddia), `bin/kontrol` **EXIT=0, 139 sn** (macOS, Docker, disk %98, 4,9 GB boş).
- Huni derlemesi: `keepchat/index.html` 1.390 B (öncesi 1.561 B). CSS satır
  içi gömülü, JS dosyaları aynı üç dosya.
- Tarayıcı (Chrome, `huni-dev` :4321, çekirdek :8081):
  - `/keepchat/`: `body.huni`, ana düğme `rgb(37,99,235)` / beyaz, köşe
    10px, çubuk 6px; soru adımında seçenekler sola hizalı, kenarlıklı.
  - `?huni=fitness` (`#16a34a`; elle hesap: parlaklık ≈ 0,27 → siyahta
    6,4, beyazda 3,3): `--ana-yazi=#000000`, düğme yazısı `rgb(0,0,0)`,
    köşe 20px, `--yazi-tipi` yuvarlak yığın, gövde `rgb(11,18,32)`.
  - Panel `/panel/huniler/keepchat/fitness/surukle`: iframe'de `.huni`
    var, ana düğme yeşil/siyah 20px, seçenekler `flex` + sola. Kapak
    `/medya/<64 hex>.webp`. Ana sayfada `.huni` sızıntısı **0/38 düğme**;
    iframe'de `.huni` dışında 0.
- Düzenleyici paketi: `dedupe` var/yok 1.211.030 / 1.211.376 B.

## Ortam notları
- `huni-dev` compose'da değil, elle açılmış kap. Ortak dizini görmesi için
  `-v $PWD/huni-bilesenleri:/huni-bilesenleri:ro` ile yeniden açıldı
  (Ç51). Eski kabın arka plan komutu 137 ile bitti (kap silindi, beklenen).
- `bin/kontrol` yine `core/config/reference.php`'yi yeniden yazdı
  (2d48a33'teki gibi). Commit'e alınmadı, HEAD hâline döndürüldü (Ç51).
- Vitest CSS içe aktarmasını varsayılan olarak boş dizgeye çeviriyor, `?raw`
  da dahil → tuzak §32; `huni-bilesenleri/vitest.config.ts`.
