# L20 — huni çalışma zamanı Preact (compat) (Ç50 (2)) (2026-11-02, döngü)

Plan: "Ç50 Preact denemesi (ölç; iyiyse geç)". Döngü kararı (kullanıcı 2026-11-02: "benden karar bekleme"): ölçüm iyi → geçildi; onay Ç81'de.

## Ölçüm → karar
- **Boyut (derleme, gzip -9, `dist/_astro/*.js`):** React **77 371 B** (istemci çalışma zamanı 65 564 B) → Preact **19 708 B** (preact 4 633 B, signals 2 997 B) — **−%75**.
- **Görüntü:** aynı üç adım (tanıtım, soru, ödeme) ekran kabında önce React, sonra Preact sunulurken çekildi → **piksel farkı 0** (en büyük fark 0). İlk karşılaştırma geçersizdi: bekleme "react" metnini arıyordu, "@astrojs/p**react**" de içeriyor — sunucu React'e dönmeden çekmişti (Preact/Preact = 0); tam kalıpla (`@astrojs/react/`) yeniden yapıldı.
- **Davranış (gerçek tarayıcı, Preact):** ileri → seçenek → geri doğru adımlar; normalde `huni-adim-ileri` + çubuk geçişi, geri `geri`; hareket azaltmada hiç animasyon yok.
- **Testler Preact'le:** huni testleri önce React'le koşuyordu (üretimle farklı çalışma zamanı). `vitest.config.ts` takma adları (`react` → `preact/compat` …) + `@testing-library/preact` 3.2.4 (MIT). Ara bulgu: `@testing-library/react` `react-dom`'u `require` ile yükleyip takma adı atlıyordu — 56 test "eski React öğesi" hatası; inline etmek yetmedi (ölçüldü) → Preact karşılığı. **73/73 geçti.**

## Karar
- `@astrojs/preact` 6.1.0 (`compat: true`, 2026-10-15) + `preact` 11.0.1 (2026-10-08) — iki hafta kuralı ✓; `@astrojs/react`, `react`, `react-dom`, `@testing-library/react` hunide kaldırıldı. Sürümler komşular gibi tam sabit.
- **Bileşenler React API'siyle yazılmaya devam eder** (`huni-bilesenleri`, `Huni.tsx` `react`'ten içe aktarır): panelde (Puck) gerçek React, hunide derlemede Preact'e eşlenir — tek kaynak (B4) korunur.
- **Bütçe** `funnel-butce` 90 KB → **30 KB** (React geri gelirse ya da büyük bağımlılık eklenirse kırmızı). Kırma: sınır 19 000 B → kırmızı (19 708), 30 720 → yeşil.
- Reddedilen: bileşenleri Preact API'sine çevirmek (panel/Puck React — iki dil, B4'ün tek kaynağı bozulur) · React'te kalıp bölmek (`lazy`) — ilk adım yine React çalışma zamanını ister.
- **Ölçülmedi:** yavaş mobil ağda ilk anlamlı boyama (Ç50 (1) hâlâ açık; Lighthouse/ağ kısıtı yok) · gerçek iPhone/Android'de Preact.
- `bin/kontrol` yeşil (313 çekirdek testi; huni 73 Preact'le; `JS gzip 19708 B`).
