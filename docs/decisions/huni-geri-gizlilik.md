# L6 — Görünür geri düğmesi + okunur gizlilik bağlantısı (Ç61 (4)(6))

2026-10-26 · karar `pre-setup.md` A13.

## Ölçüm (önce)
- Hunide geri dönmenin tek yolu tarayıcı geri tuşuydu (H2'de D-davranışı,
  `popstate`); ekranda düğme yoktu.
- "Gizlilik ayarları" `font-size: 13px`, `padding: 4px 8px` — dokunma
  alanı 44 px'in altında.

## Karar
- `Huni.tsx`: `mevcut > 0` iken ilerleme çubuğunun üstünde "← Geri";
  `history.back()` — tarayıcı geri tuşuyla aynı yol (`popstate`), dallanmada
  da gelinen adıma döner. **Reddedilen:** `setMevcut(mevcut - 1)` (dallanmada
  hiç görülmemiş adıma düşerdi ve geçmişle ayrışırdı).
- `huni.css`: `.izin-ayarlari` `min-height: 44px`, 15 px; `.geri` 44 px,
  15 px, yazısı içerikle hizalı (−12 px kenar = iç boşluk).

## Test
Huni 41 test (+1): ilk adımda yok, ikinci adımda tıklayınca ilk adıma
döner, `history.state` `{adim: 0}`, düğme yine yok. Bileşen 24 (+1):
`huni.css` bloklarında `min-height ≥ 44`, `font-size ≥ 15`.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | ilk adımda da görünür | 🔴 |
| K2 | tıklama bir şey yapmaz | 🔴 |
| K3 | gizlilik yazısı 13 px | 🔴 |
| K4 | gizlilik 40 px | 🔴 |
| K5 | geri düğmesinde 44 px yok | 🔴 |

## Tarayıcı (Chrome, yerel huni + çekirdek)
İlk adımda Geri yok; "Başlayalım" → Geri 70×44 px → tıklayınca "KeepChat'e
hoş geldin", Geri kayboldu. Gizlilik bağlantısı 44 px / 15 px. 500 px
genişlikte: Geri kutusu 4 px, yazısı başlıkla aynı 16 px'te; yatay kaydırma
yok.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0 (JS bütçesi dahil).
