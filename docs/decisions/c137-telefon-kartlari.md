# Ç137 — Huniler, Entegrasyonlar, Planlar telefonda kart (P8; 2026-11-16)

Kullanıcı kararı: "tablolar karta dönsün, rapor kaydırmalı kalsın".

## Ne yapıldı

- Telefonda (`max-md`) kendi içinde yatay kayan beş tablo, Aboneler'deki (P7g, Ç136) gibi karta döndü:
  - **Huniler › hunileri:** ad ve rozetler solda, "Yayında vN" sağda; altında taslak rozeti, düğmeler sola yaslı.
  - **Huniler › kuralları:** sıra no ve adres yan yana, altında "→ Huni …" ve ↑ ↓ Sil.
  - **Entegrasyonlar › Webhook hedefleri:** adres ve durum rozeti üstte; olaylar, teslim ve işlemler altta
    (`max-md:order-1` ile sıra). Açılan teslim günlüğü satırı da `block`.
  - **Entegrasyonlar › API anahtarları:** ad ve izin üstte; önek, oluşturma, son kullanım ve işlem altta. Katlı
    "İptal edilen anahtarlar" tablosu da aynı aileden, o da kart.
  - **Planlar:** ad ve fiyat üstte; anahtar, teklif, ek para birimleri, Stripe fiyatı ve "Fiyatı değiştir" altta.
- Kart sınıfları (`kartSatir`, `kartHucre`, `kartTam`, `kartTablo`) Kullanicilar'dan
  [`ui/Tablo`](../../core/panel/src/uygulama/ui/Tablo.tsx)'ya taşındı; Aboneler aynı dizgeleri kullanıyor, davranışı
  değişmedi.
- Yeni `KartEtiketi`: telefonda sütun başlığı gizli olduğu için çıplak değerin önüne ad koyar ("Yayında", "Önek",
  "Stripe fiyatı"…). Masaüstünde `md:hidden`. Hücre mono yazı tipliyse etiket de mono oluyordu (ekranda görüldü), bu
  yüzden etikete `font-sans` sabitlendi.
- Rapor tabloları ve Deneyler bilerek dokunulmadı (kullanıcı: rapor kaydırmalı kalsın; Deneyler kararda yok →
  Ç137'de açık).

## Neden böyle

- **Tek DOM, CSS ile kart:** gerekçe P7g kaydında (iki kopya ayrışır, testler ve ekran okuyucu her satırı iki kez
  görür). Bedeli de aynı: telefonda sütun başlığı yok, onun yerine `KartEtiketi` var.
- **Sınıflar ortak dosyada:** artık altı tablo aynı kalıbı kullanıyor. Her sayfada ayrı kopya, bir gün birinin
  ayrışmasına yol açardı (kural 7).

## Kırma denemeleri — 4/4 kırmızı (ikinci tur)

Ölçen araç ekran döngüsü (`bin/panel-ekran`, telefon 390, `kaydirilanKaplar`). Vitest'te CSS yok (tuzak §32).

**Birinci tur, 5/5 tutmadı:** denemede yalnız `<Tablo className={kartTablo}>` kaldırıldı. Kayma geri gelmedi,
çünkü kaymayı kaldıran `tbody`/`tr`/`td` sınıfları. Deneme yanlış parçayı bozuyordu, kod değil. Sonuç: `kartTablo`
ölçülen sonucu etkilemiyor; Aboneler'le görünüm birliği için duruyor, ölçülmüş bir karar değil.

**İkinci tur:** dosyanın kart değişikliği tümden geri alındı (değişiklik öncesi yedek).

| Deneme | Sayfa | `kaydirilanKaplar` | Sonuç |
|---|---|---|---|
| K1 Huniler (iki tablo) | huniler | "keepchat hunileri", "keepchat kuralları" | kırmızı |
| K2 Webhook hedefleri | entegrasyonlar | "Hedefler" | kırmızı |
| K3 API anahtarları | entegrasyonlar | "Anahtarlar" | kırmızı |
| K4 Planlar | planlar | "Planlar" | kırmızı |

Geri koyunca dördünde de liste boş. Betik: `$TMPDIR/…/scratchpad/kir-c137b.py`.

## Ölçüm

- **Önce** (telefon): Huniler 2, Entegrasyonlar 2, Planlar 1 kayan tablo.
- **Sonra:** üç sayfada `kaydirilanKaplar` boş, `tasanlar` boş, `yatayKayma` yok; açık ve koyu tema. Aboneler
  değişmedi (yalnız bilerek kayan "Durum" seçicisi).
- **Görülemeyen:** test kullanıcısında webhook hedefi yok, iptal edilmiş anahtar listesi katlı. Bu iki kart
  görüntüde çizilmedi, yalnız boş durumları ölçüldü.
- **Panel:** 202 test geçti. **Tam kontrol:** YEŞİL, 780 çekirdek testi.
