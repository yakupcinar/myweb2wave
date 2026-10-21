# Blok: H4 — adım geçişleri (B4) — 2026-10-21

## Ne yapıldı
Huni adımı değişince yeni adım 220 ms'de belirip 8 px yukarı kayarak
geliyor (`huni-bilesenleri/huni.css`, `@keyframes huni-adim-gir`). Yalnız
CSS: gösterilen kutu `hidden`'dan çıkınca animasyon kendiliğinden baştan
başlıyor. Oynatıcıya tek durum eklendi (`gecti`): ilk geçişten sonra adım
kutularının kabına `data-gecis` koyuyor; animasyon yalnız onun altında.
Geri tuşunda da aynı geçiş. `prefers-reduced-motion: reduce` seçen
kullanıcıya hiç uygulanmıyor. Tailwind ve animasyon kütüphanesi yok.

## Neden böyle

| Karar | Elenen | Neden |
|---|---|---|
| CSS `@keyframes`, `display` değişimiyle yeniden başlıyor | Framer Motion (PLAN'da "CSS yetmezse") | CSS yetti: JS +23 B (bütçe 73.306 / 92.160). Kütüphane on KB'larca olurdu |
| **İlk adımda animasyon yok** (`data-gecis` ilk geçişten sonra) | her gösterilen adımda | İlk ölçüm: ilk adım da opaklık 0'dan başlıyordu → ilk anlamlı boyama 220 ms gecikirdi (H0'dan beri kısaltmaya çalıştığımız şey) |
| `gecti` durumu adımla **aynı çizimde** | effect'te öznitelik koymak | Effect boyamadan sonra koşabilir: yeni adım bir kare tam görünür, sonra 0'dan başlar (titreme) |
| Animasyon yalnız `@media (prefers-reduced-motion: no-preference)` içinde | `reduce` için `animation: none` | Varsayılan hareketsiz; tercih bilinmiyorsa hareket yok |
| Yalnız giriş animasyonu (eski adım anında gizlenir) | çıkış animasyonu | Çıkış için eski adımı bir süre DOM'da görünür tutmak gerekir (JS zamanlayıcı) — CSS'le yapılamaz; ileri/geri yönüne göre farklı kayma da yok (kural 6a; istenirse ayrı karar) |

## Testler
- `huni-bilesenleri/tema.test.ts` +1: animasyon yalnız `no-preference`
  medya sorgusunda ve `.huni [data-gecis] > [data-sira]` için; dışarıda
  `animation` yok; `@keyframes` var. CSS kapsam testi `@media` / `@keyframes`
  içini doğru okuyacak şekilde güncellendi.
- `funnel/src/oynatici/Huni.test.tsx` +2: ilk adımda `data-gecis` yok,
  ileri geçişte var; geri tuşunda var.

## Kırma denemeleri

| # | Deneme | Sonuç |
|---|---|---|
| T1 | animasyon koşulsuz (`@media all`) | 🔴 1 test |
| T2 | ilk adımda da animasyon (`.huni [data-sira]`) | 🔴 1 test |
| T3 | ileri geçişte `gecti` yok | 🔴 1 test |
| T4 | geri tuşunda `gecti` yok | 🔴 1 test |
| T5 | `data-gecis` her zaman | 🔴 1 test |

## Ölçüm
- `bin/kontrol` **EXIT=0, 145 sn**; çekirdek 173, huni 26 (+2), bileşen 19
  (+1), düzenleyici 14. JS gzip 73.306 B (+23).
- Tarayıcı (Chrome, `?huni=fitness`, `matchMedia(no-preference)` = true):
  ilk yükleme `getAnimations()` = [] ve opaklık 1; "Başlayalım" → yalnız
  `huni-adim-gir@1`, 220 ms; `history.back()` → `huni-adim-gir@0`; odak
  başlıkta kaldı. Puck önizlemesinde `[data-sira]` 0, animasyon 0.
- **Ölçüm tuzağı (§34):** ilk denemede 400 ms sonra opaklık hâlâ 0 ve
  animasyon "running" göründü — sekme gizli (`visibilityState=hidden`) ve
  `document.timeline.currentTime` = 0. Ekran görüntüsü bir kare çizdirince
  zaman çizelgesi 18.056 ms'ye ilerledi, animasyon bitti, opaklık 1. Gerçek
  (görünür) sekmede sorun yok; gizli sekmede animasyon ölçümü yalan söyler.
- Hareket azaltma tercihi (`reduce`) tarayıcıda **ölçülmedi** — araçta
  medya emülasyonu yok; davranış CSS testinde (Ç54).

## Ortam notu
`huni-dev` b1 sonrasındaki `docker restart`'tan sonra bir dakika içinde
çıkış 1 ile kapanmıştı (günlükte `SIGTERM` + Vite bağımlılıkları yeniden
iyileştiriyordu); `docker start` ile açıldı. Sebep ölçülmedi (Ç51 (1)).
