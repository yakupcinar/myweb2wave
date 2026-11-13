# P7 — Panel ile onaylı taslak arasındaki fark (ölçüm, 2026-11-10)

**Soru (kullanıcı):** Sayfalar taslak kadar iyi olmadı. Seçtiğimiz dil / çatı (React + Tailwind v4 + Symfony)
bunu engelliyor mu?

## Yöntem

Taslağın üç sayfası (`Main`, `Kullanicilar`, `Ayarlar`) tuvalden çıkarılıp tek başına HTML olarak yerelde
sunuldu. Panelle aynı tarayıcıda, 1440 genişlikte açıldı ve karşılık gelen öğelerin hesaplanmış stili
(`getComputedStyle`: konum, boyut, yazı, renk, dolgu) yan yana okundu.

## Sonuç: yığın engel değil

Taslak düz HTML + satır içi CSS. Kullandığı her değer (yazı tipi, renk, dolgu, köşe, gölge) Tailwind'de
birebir yazılabiliyor. Ölçülen farklar şunlar:

| Fark | Taslak | Panel | Etkisi |
|---|---|---|---|
| Satır yüksekliği | `normal` (≈1.2) | 1.5 (Tailwind gövde varsayılanı) | Menü öğesi 31 → 35 px, tablo satırı 62 → 68 px; her şey gevşek |
| Menü zemini | #f0f0f3 | #f2f2f7 | Çok az |
| Vurgu | #0071e3 | #0066cc | Bilerek (AA 4.5:1, P6) |
| Form alanları | Çerçeveli, 40 px, yuvarlak | Bazı sayfalarda **hiç stil yok** | Deneyler "Yeni deney" formu: etiket ve değer yapışık, çerçeve yok; "Sürdür / Bitir" düz yazı |
| Sayfa yapısı | Rapor, Uygulama taslaktaki gibi | Henüz eski düzen | P7b / P7c |

Yazı tipi aynı (`-apple-system` → SF), renk değişkenleri aynı, h1 34 px ve tablo hücre dolgusu
(14 px 18 px) aynı.

## Stilsiz form öğeleri (sayım)

Kaynakta `className`'siz `<input>` / `<select>` / `<button>` sayısı (Tailwind'in sıfırlaması bunları
çıplak bırakır):

| Sayfa | Stilsiz öğe |
|---|---|
| Deneyler | input 6, select 1, button 5 |
| Planlar | input 12, select 6 |
| Huniler | input 2, select 3 |
| Webhooklar | input 2 |
| Anahtarlar | input 2 |
| Bağlam | input 1, select 1, textarea 1 |
| Yasal | select 1, textarea 1, button 1 |
| Ayarlar | select 1 |

Sayı düzenli ifadeyle yaklaşık bulundu; bir kısmı başka bir sarmalayıcıdan stil alıyor olabilir. **Düzeltme (P7e, 2026-11-11):** çizilmiş sayfada Huniler ve Planlar'da çıplak alan yoktu; sayım `=>` içindeki `>`'de kesilip `className={değişken}`'i görmüyordu. Asıl fark eski sınıflardı ([p7e-kalan-sayfalar.md](p7e-kalan-sayfalar.md)).
Ekranda ölçülerek doğrulanmalı. Deneyler, Webhooklar ve Anahtarlar ekranda görüldü. Bu sorun P6'dan
değil: P6'dan önceki `stil.css`'te de form öğeleri için temel stil yoktu.

## Kullanıcı soruları (2026-11-10)

- **API anahtarları:** iptal var (`DELETE /panel/api/uygulamalar/{app}/anahtarlar/{id}`), silme yok.
  İptal edilenler listede kalıyor (yerelde 7 anahtarın 6'sı iptal). → Ç130
- **Webhook'lar:** olay seçimi teknik adlarla (`huni.ziyaret`, `odeme.fatura`…) tek satırda onay kutusu.
  Taslakta menüde "Entegrasyonlar" tek başlık (Webhook'lar + API anahtarları + Ürün bağlamı yerine). → Ç131
