# A4 — Ürün bağlamı (uygulama başına, AI sistem istemine)

2026-10-27 · araştırma `ai-asistan-arastirmasi.md` (FunnelFox proje bağlamı).

## Karar
- `uygulama_baglamlari` (göç `Version20261027130000`): urun ≤1000, kategori
  ≤100, kitle ≤500, ton ≤200, yasaklar ≤1000 (CHECK; `UrunBaglami::ALANLAR`).
- Uçlar (sözleşme): `GET /panel/api/uygulamalar`, `GET/PUT
  /panel/api/uygulamalar/{app}/baglam` (PUT CSRF `panel-api`, kabuğun
  `data-jeton`'u; tamamı yazılır, kırpılır; 403/404/422).
- React sayfası `/panel/baglam` (menü React + Twig düzeninde, kural 7):
  sayaçlı alanlar, açıklama, örnek; Kaydet yalnız değişince.
- HuniAsistani: bağlam doluysa sistem isteminde 2. blok; boş alan gitmez;
  önbellek işareti yalnız son blokta.
- API istemcisi GET dışı her isteğe CSRF başlığı ekler.

## Bulunan hata (test)
Form veri gelmeden boş çiziliyor, `useEffect` sonradan yazılanı eziyordu →
form veri gelince çizilir, başlangıç değeri veriden.

## Test
Çekirdek `PanelBaglamTest` (5) + `PanelAiTest` (+1, bağlamsız tek blok);
panel `Baglam.test.tsx` (3). Kırma K1–K8 kırmızı (jeton, yükleme sırası,
kısmi gövde, bağlam istemde, önbellek işareti, CSRF, uzunluk, DB kısıtı —
kısıt test DB'de kaldırılıp geri kondu).

## Gerçek
Derlenmiş panel önizlemesi (sahte yanıtlar) 1280 px: sayfa ve menü doğru.
Dev: `/panel/baglam` girişsiz 302 → giriş. Gerçek Claude ölçülmedi (anahtar yok).

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 256 çekirdek testi / 2.038 doğrulama.
