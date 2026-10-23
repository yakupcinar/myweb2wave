# H6c — tarayıcı pikselleri

**Ne yapıldı.** Ödeme başarı sayfası Stripe oturum kimliğini URL'den hemen
siler. Çekirdekten giriş bağlantısı başarıyla döndükten sonra, yalnız
`huni-reklam-izni=evet` ise yapılandırılmış Meta ve TikTok pikselleri dinamik
yüklenir. Tarayıcı olayı `Purchase` / `CompletePayment` ve Stripe oturum
kimliğini `eventID` / `event_id` olarak kullanır; böylece H6b sunucu olaylarıyla
tekilleşebilir. Piksel kimlikleri üretim Docker derlemesine var olan
`META_PIXEL_ID` ve `TIKTOK_PIXEL_ID` ayarlarından aktarılır; boş değer betiği
yüklemez.

## Karar

- Piksel betiklerini tüm hunide baştan yüklemek yerine yalnız doğrulanmış
  satın alma sayfasında yükle: izinsiz ziyaretlerde üçüncü tarafa istek gitmez.
- Olayı sayfa açılır açılmaz değil, ödeme doğrulaması sonucu giriş bağlantısı
  geldikten sonra gönder: URL'ye elle eklenmiş veya henüz ödenmemiş bir Stripe
  oturumu satın alma sayılmaz.
- Meta için standart `Purchase`, TikTok için standart `CompletePayment` kullan.
  TikTok H6b sunucu olayı önce `Purchase` idi; tarayıcı ve sunucu olay adları
  eşleşmediği için H6c ile birlikte `CompletePayment` olarak düzeltildi.
- İki tarafta da Stripe `session_id` değerini olay kimliği olarak kullan.
  Reklam sağlayıcısına erişim anahtarları yalnız sunucuda kalır; tarayıcıya
  yalnız herkese açık piksel kimlikleri derlenir.

## Ölçüm ve doğrulama

- Başarı sayfası yerel huni sunucusundan HTTP **200**, **2.168 B** döndürdü.
- Hedefli tarayıcı pikseli testleri: **3/3**; Astro tip kontrolü: **0 hata**;
  statik derleme: **3 sayfa**.
- Üretim Docker imajı örnek piksel kimlikleriyle başarıyla derlendi. İlk
  deneme ortak `huni-bilesenleri` klasörünün derleme bağlamında olmaması nedeniyle
  düştü; Docker bağlamına klasör eklendi ve sonraki deneme geçti.
- Tam `bin/kontrol`: **yeşil**, çekirdek **202 test / 1.280 doğrulama**.
- Kırma: izin kontrolünü kaldırınca test **1/3 kırmızı**; TikTok `event_id`'yi
  değiştirince tekilleştirme testi **1/3 kırmızı**. Her iki değişiklik geri
  alındı ve kaynakta doğrulandı.
- Gerçek Meta/TikTok hesaplarına test olayı gönderilmedi; hesap kimlikleri ve
  test olay kodları henüz yapılandırılmadığından sağlayıcı panelindeki alım ve
  tekilleştirme ayrıca doğrulanmalı. Hukuk/izin incelemesi (H6a/Ç56) ücretli
  trafiğe geçiş için açık kalır.
