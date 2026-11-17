# P7-temel — satır yüksekliği, ortak form alanı, Deneyler

**Tarih:** 2026-11-11 · **İstek (kullanıcı):** "Deneylerde yeni deney kısmı tam oturmamış"; her sayfa
taslağa olabildiğince benzesin. Ölçüm: [p7-taslak-farki.md](p7-taslak-farki.md).

## Ne yapıldı

- **Satır yüksekliği** gövdede `normal` (taslakla aynı); Tailwind'in 1.5'i kalktı
  ([stil.css](../../core/panel/src/uygulama/stil.css)). Menü öğesi 35 → 32 px (taslakta 31; ikon 18 px).
- **Ortak form** [ui/Alan.tsx](../../core/panel/src/uygulama/ui/Alan.tsx):
  - `alanSinifi`: 15 px yazı, `kenar-2` çerçeve, 8 px köşe, odakta vurgu.
  - `Satir`: solda etiket (220 px), sağda alan ve yardım; dar ekranda alt alta. Etiket `useId` ile alana
    bağlı.
  - `kutuSinifi`: onay kutusu.
- **Deneyler:**
  - Liste satırları taslak düzeninde: ad, Türkçe durum rozeti (Taslak, Çalışıyor, Durdu, Bitti) ve
    "Yönlendirme · huni · paylar" alt satırı.
  - Düğmeler hap biçiminde: Başlat ana düğme, Bitir kırmızı.
  - "Yeni deney" formu satır düzeninde, yardım metinleriyle.

Diğer sayfaların stilsiz alanları kendi bloklarında bu bileşenle düzelecek: Uygulama P7c'de,
Entegrasyonlar P7d'de, Planlar / Huniler / Yasal kalan sayfalarda.

## Neden böyle

- **Satırlı form, yığılı form değil.** Taslakta Uygulama sayfası bu düzende; Deneyler aynı dili
  kullanınca sayfalar arasında tutarlı.
- **Alan için render prop (`{(id) => <input id={id}/>}`).** Etiket ↔ alan bağı `useId` ile kurulur.
  Reddedilenler:
  - Etiketi sarmalayıcı yapmak: yardım metni de erişilebilir ada girer, `getByLabelText("Deney adı")`
    bulamaz.
  - `cloneElement`: çocuğun türünü gizler.
- **Satır yüksekliği için test yok.** Vitest CSS'i boş dizgeye çevirir (§32), jsdom çizmez. Ölçüm
  tarayıcıda yapıldı: `getComputedStyle(body).lineHeight = normal`, menü öğesi 32 px.

## Kırma denemeleri

| # | Bozulan karar | Sonuç |
|---|---|---|
| T1 | Etiket alana bağlı değil (`htmlFor` yok) | Deneyler.test 2/6 |
| T2 | Alan id almaz | 2/6 |
| T3 | Durum ham ("durdu") | 1/6 |

## Ölçüm

- `bin/kontrol` YEŞİL.
- Tarayıcı (derlenmiş panel, yerel veri): Deneyler listesi "acilis · Durdu" ve "Sürdür / Bitir" hap
  düğmeleriyle çizildi; form satırları 36 px alanlarla, etiketler solda.
