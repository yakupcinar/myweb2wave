# Blok: Stripe → RevenueCat aktarımı (A9) — 2026-10-20

## Ne yapıldı
Hak kaynağı RevenueCat (A9, kullanıcı kararı). Çekirdek, Stripe abonelik
olayı işlenirken (`checkout.session.completed` e-postayı bağladığında ve her
abonelik durum olayında) aboneliğin e-postası bu uygulamada bir **kişiye
bağlıysa** RevenueCat giden kutusuna (`revenuecat_gidenler`) aynı işlemde
satır yazar; e-posta koduyla bağlama başarılı olunca o kişinin bu
uygulamadaki bütün abonelikleri de kuyruğa girer. İşlem kapandıktan sonra
`POST {REVENUECAT_API_BASE}/v1/receipts` (`Authorization: Bearer <Stripe
uygulaması açık anahtarı>`, `X-Platform: stripe`, gövde `app_user_id` = kişi
UUID'i, `fetch_token` = `sub_…`) denenir. Başarısız satır bekler (deneme,
son hata); `app:revenuecat:gonder` (sunucuda 5 dakikada bir cron) yeniden
dener, en çok 10 deneme. Uygulamanın anahtarı boşsa aktarım kapalı (hiçbir
şey kuyruğa girmez). Kılavuza (`uygulama-entegrasyonu.md` §4b) uygulamanın
RevenueCat SDK'sında `logIn(kisi)` kullanacağı yazıldı.

## Neden böyle
- **A9:** IAP doğrulaması, Apple bildirimleri, iade, web + App Store tek hak
  — RevenueCat'in işi; Ç29'daki "kendimiz yazarız" kalktı.
- **Giden kutusu, doğrudan çağrı değil:** RevenueCat'e webhook işleminin
  içinden gitseydik kesintisi Stripe kaydımızı geri alırdı (Stripe yeniden
  dener ama abonelik durumumuz da gecikir). Satır aynı işlemde yazılır →
  kaybolmaz; gönderim sonra.
- **Kullanıcı kimliği = kişi UUID'i:** web alıcısı ödeme anında uygulamada
  giriş yapmamış olabilir; bağlama zaten kalıcı kişi kimliği üretiyor.
  Reddedilen: satın almada rastgele RevenueCat kimliği + sonra takma ad.
- **Her durum değişikliği yeniden gönderilir:** RevenueCat belgesine göre
  Stripe iptali kendiliğinden ~2 saate kadar gecikir, yeniden gönderim
  hemen yansıtır.

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| R1 | e-postayı bağlayan olay kuyruğa almıyor | 🔴 3 test |
| R2 | durum olayı kuyruğa almıyor | 🔴 `testDurumDegisikligiYenidenGonderilir` |
| R3 | bağlama sonrası kuyruk yok | 🔴 `testBaglamaSonrasiOncekiAbonelikGider` |
| R5 | `X-Platform` yanlış | 🔴 |
| R6 | her yanıt başarılı sayılıyor | 🔴 `testRevenueCatHataVerirse…` |
| R7 | azami deneme sınırı yok | 🔴 `testAzamiDenemeyiAsan…` |
| R8 | anahtar boşken de kuyruğa alıyor | 🔴 `testAnahtarYoksa…` |
| R9 | webhook sonrası gönderim yok | 🔴 3 test |
| R10 | ağ hatası yakalanmıyor | 🔴 `testAgHatasiDaStripeKaydiniDurdurmaz` |

## Ölçüm
- `bin/kontrol` YEŞİL: 126 test (öncesi 117). Testte HTTP istemcisi
  `framework.http_client.mock_response_factory` ile sahte — ağa çıkmaz,
  istekler (adres, başlık, gövde) kaydedilir.
- **İlk test beklentim yanlıştı:** "iki olay = iki gönderim" yazmıştım; kod
  bir gönderim yaptı. Doğru olan kod: Stripe'ın gerçek sırasında (huni-paywall
  E1'de ölçülmüştü) `subscription.created` e-postadan ÖNCE gelir, o anda
  kişi bilinmez; e-postayı getiren ödeme olayıyla tek gönderim. Beklenti
  düzeltildi, iptal olayı için ayrı test eklendi.
- **Bulgu (yazarken):** sunucu cron satırı compose'un zorunlu `APP_VERSION`'ı
  yüzünden düşerdi (yedek.sh'te ölçülen tuzağın aynısı) → `APP_VERSION=cron`.
- **Gerçek RevenueCat'e istek ATILMADI** — RevenueCat projesi, Stripe
  bağlantısı ve anahtar kullanıcıda (Ç43).

## Açık
Ç43 (gerçek RevenueCat ölçümü; Offering ürün kimliği eşlemesi; KeepChat
iOS'ta `logIn(kisi)` — KeepChat oturumunda) · Ç17 (kuyruk Messenger değil,
tablo + cron).
