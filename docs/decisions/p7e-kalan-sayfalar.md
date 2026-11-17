# P7e — Kalan sayfalar: Yasal, Huniler, Planlar (2026-11-11)

## Ne yapıldı

- **Yasal metinler**, Uygulama sayfasının diline geçti (880 px):
  - Uyarı notu turuncu zeminde.
  - **Şirket bilgileri** bölümü: solda etiketli satırlar, Kaydet sağ altta.
  - **Metinler** bölümü: belge başına kart. Başlıkta dil seçici var. Altında "Yayında" ya da "Taslak — yayında değil"
    rozeti ve yayın adresi var. Metin alanı ortak stilde, "Kaydet ve yayınla" sağda.
- **Huniler ve Planlar**:
  - Satır içi ekleme formları (huni aç, kural ekle, plan aç, fiyat değiştir, teklif, ek ürün) yan yana dizilimini
    korudu.
  - Alanları ortak stile geçti: yeni `satirIciAlanSinifi`. `alanSinifi` ile aynı görünüm; yalnız `w-full` yok, çünkü
    çağıranın `w-40` gibi genişliğiyle çakışırdı.
  - Etiketler kısa form diline (`kisaEtiketSinifi`, 13 px, soluk) geçti.
  - Huniler'de kaldırma onayı artık dolu kırmızı, Vazgeç gri (P7a kuralı).
- **Ortak denetim** [`ui/stilDenetimi.ts`](../../core/panel/src/uygulama/ui/stilDenetimi.ts): çizilmiş sayfada her
  giriş, seçim ve metin alanı ortak stili (`border-kenar-2`, kendisinde ya da sarmalayıcısında) taşımalı, her kutu da
  `accent-vurgu` almalı. Sekiz sayfa testine eklendi: Aboneler, Deneyler, Huniler, Planlar, Uygulama, Yasal,
  Entegrasyonlar'ın üç bölümü.

## Neden böyle

- **Önce ölçüldü, tahmin yanlış çıktı.** P7 farkında (p7-taslak-farki.md) kaynakta düzenli ifadeyle Planlar'da 18,
  Huniler'de 5 "stilsiz" öğe sayılmıştı. Çizilmiş sayfada ilk denetim (yalnız "çerçeve var mı") ikisini de yeşil
  buldu. O sayım `onChange={(e) => …}` içindeki `>`'de kesilip `className={girdi}`'yi görmüyordu. Asıl fark başkaydı:
  alanlar sayfaya özgü eski sınıflardaydı (`border-kenar bg-zemin`). Denetim "ortak stil" diye sıkılaştırılınca
  Huniler'de 5, Planlar'da 17 alan kırmızı çıktı. Bunlar düzeltildi.
- **Satır içi formlar satır düzenine (Satir) çevrilmedi.** Tablonun altındaki kısa ekleme formunu solda etiketli
  satırlara açmak sayfayı uzatırdı; taslakta bu sayfalar yok. Görünümü (alan, etiket, düğme) ortak yapmak yetti.
- **Denetim sınıf adına bakıyor, hesaplanmış stile değil.** Vitest'te CSS boş (tuzak §32), hesaplanmış stil
  ölçülemez. Ortak sınıfın varlığı, "bu alan ortak bileşenden mi" sorusunun ölçülebilir karşılığı.
- **Ölçülmeyen görsel kararlar:** Huniler'deki onay düğmesinin rengi, Yasal'daki rozet ve not zemini. Bunları
  ancak tarayıcıda bakmak ölçer.

## Kırma denemeleri — 7/7 kırmızı

| Deneme | Sonuç |
|---|---|
| E1 ortak stilin rengi eskiye (`border-kenar`) | 8 sayfa testi düştü |
| E2 Huniler eski sınıf | 1 (Huniler) |
| E3 Planlar eski sınıf | 1 (Planlar) |
| E4 Yasal veri alanı sınıfsız | 1 (Yasal) |
| E5 Yasal metin alanı eski sınıf | 1 (Yasal) |
| E6 kutu vurgu rengi yok | 3 (Webhook'lar, Anahtarlar, Bağlam; üçü de Entegrasyonlar'ı çizer) |
| E7 şema sarmalayıcısı çerçevesiz | 1 (Uygulama) |

Betik: `$TMPDIR/…/scratchpad/kir-p7e.py`.

## Ölçüm

- **Panel testleri:** 183 (8'i yeni denetim). Huniler'in 5 ve Planlar'ın 17 alanı düzeltmeden önce kırmızıydı.
- **Tarayıcıda: KOŞULMADI.** Chrome eklentisi bağlı değil. P7b–P7d ile birlikte bekliyor.
