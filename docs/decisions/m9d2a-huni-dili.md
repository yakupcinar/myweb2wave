# M9d2a — Huninin dili: oynatıcının sabit metinleri, sayfa dili, fiyat, yasal bağlantılar (2026-11-11)

Kullanıcı kararı (M9d kapsamı): **yönlendirme + huni dili**. M9d1 kuralı kurdu; bu blok İngilizce huninin gerçekten
İngilizce görünmesini sağlıyor. Ödeme sonrası sayfalar (başarılı, iptal, abonelik, ek ürün) M9d2b'de.

## Ne yapıldı

- **Tanım:** huni tanımına isteğe bağlı `dil` (`tr` | `en`, yoksa `tr`). Diller yasal metinlerinkiyle aynı tek
  kaynaktan (`Taslaklar::DILLER`); başka dil kaydedilmez ("dil tr ya da en olmalı").
- **Yasal bağlantılar:** paywall ve izin şeridindeki bağlantılar huninin dilindeki yayında belgelere gider
  (yayındaki ve taslak önizleme uçlarında).
- **Ortak sözlük** [`huni-bilesenleri/metinler.ts`](../../huni-bilesenleri/metinler.ts): kodun koyduğu metinler.
  - Kapsadıkları: yükleniyor ve hata mesajları, Geri, izin şeridi, gizlilik ayarları, ödeme penceresi, downsell,
    e-posta / yanıt etiketleri, puan, güven rozetleri, yasal bağlantı adları, "Önerilen", teklif sayacı.
  - `en` sözlüğü `tr` ile aynı anahtarları taşımak zorunda; tip eksik çeviride derlemeyi kırar.
  - Dil React bağlamıyla verilir (`DilBaglami`), bu yüzden oynatıcı ve panel önizlemesi aynı metni çizer.
- **Fiyat cümleleri** (`funnel/src/lib/fiyat.ts`):
  - İngilizce kalıplar: "7 days free, then €9.99 / month", "Nothing to pay today. First payment in 7 days; the
    subscription renews every month at €9.99.", tekil "1 day".
  - Sayı biçimi dile göre (`tr-TR` €9,99 / `en-US` €9.99).
- **Sayfa:** `<html lang>` huninin dilinde.
- **Tanım gelmeden:** yükleniyor ve "sayfa yüklenemedi" metinleri `?dil=` ya da tarayıcının dilinde; huninin dili
  henüz bilinmiyor.
- **Panel düzenleyicisi:** kök alanında "Dil (sabit metinler ve yasal bağlantılar)": Türkçe / English. Önizleme
  seçilen dilde çizilir. `tr` tanıma yazılmaz, eski tanımlar aynen kalır.

## Neden böyle

- **Yalnız sabit metinler sözlükte.** Huninin kendi metnini (başlık, soru, paywall metni, düğme) yazar yazıyor;
  çeviri ayrı huni (M9d1, web2wave modeli). Gerçek ekranda "Abone ol" yazarın Türkçesinde kaldı, fiyat ve koşul
  İngilizce oldu: bölünme bu.
- **Önizleme uyarısı Türkçe kaldı.** "Önizleme — taslak; hiçbir şey kaydedilmez" yalnız panelden bakan yöneticiye
  görünüyor.
- **Ölçüldü:** kapsam kararı öncesi oynatıcıda 34+ sabit Türkçe metin; envanter `scratchpad/metinler.txt` (yorum ve
  yol dışı). Ödeme sonrası sayfalar envanterde ayrı (M9d2b).
- **Reddedilen: i18n kitaplığı** (i18next…). İki dil ve yaklaşık 40 anahtar için bağımlılık, yükleme ve
  çoğul/biçim kuralları fazla. Tipli tek dosya eksik çeviriyi derlemede yakalıyor.
- **Reddedilen: her dil için ayrı fiyat.ts.** Biçim mantığı (bölünmez boşluk, teklif sırası) tek yerde kalsın.

## Kırma denemeleri — 10/10 kırmızı

| Deneme | Sonuç |
|---|---|
| L1 tanımda dil denetimi yok | HuniTest 1 |
| L2 yasal bağlantı hep tr | HuniTest 1 |
| L3 en sözlükte "Kabul et" | Huni.test 1 |
| L4 dil bağlamı hep tr | 1 |
| L5 `<html lang>` yazılmaz | 1 |
| L6 en sayı biçimi tr-TR | 3 |
| L7 tekil "1 day" yok | 1 |
| L8 tanımdan önce tarayıcı dili yok | 1 |
| L9 panel `en`'i tanıma yazmaz | donustur.test 1 |
| L10 panel `tr`'yi de yazar | 28 (eski tanımların gidiş-dönüşü bozulur) |

Betik: `$TMPDIR/…/scratchpad/kir-m9d2a.py`.

## Ölçüm

- **Testler:**
  - Çekirdek: HuniTest +1 (İngilizce huni → `gizlilik/en`, önizleme de; `de` reddedilir).
  - Huni: fiyat +2, oynatıcı +2.
  - Panel: donustur +1.
  - Var olan yükleme testlerine tarayıcı dili açıkça Türkçe verildi: happy-dom `en-US`.
- **Gerçek ekran:**
  - AI araçlarıyla geçici `dil-deneme` (genel kopyası, `dil: en`) taslağı açıldı.
  - `ekran_goruntusu` paywall'da "€9.99 / month" ve "€9.99 today; the subscription renews every month at €9.99."
    gösterdi; yazarın metni Türkçe kaldı.
  - Huni sonra silindi (sürüm 0, ziyaret 0).
  - Huni geliştirme sunucusu §38 yüzünden yeniden başlatıldı.
- **Tam kontrol:**
  - İlk koşu KIRMIZI, testler 751/751 yeşildi; kırmızılar:
    - `core-bicim` ve `bilesen-bicim`: biçimlendirici çalıştırılmamıştı;
    - `core-stan`: testte `jsonYanit()['tanim']` karışık tipteydi ve yanıt iki kez okunuyordu;
    - `belge-baglanti`: §58.
  - Hepsi düzeltildi; ikinci koşu YEŞİL (751 çekirdek testi).
