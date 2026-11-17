# M9d2b — Ödeme sonrası sayfalar huninin dilinde (2026-11-11)

M9d2'nin ikinci parçası (kullanıcı: "M9d2'ye geç"). M9d2a oynatıcıyı iki dilli yaptı; bu blok Stripe dönüşünden
sonraki sayfaları da huninin diline taşıyor: başarılı, iptal, "Aboneliğimi yönet" ve ödeme sonrası ek ürün.

## Ne yapıldı

- **Dilin taşınması:** oynatıcı huninin dilini temanın yanına oturum belleğine yazıyor
  ([`sonuc-dil.ts`](../../funnel/src/lib/sonuc-dil.ts), `huni-dil`). Sonuç sayfaları dili şu sırayla okuyor:
  oturum belleği → `?dil=` (e-postadaki bağlantıyla gelen için) → tarayıcının dili.
- **Başarılı sayfası:** çekirdekten huninin tanımını zaten alıyor; tanım gelince onun `dil`'i esas.
- **Sabit HTML metinleri** `data-metin="anahtar"` ile işaretli ve sözlükten yazılıyor: başlık, h1, açıklamalar,
  düğmeler, `<title>`, `<html lang>`. JS çalışmazsa Türkçe kalır.
- **JS metinleri:**
  - "Ödemeniz alındı.";
  - portal formunun üç durumu;
  - ek ürünün hataları, "Eklendi", tek seferlik ödeme notu ve tutar (en-US biçimi).
- **Sözlük:** [`metinler.ts`](../../huni-bilesenleri/metinler.ts) 26 anahtarla büyüdü; `useDil()` kancası eklendi.
  Ek ürün preact'in `useContext`'iyle React bağlam tipine bağlanamıyordu, kanca bunu çözüyor.
- **Bulunan kusur:** "Aboneliğimi yönet" sayfasının e-posta alanı M6b2'den beri stilsizdi (`eposta-formu` için hiçbir
  CSS kuralı yok; küçük, tarayıcı çerçeveli alan). İngilizce ekran görüntüsünde görüldü. Etiket, huni bileşenlerinin
  e-posta alanıyla aynı sınıfı (`serbest-yanit`) aldı.

## Neden böyle

- **Oturum belleği:** tema da bu yolla taşınıyor (Mobil görünüm 5); dönüş adresine ve sunucuya ek parametre yazmaz.
- **`?dil=` ve tarayıcı yedeği:** "Aboneliğimi yönet" e-postadaki bağlantıyla da açılıyor, orada oturum belleği yok.
- **`data-metin` işareti.** Sayfalar statik HTML (Vite çok sayfalı); metinleri JS'e taşımak JS'siz görünümü
  boşaltırdı. Her işaretin sözlükte olduğunu bir test ölçüyor (yazım hatası sessizce Türkçe bırakırdı).
- **KeepChat'in İngilizce menü adları doğrulanmadı.** İngilizce yedek metin yolu adsız anlatıyor ("the subscription
  section in settings"). Ç138.

## Kırma denemeleri — 7/7 kırmızı

| Deneme | Sonuç |
|---|---|
| N1 oynatıcı dili belleğe yazmaz | 1 |
| N2 tarayıcı dili bellekten önce | 1 |
| N3 sayfa dili yazılmaz | 1 |
| N4 HTML'de anahtar yazım hatası | 1 (işaret–sözlük testi) |
| N5 hata kodu yanlış metne | 2 |
| N6 ek ürün tutarı tr biçiminde | 2 |
| N7 ödeme sonrası ekran dil bağlamı vermez | 1 |

Betik: `$TMPDIR/…/scratchpad/kir-m9d2b.py`.

## Ölçüm

- **Testler:** huni 137 (yeni: sonuç dili 3, ek ürün İngilizce 1, portal İngilizce 1, oynatıcının belleğe yazması,
  ödeme sonrası ekran İngilizce).
- **Gerçek ekran:** başsız Chrome, telefon boyutu.
  - `/keepchat/iptal/?dil=en` → lang=en, "Payment not completed", "Back to the plan".
  - `/keepchat/abonelik/?dil=en` → "Manage my subscription", "Send link", stilli e-posta alanı.
  - `?dil`'siz iptal sayfası da en: başsız Chrome'un dili en-US, yani tarayıcı yedeği çalışıyor.
  - Huni geliştirme sunucusu §38 yüzünden iki kez yeniden başlatıldı.
- **Ölçülmeyen:** başarılı sayfasının uçtan uca akışı (gerçek Stripe dönüşü). `basarili.ts` modül düzeyinde ve testi
  yok; dil yolu `sonrasiCiz` testi ve `metinleriUygula` testiyle parça parça ölçüldü.
- **Tam kontrol:** YEŞİL ilk koşuda (751 çekirdek testi, huni 137).
