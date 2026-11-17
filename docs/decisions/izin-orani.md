# L16 — Reklam ölçümü izin oranı raporu (Ç56 (3))

2026-10-26 · karar `pre-setup.md` A13 ("kabul / reddet / seçmeyen; yalnız sayı").

## Ölçüm (önce)
Çekirdek yalnız kabulü biliyordu (`izin_at`). "Reddet" hiçbir istek
göndermiyordu; önceden reddeden ziyaretçi `izin` alanı olmadan açılıyordu →
ret ile "henüz seçmedi" ayırt edilemiyordu.

## Karar
- `huni_ziyaretleri.izin_red_at` + kısıt `izin_at IS NULL OR izin_red_at IS
  NULL` (göç `Version20261026140000`; tuzak §4 ayrı testle).
- Ziyaret açılışı üç durumlu: `izin: true` kabul, `false` ret, yok = seçmedi.
  Huni kayıtlı tercihi (`localStorage`) olduğu gibi yollar.
- Şeritte ilk "Reddet" → `DELETE /izin` (geri alma ucu; ret anını yazar,
  tıklama verisini zaten tutmaz). Kabul (`POST /izin`) reti siler.
- Panel ana sayfasında "Reklam ölçümü izni" kartı: Kabul etti / Reddetti /
  Seçmedi, sayı · yüzde (seçilen gün aralığı). Kim olduğu yok.
- **Reddedilen:** ayrı "ret" ucu (geri alma ucu aynı işi görüyor; sözleşme
  büyümez) · tarayıcıdan sayaç/analitik (A6: analiz çekirdekte).

## Test
`AtifTest` +2: üç durum ayrı; DELETE reti yazar, sonraki POST reti siler;
kısıt doğrudan. `PanelTest` +1: 2 kabul / 1 ret / 1 seçmeyen → "2 · 50,0 %",
"1 · 25,0 %", "1 · 25,0 %". Huni: ilk Reddet DELETE yollar; kayıtlı "hayir"
ziyaret gövdesinde `izin: false`. Kısıt testi göçten önce sahte yeşildi —
K1 ile ölçüldü.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | test DB'sinde kısıt yok | 🔴 kısıt testi (geri kondu) |
| K2 | `izin:false` ret yazmaz | 🔴 üç durum |
| K3 | Kabul reti silmez | 🔴 üç durum |
| K4 | geri alma ret yazmaz | 🔴 üç durum |
| K5 | seçmeyen = `izin_at IS NULL` | 🔴 kart |
| K6 | ilk Reddet istek yollamaz | 🔴 Reddet testi |
| K7 | kayıtlı "hayir" gitmez | 🔴 önceden reddedilmiş |

## Gerçek istek (yerel çekirdek)
`izin:true / false / yok` → satırlar `(t,f) (f,t) (f,f)`; ölçüm satırları
`utm_source='l16-olcum'` ile silindi.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 232 çekirdek testi / 1.638 doğrulama.
