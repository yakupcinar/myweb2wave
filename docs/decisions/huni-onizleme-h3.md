# Blok: H3 — önizleme = huni ölçümü (B4) — 2026-10-21

## Ne yapıldı
Tanımdaki bir adımı bileşene çeviren eşleme oynatıcıdan ortak pakete taşındı
(`huni-bilesenleri` `Adim`; `funnel/src/oynatici/Huni.tsx` bunu kullanıyor).
Puck kendi alanlarından aynı bileşenleri **ayrı yoldan** çağırmaya devam
ediyor (`yapilandirma.tsx`). Yeni test `core/editor/src/onizleme.test.tsx`
aynı tanımı iki yoldan çizip DOM'u karşılaştırıyor (tuzak §3: biri
diğerinden hesaplanmıyor). Tarayıcıda aynı taslak panelde (Puck) ve
huninin önizleme bağlantısında açıldı; hesaplanmış stiller birebir aynı.

"Taklit kodu kalkar" maddesi: H1'de Puck'ın satır içi stil kopyası
kalkmıştı. Bilerek kalan iki düzenleyici farkı var: (1) adımlar alt alta
göründüğü için kesikli **ayırıcı kutu** (2) huni sayfasındaki `<body>`'nin
yerine tutan, arka planı bütün önizlemeye yayan **dış kutu**. Test (1)'i
ayıklıyor ve orada olduğunu ayrıca iddia ediyor; (2) tema testinde.

## Neden böyle

| Karar | Elenen | Neden |
|---|---|---|
| Karşılaştırma iki **bağımsız** eşleme arasında: Puck alanları → bileşen ve tanım → `Adim` | Puck'ın da `Adim`'i çağırması | Öyle olsaydı test kendini ölçerdi (§3). Puck alanları tanımdan farklı (`kimlik`, boş dize görsel); çeviri kusuru tam da bu katmanda |
| Test düzenleyici süitinde (`renderToStaticMarkup`) | Tarayıcıda ekran görüntüsü farkı | DOM karşılaştırması kesin ve CI'da koşar; görünüm tarayıcıda hesaplanmış stille bir kez ölçüldü |
| React 19'un sunucu çizimine eklediği `<link rel="preload" as="image">` iki taraftan da ayıklanıyor | Testi ham çıktıyla yazmak | Tarayıcıdaki `createRoot` bunu üretmiyor; ilk koşu bu yüzden kırmızıydı (ürün farkı değil, ölçüldü) |
| Klasik düzenleyici **kalıyor** | H3'te kaldırmak | Yayınla, sürümler ve önizleme bağlantısı orada; kaldırmak ürün kararı (Ç45) — kullanıcıya soruldu |

## Kırma denemeleri

| # | Deneme | Sonuç |
|---|---|---|
| Q1 | Puck soru: seçenek görselleri düşürülür | 🔴 `kiminle` |
| Q2 | Puck tanıtım: kapak her zaman boş | 🔴 `karsilama` |
| Q3 | `Adim` tanıtım: kapak geçirilmez | 🔴 `karsilama` |
| Q4 | Puck kökü özel temayı yok sayar | 🔴 tema |
| Q5 | Puck kökünde `.huni` sınıfı yok | 🔴 tema |
| Q6 | `Adim` ödeme: başlık ↔ fiyat satırı | 🔴 `paywall` |

## Ölçüm
- Düzenleyici 10 test (yeni 5: dört adım + tema), huni 22, bileşen 18,
  çekirdek 169. `bin/kontrol` **EXIT=0, 149 sn** (disk %98, 4,9 GB boş).
  Huni JS gzip 73.283 B (+54 B, `Adim` pakete taşındı).
- Tarayıcı (Chrome; `keepchat/fitness` taslağı): Puck iframe'i ile huni
  önizlemesinde 9 öğe (kök, başlık, metin, ana düğme, kapak, seçenek
  kutusu, seçenek, seçenek görseli, ödeme düğmesi) × 12 hesaplanmış
  özellik (yazı boyu/kalınlık/satır, renkler, köşe, dolgu, hiza, display,
  kenar rengi, dış boşluk) + yazı tipi yığını: **fark 0**.
- Geçerli önizleme bağlantısı tarayıcıda (Ç52 (5) kapandı): şerit var, 6
  adım, ödeme düğmesi kapalı, ziyaret isteği 0.
