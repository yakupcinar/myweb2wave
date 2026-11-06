# G5 — tanıtım adımında görsel düzeni (2026-11-02)

Kullanıcı: "planda ne yazıyorsa ona göre devam et" + "loop'a al, benden karar bekleme" (2026-11-02). G1 bulgusu: adımlar seyrek, ekranın büyük kısmı boş; adım başına görünüm çeşidi boşluğu doldurur. Plan notu: "T1 blok kataloğuyla çakışır".

## Karar (kullanıcı onayı bekliyor — Ç77)
- **Kapsam küçük tutuldu:** yalnız görseli olan **tanıtım** adımına `duzen` (`ust` · `kahraman` · `tamEkran`); yoksa `ust` = bugünkü görünüm, DOM'da işaret yok. Blok kataloğu (T1) gelince bu alan bloğun ayarı olarak taşınır; şimdiden genel "adım düzeni" kurmak T1'i önden tasarlamak olurdu.
- **Doğrulama (`Tanim.php`):** bilinen değer; `kahraman`/`tamEkran` görsel ister ("duzen kahraman görsel ister"); başka adım türünde alan kabul edilmez (var olan alan denetimi).
- **Çizim:** `<section data-duzen>`; `kahraman` → görsel 46dvh, kırpılır (`object-fit: cover`). `tamEkran` → görsel bütün adımı kaplar, üstünde **sabit karartma** (alttan koyu), metin beyaz — okunurluk temadan bağımsız, çünkü görselin içeriği bilinmez.
- **Puck:** tanıtımda "Görsel düzeni" (Üstte / Büyük / Tam ekran); `ust` tanıma yazılmaz.
- **AI:** yönergeye bir cümle ("görsel varsa kahraman/tamEkran boş ekranı doldurur"); alanın biçimi araç şemasında sözleşmeden.
- Reddedilen: soru/yorum/ödeme adımlarına da düzen (görselleri yok ya da seçenek görseli — G4'ün işi) · tam ekranda tema metin rengini kullanmak (beyaz görselde koyu metin, koyu görselde açık metin gerekir; sabit karartma + beyaz her durumda okunur) · görselin parlaklığını ölçüp karartmayı ayarlamak (G3'teki gerekçe).

## Ölçülen kusur ve düzeltme
**En kötü durum ölçümü:** bembeyaz görsel (1080×1920, içerik özeti kimlik, ölçümden sonra veritabanından ve diskten silindi), metin saydam yapılıp başlık/açıklama kutusunun altındaki **en açık** piksel okundu:
- İlk sürüm: başlık **2,58:1** (en az 4,5) — başlık ekranın %38'inde, karartmanın zayıf yerinde. Sebep: başlıktaki `margin-top: auto` ile düğme çubuğundaki `margin-top: auto` boşluğu bölüşüyordu.
- Düzeltme: tam ekranda düğme çubuğu `margin-top: 0` → başlık %68'e indi: başlık **13,97:1**, açıklama **14,74:1**. CSS bekçisi testi eklendi.

## Ölçüm
- Testler: `HuniTest` (geçerli düzenler, görselsiz `ust`; 4 hata durumu) · `bilesenler.test.tsx` (`ust`/yoksa işaret yok, işaretler, `Adim` düzeni aktarır, CSS kuralları + bekçi) · `donustur.test.ts` (gidiş-dönüş, `ust` yazılmaz).
- Kırma 8/8 kırmızı: K1 alan denetimine takılır · K2 görselsiz kahraman geçer · K3 bilinmeyen değer geçer · **K4 `ust` de işaret yazar — ilk denemede tutmadı: test aynı bileşenin iki çıktısını kıyaslıyordu (§3) → "işaret hiç yok" diye bağımsız yazıldı, K4b kırmızı** · K5 işaret hiç yok · K6 düğme boşluğu bekçisi · K7 Puck düzeni okumaz · **K8 `Adim` düzeni aktarmaz — ilk denemede tutmadı: test yoktu → `Adim` testi eklendi, K8b kırmızı**.
- Gerçek sistem (KeepChat taslağı yedeklendi → **aynen geri yüklendi**, tanım + imza eşit): tam ekran (beyaz görsel, ölçüm yukarıda), kahraman (koyu dokulu görsel) çekildi.
- CI: koşu 41395389264, SHA 09a2db7, `KONTROL: YEŞİL`, 294 test.
- `bin/kontrol` yeşil (294 çekirdek testi). Bir ara tip hatası: `Adim`'in yerel tanım tipi `duzen`'i bilmiyordu, `exactOptionalPropertyTypes` `undefined` istedi.
- **Ölçülmedi:** Puck'ta alanın görsel kontrolü; gerçek iPhone'da `dvh` ve yapışık düğmenin tam ekran içinde davranışı.
