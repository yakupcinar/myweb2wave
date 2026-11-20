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

## 2026-11-19 — Gerçek RevenueCat kurulumu (tarayıcıda)

- Proje KeepChat (`1d3080e2`). Stripe'ta (sandbox `myweb2wave sandbox`) RevenueCat uygulamasının kurulumunu
  kullanıcı onayladı; RevenueCat'te "KeepChat (Stripe)" yapılandırması (`app9374f6a228`, Web altında) Claude kurdu.
  Managed Payments kapalı.
- Satın alma izleme ayarları varsayılan bırakıldı, kararla aynı: **sunucudan sunucuya izleme kapalı** (açık olsa
  RevenueCat aynı aboneliği anonim kimlikle ikinci müşteri olarak açardı; kimliği biz kişi UUID'iyle veriyoruz),
  **fatura ödenince** işlem tanınır, **incomplete / past_due / unpaid / paused abonelik reddedilir** (Ç143'ün
  açığını **kapatmıyor** — 2026-11-19 ölçüldü: `paused` Stripe'ın durum değeri, bizim `pause_collection`'lı
  aboneliğimiz `active` kalıyor; § Ç143 ölçümü).
- Ürünler: canlı `planlar` tablosundaki iki fiyat içe aktarıldı — `monthly` → `prod_VTArs3nl8F9i0B` /
  `price_1UbJo1…`, `yearly` → `prod_VZ87RLllPfXiIt` / `price_1UXzqz…`. Stripe CLI artığı üç "myproduct" ve tek
  seferlik koçluk paketi alınmadı. İkisi de var olan **`keepchat_pro`** hakkına bağlandı (yeni `pro` açılmadı:
  App Store ve web aynı hakta buluşmalı; hakta Test Store ürünleri de duruyor).
- **Bulgu — anahtar:** belge (Track External Purchases) `/v1/receipts` için "Stripe app public API key" istiyor;
  yeni (Web altındaki) Stripe yapılandırmasında yalnız **Sandbox API key** var ve altında "harici, API ile
  gönderilen satın alımda kullanılamaz" notu yazıyor. Hangi anahtarın tuttuğu ölçülecek (Ç158).
- **Bulgu — kuyruk boş kalır:** canlıda 3 aktif `keepchat` aboneliği var ama `baglantilar` 0 satır. Aktarım yalnız
  e-postası bu uygulamada bir kişiye bağlı aboneliği kuyruğa alıyor; KeepChat uygulaması kişiyi bağlayana kadar
  anahtar yazılsa da RevenueCat'e hiçbir şey gitmez (Ç158).

### Ölçüm (2026-11-19, akşam)

- **Anahtar tuttu:** kullanıcı Sandbox API key'i panodan okuyan bir `curl` ile `POST /v1/receipts` attı
  (`X-Platform: stripe`, `app_user_id=olcum-20261119`, `fetch_token=sub_1UciiGPfp6LpRKk1MQnFvqFq`). RevenueCat
  Customers › `olcum-20261119` (sandbox verisi): **KeepChat Pro — Active · renews in 1 month**, ürün
  MyWeb2Wave Pro (test) aylık, TRY 349,99. Yani sayfadaki "harici satın alımda kullanılamaz" notuna rağmen bu
  anahtar (`strp_` önekli, 32 karakter) receipts ucunda geçerli. Ç43 kapandı, Ç158 (1) kapandı.
- Anahtar sunucuda `deploy/.env.uretim`'e kullanıcının komutuyla yazıldı (değer sohbete ve depoya girmedi).
  Dağıtım `faf1e0a7a37e`; konteynerde `REVENUECAT_STRIPE_KEEPCHAT` uzunluk 32, önek `strp_`.
  `app:revenuecat:gonder` → `gönderilen=0 başarısız=0`, çıkış 0 — beklenen, çünkü `baglantilar` 0 satır (Ç158 (2)).
- Deneme müşterisi `olcum-20261119` RevenueCat'te duruyor (sandbox). Aynı abonelik ileride kişi UUID'iyle
  gönderilince "Transfer to new App User ID" davranışıyla yeni kimliğe geçer; istenirse panelden silinir.
- Kalan: ~~Ç143 gerçek duraklatmayla~~ → ölçüldü, hak veriliyor (§ Ç143 ölçümü) · KeepChat'in kişiyi bağlaması (ilk gerçek
  aktarım) · canlıya geçişte üretim anahtarı.
- Deneme müşterisi `olcum-20261119` kullanıcı isteğiyle RevenueCat'ten silindi (2026-11-19; Stripe'taki abonelik yerinde).

### Etiket — "Bağlantıyı dene"de `kayitli` (2026-11-19, kullanıcı: "etiketi değiştir")

Canlı TikTok denemesinde pixel + anahtar kayıtlıyken dönüşüm satırı "Ayarlı değil" diyordu: Ç155 kararıyla test
kodu boşken deneme olayı gönderilmez ve sunucu bunu da `ayarsiz` döndürüyordu. Artık ayrı durum **`kayitli`**
(sözleşme `MetaDenemeSonucu.durum`; Meta ve TikTok ortak şema): bilgiler kayıtlı, deneme yapılmadı; rozet
"Kayıtlı" (nötr — doğrulanmış "Bağlı" değil), mesaj "Canlı olaylar gönderiliyor. Bağlantıyı denemek için test olay
kodu girin…". İstek atılmaması (Ç155) aynı. **Kırma 5/5 kırmızı:** K1/K2 sunucu `ayarsiz` döner (PanelTiktokTest,
PanelMetaTest) · K3 sözleşme enum'undan `kayitli` çıkar (yanıt doğrulaması — bu satır kırmadan önce eklendi; yoksa
düşmezdi) · K4/K5 panel `kayitli` dalı yok (Meta.test, Tiktok.test).
- **Canlıda (dağıtım `ebbe30a9f9fd`):** panel › Entegrasyonlar › "Bağlantıyı dene" — TikTok: Dönüşüm **Kayıtlı**
  ("Canlı olaylar gönderiliyor…"), Harcama Ayarlı değil (Marketing API anahtarı yok). Meta: Dönüşüm **Kayıtlı**,
  Harcama Bağlı ("Reklam hesabı: web2quiz (EUR)").

### Ç143 ölçümü (2026-11-19, gece)

- Abonelik `sub_1UciiG…` panelden 1 ay duraklatıldı (M6c: `pause_collection {behavior: void}`, `resumes_at`
  2026-12-19 19:40 UTC). Sunucu DB'si: `status=active`, `duraklatildi=t`.
- Kullanıcı sandbox anahtarıyla `POST /v1/receipts` (`X-Platform: stripe`, kimlik `olcum-c143`) gönderdi. İlk
  deneme 400 döndü; gövdesi görülmediği için gerekçesi bilinmiyor. Aynı istek tekrarlandığında **200** döndü:
  `entitlements.keepchat_pro` aktif, `expires_date` 2026-12-17 (dönem sonu), `auto_resume_date: null`,
  `unsubscribe_detected_at: null`.
- **Sonuç:** RevenueCat duraklatmayı görmüyor ve hak veriyor. "Reject paused" ayarı yalnız Stripe'ın `paused`
  durumunu (ödeme yöntemsiz biten deneme) reddediyor. Bizim hak ucumuz aynı kişiye `paused` diyor; iki kaynak
  ayrışıyor (Ç143'te açık kalan karar).
- Ölçülmedi: dönem sonunda `void` faturadan sonra RevenueCat hakkı uzatıyor mu ("fatura ödenince" ayarına göre
  uzatmaması beklenir). Bunun için 2026-12-17'den sonra aynı müşteriye bakmak ya da Stripe test saatiyle
  denemek gerekiyor.
- Ölçümden sonra (kullanıcı kararı, 2026-11-20): abonelik panelden sürdürüldü (DB `duraklatildi=f`,
  `duraklatma_bitis` boş), RevenueCat sandbox müşterisi `olcum-c143` silindi ("Customer not found"). Dönem sonu
  ölçümü bu yüzden yapılmayacak; gerekirse Stripe test saatiyle.

### Kararlar (2026-11-20, kullanıcı)

- Ç143 → duraklatma RevenueCat'li uygulamada kapalı (`m6c-duraklat.md` § Ç143); dönem sonu ölçümü yapılmayacak.
- Ç158 (2) → KeepChat girişte bağlar, ilk gerçek web satışından önce (KeepChat deposu).
- TikTok harcaması → geliştirici uygulaması onayı beklenir; kod değişmez. Onay gelince anahtarı kullanıcı girer.
- Anahtar değişimi → canlıya açılırken toplu (PLAN "Değişecek anahtarlar").
- İngilizce yasal metinler → **zaten canlıda** (4 belge × en, 2026-11-17 kaydedildi; şartlar tr ile karşılaştırıldı,
  birebir çeviri). Önceki "yazılacak" önerisi eski bilgiydi. Kalan yalnız Ç126 (hukuki gözden geçirme; Ç126 kaydına göre huni yalnız tr'ye — bugün ölçülmedi —
  bağlanıyor).
