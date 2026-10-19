# 5 · Ödeme sistemi

Bu bölüm para akışının **güvenlik ve doğruluk kararlarını** anlatır.
Ölçümler: `stripe-odeme.md`, `huni-paywall.md`, `uygulama-boyutu.md`.

## Akış ve bugünkü durum

| Adım | Ne olur | Bizde |
|---|---|---|
| Reklam | tıklama kimliği + UTM huniye gelir | ❌ (A6, canlıdan önce) |
| Huni | adımlar, paywall | ✅ paywall · ❌ quiz adımları |
| Ödeme | Stripe Checkout, webhook ile kayıt | ✅ test modu |
| Reklam ağına dönüşüm | sunucudan Meta CAPI / TikTok Events API | ❌ (A6) |
| Uygulamaya geçiş | e-posta koduyla bağlama, hak sorgusu | ❌ (sıradaki blok, A5) |

## Ödeme oturumu: istemciye güvenme

- Huni yalnız **uygulama ve plan adını** gönderir. Fiyat kimliği ve dönüş
  adresleri sunucu yapılandırmasından gelir. İstemciden dönüş adresi almak
  açık yönlendirme açığı olurdu; istemciden fiyat almak fiyat değiştirmeye
  kapı açardı.
- Bilinmeyen uygulama/plan → 422, Stripe'a istek gitmez.
- Stripe'a ulaşılamazsa → 502 + günlük; huni ayrı mesaj gösterir.
- CORS yalnız huninin kökeni, yalnız ödeme ucu, yalnız POST (kurulum
  tarifinin varsayılanı her localhost portuna ve her yola açıktı).

## Webhook: gerçeğin kaynağı

| Karar | Neden | Ölçüm |
|---|---|---|
| İmzası doğrulanmayan gövde işlenmez | herkese açık uç abonelik yazıyor | kırma S5 |
| Aynı olay iki kez gelirse bir kez işlenir — **veritabanı kısıtı** asıl kapı | Stripe tekrar gönderir | kırma S6, S10 + gerçek `stripe events resend`: 1 olay, 1 abonelik |
| Durumu yalnız abonelik olayları yazar | "ödeme tamamlandı" ≠ "abonelik aktif" | kırma S8 (eksik test bununla yazıldı) |
| Eski olay yeni durumu ezmez | Stripe sırayı garanti etmiyor | gerçek ödemede olaylar ters sırada ve eşzamanlı geldi, sonuç doğru |
| Uygulaması bilinmeyen olay uygulanmaz | elle tetiklenen abonelik bir uygulamayı açmasın | gerçek `stripe trigger`: olay işlendi, abonelik eklenmedi |

## Erişim kuralı (A5)

`active`, `trialing`, `past_due` erişim verir; `unpaid`, `canceled`,
`incomplete`, `incomplete_expired` vermez. Ödeme bir kez düştüğünde
kullanıcı kartını güncelleyene kadar kilitlenmez. "Aktif mi?" sorusunu
uygulamanın **sunucusu** sorar (uygulama başına gizli anahtar); uygulamanın
içinde sır yok.

## Ödeme sağlayıcısı ve hukuk

- Şirket Türkiye'de; test Stripe (hesap DE'ye kayıtlı); canlı sağlayıcı
  açık (Ç10): Paddle (satıcı adına vergi), iyzico, yurt dışı şirket + Stripe.
- Uluslararası alıcı: AB KDV, cayma hakkı metni, otomatik yenileme
  yasaları, çerez onayı → hukukçu/mali müşavir doğrulaması (Ç12).
- App Store: web'de alınanı uygulamada açmak serbest **ancak** aynı ürün
  IAP'de de satılıyorsa (3.1.3(b)) → tek hak merkezi, iki kanal (Ç29).
- Stripe Adaptive Pricing Türkiye'deki ziyaretçiye TRY + %4 gösteriyor,
  paywall € diyor (Ç19, Ç21: fiyat tek kaynak).

## Sırlar

Gerçek anahtarlar yalnız `core/.env.local` (git dışı, ölçüldü); testler
`.env.test` ile **sahte** Stripe'a (stripe-mock) gider, gerçek anahtarı hiç
görmez. Depo herkese açık → her commit'ten önce anahtar deseni taraması
(bölüm 6).
