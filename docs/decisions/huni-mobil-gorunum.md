# Huni mobil uygulama görünümü (1. parça: görünüm)

2026-10-27 · kullanıcı: "huni tarafını sevmedim, örnek repolara bak".
Yön seçimi kullanıcıda: **mobil uygulama gibi** (sohbet/tek soru HeyForm
tarzı reddedildi).

## Referans (yalnız davranış, kod yok — CLAUDE.md §2.12)
`luispdoesai/openFunnel` (AGPL-3.0) README'si: telefon çerçevesi, simgeli
seçenekler, tek seçimde otomatik ilerleme, alta yapışık düğme, ödemeden
önce yükleme adımı, rozetli plan kartı, tema ön ayarları. `heyform` README'si
görsel ayrıntı vermedi. Kod ve stil dosyası alınmadı; kendi CSS'imiz.

## Ölçüm (önce, tarayıcı)
İnce çerçeveli düz seçenekler; başlık dar ekranda taşıyor
("kullanacaksın?" kesik); ana düğme içerikle birlikte ortada yüzüyor;
ödeme ekranı tek satır fiyat. Tek seçimde otomatik ilerleme **zaten var**
(planlanan 2. parça gereksiz).

## Karar
- Seçenek kartı: yüzey + 1,5 px çizgi + sağda işaret (`.isaret`; tekli
  yuvarlak, çoklu kare — `.secenekler[data-tur]`); seçili ana renkte, onay
  imi CSS'le (`--ana-yazi`).
- Ana düğme `.eylem` içinde; huni oynatıcısında telefonda ekranın altına
  yapışır, içerik kısa olsa da dipte (`main` → `.adimlar` → adım → section
  esnek sütun). **Yalnız `.huni .adimlar .eylem`**: Puck önizlemesinde
  adımlar alt alta, yapışık düğme her birini kaplardı. Masaüstünde (≥768)
  yapışmaz.
- Ödeme: faydalar onaylı liste, fiyat **seçili plan kartında** faydaların
  altında, düğme hemen ardından; ödeme hatası düğmenin üstünde.
- Başlık 28 px, `text-wrap: balance`, `overflow-wrap: anywhere`; metin soluk.
- Türetilen renkler `.huni` üzerinde (`--soluk`, `--yuzey`, `--cizgi`;
  `color-mix` ile temadan) — `:root` tema değişkenleriyle aynı kalır
  (tema.test).
- İzin şeridi sade kart; Kabul/Reddet eşit ağırlık korundu.
- Basma geri bildirimi (ölçek 0,98) yalnız hareket azaltma kapalıyken.
- **Reddedilen:** yapışık düğmeyi bütün `.eylem`'e uygulamak (panel) ·
  `:root`'a yeni değişken (tema sözleşmesi bozulur) · adım sayacı "2/6"
  (karşılama ve ödeme de sayılır, yanıltıcı; çubuk yeterli).

## Test
`bilesenler.test`: sınıf listesi (+eylem, isaret, plan-karti); seçenekte
işaret + `data-tur`; paywall sırası `</ul>` → plan kartı → `.eylem`; yapışık
kural yalnız `.huni .adimlar .eylem`. `Huni.test`: ana düğme
`.adimlar > [data-sira] > section > .eylem`.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | seçenekte işaret yok | 🔴 bileşen |
| K2 | fiyat faydaların üstünde | 🔴 bileşen |
| K3 | ödeme düğmesi `.eylem` dışında | 🔴 bileşen |
| K4 | yapışma kapsamsız (`.huni .eylem`) | 🔴 bileşen |
| K5 | oynatıcıda `.adimlar` yok | 🔴 huni (ilk deneme sed eşleşmedi → KOŞULMADI, yeniden koşuldu) |

## Gerçek (tarayıcı, yerel huni, ~235 px görüntü)
Karşılama: düğme ekranın dibinde. Sorular: kartlar + boş yuvarlak işaret,
dokununca ilerler. Teşekkür → ödeme: plan kartı onaylı, "Abone ol" dipte.
İlk denemede eski stil çizildi — tuzak §38. **Ölçülmedi:** masaüstü
genişliği (tarayıcı penceresi daraltılmış kaldı), Puck önizlemesi (panel
şifresi), karanlık tema.

## İçerik (kullanıcıda, panel)
Ödeme adımının metninde elle yazılmış fiyat duruyor ("Aylık plan · 9,99 € /
ay") — plan kartı ile iki kez görünür; metni sil, faydaları ekle.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0.

## Sıradaki parçalar
3. seçenek simgesi (emoji) — sözleşme + Puck alanı · 4. ödemeden önce
"planın hazırlanıyor" adımı · 5. başarı/iptal sayfaları aynı görünümde.
