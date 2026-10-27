# web2wave platform yapısı — modüller, düzenleyici, mantık, entegrasyon (2026-10-27)

Kullanıcı: "konu olarak değil yapı olarak bu panellerden, toollardan,
featurelardan bahsediyorum". Amaç: web2wave'i **araç** olarak çözümlemek
ve bizim platformla (MyWeb2Wave) yapısal farkı çıkarmak. **Kod alınmadı**
(kapalı kaynak; kural 12) — yalnız herkese açık belgeler.

## Kaynak ve yöntem
`https://docs.web2wave.com/llms.txt` (belgenin tam dizini) + 21 sayfa ham
metin (`/reference/<ad>.md`): conditional-logic-for-fields-and-screens,
web2wave-advanced-templating-with-js-and-css, js-api-windoww2w-object,
blocks-list, paywall-blocks, plans-prices, experiments, events,
webhook-formats, managing-subscription, project-settings, project-members,
custom-external-payment-system, web2wave-deferred-deeplinks,
pass-subscription-from-web2web-to-app, multicurrency, downsell, email-
sequences, mcp-server, wavy-ai, post-subscription-integration-flow.
Belge = üreticinin beyanı; panel çalıştırılmadı (Cloudflare 403).

## web2wave'in modülleri (panel = "cabinet")

| # | Modül | Yapı (belgeden) |
|---|---|---|
| 1 | **Proje** | Özel alan adı (birden çok), varsayılan paywall, varsayılan tema, yasal veriler (şirket, adres, mağaza kimlikleri → şartlar/gizlilik metnine), robots.txt, müşteri portalı bağlantısı, API anahtarı, webhook hedefleri, dil/ülkeye göre otomatik yönlendirme |
| 2 | **Ekip** | Üye davet; roller **Analyst / Editor / Project Editor / Administrator**; Editor'ü belirli quiz/paywall'larla sınırlama |
| 3 | **Quiz düzenleyici** | Quiz = **ekran listesi**, ekran = **blok listesi** (~60 blok: içerik, soru, yapı, yükleyici). Ekranın kimliği (gezinme) ve analitik kimliği (raporda birleştirme) ayrı. Yayın sürümleri: geçmiş, görsel fark, geri yükleme, sürüm başına önizleme adresi + kullanım istatistiği. Kopyalama. Tema = CSS değişkenleri (`--primary`, `--radius`…) + özel CSS + yazı tipi. Çeviriler. AI ile ekran üretme (Wavy) |
| 4 | **Mantık** | (a) Sonraki ekran: varsayılan / seçeneğe göre / **JS koşulu** `function(answers) → ekran adı`; (b) **ekranı atla** (JS koşulu); (c) **koşullu değer**: HER blok ve ekran özelliği kurala bağlanabilir — kural = koşullar (VE/VEYA) + değer; işleçler `exists, ==, !=, >, <, contains, in, not in…`; koşul özellikleri: cevaplar (ekran kimliği), UTM, dil, ülke/şehir/IP, platform, mobil/masaüstü, viewport, satın alınmış abonelikler, **deney grubu `exp_<id>`** |
| 5 | **Değişkenler** | Metinde `{ad}`, `{utm_tag}`; EJS şablonu; her alana `function(answers)` yazılabilir; hazır değişkenler `subscriptions.*`, `project.*` (mağaza bağlantıları, yasal) |
| 6 | **Özel JS** | JS blok, `window.w2w` arayüzü (kullanıcı özelliği get/set, `logEvent`, olay dinleme, `moveToScreen`/sonraki/önceki, açılır pencere, bildirim), quiz bitmeden dış API'ye gönderme, `AnalyticsManager.on('Subscribe')` |
| 7 | **Paywall düzenleyici** | Quiz'den **ayrı varlık** (quiz → paywall'a yönlenir; cevaba göre farklı paywall). Paywall blokları: fiyat listesi, öde düğmesi, Stripe formu (Checkout/Embedded), Apple Pay hızlı ödeme, ödeme yöntemi seçici, PayPal, Paddle formu, **ödeme koşulları (seçili plana göre dinamik)**, sayaç (yapışık), indirim+sayaç, yorum kaydırıcı, önce/sonra, seçilen hedefler (kişiselleştirme), şartlar onay kutusu, güvenli ödeme rozetleri. Kapatırken **downsell** penceresi, ödeme sonrası **tek tık upsell**, "after pay" sayfası, otomatik para birimi |
| 8 | **Plan ve fiyat** | Fiyat sağlayıcıyla eşlenik (dış kimlik, lookup_key), ek para birimleri, **giriş teklifleri**: Stripe Subscription Schedule ile en çok 2 giriş evresi + deneme günü (eski yol: kupon) |
| 9 | **Ödeme sağlayıcıları** | Stripe (+PayPal, Apple/Google Pay), Paddle, PayPal, Primer (Adyen, Braintree…), Solidgate, Unlimit, FastSpring, Zotlo, Billerix… + **özel dış ödeme sistemi** (kendi checkout'un → API/JS SDK ile abonelik bildir); başarısız ödeme davranışı; ters ibraz (chargeback) panosu |
| 10 | **Kullanıcılar (CRM)** | `user_id` (GUID) ziyaretler arası; **user_properties** (her cevap, UTM, e-posta bir özellik); olay geçmişi; ziyaretler; abonelikler; giriş bağlantısı gönder |
| 11 | **Abonelik yönetimi** | Liste, iptal (hemen / dönem sonunda), iade, duraklat/sürdür, ek ücret, faturalar; **müşteri portalı = bir quiz** (abonelik listesi, iptal düğmesi, iptalde downsell, plan değiştirme düğmesi) |
| 12 | **Deneyler** | İki tür: **yönlendirme** (trafiği quiz/paywall varyantlarına böl) ve **içerik** (aynı huni, `exp_<id>` koşullu değerle). Paylar %100, **yapışık atama**, kesişme açık/kapalı, filtre (ülke, dil, cihaz, UA, IP, UTM), durumlar Draft→Active→Pause/Stop/Success/Fail→Archive (Draft'a dönüş yok), başlayınca kilitlenen ayarlar, sonuç: hacim, adım dönüşümleri, ARPU, dışa aktarma |
| 13 | **Analitik** | Sabit **olay sözlüğü**: `Step N <ekran>`, `Answer <ekran>`, `Quiz finished`, `CompleteRegistration` (paywall görüldü), `Paywall click/price/scroll/popup`, `InitiateCheckout`, `AddPaymentInfo`, `Subscribe/StartTrial/Purchase`, `Lead` (e-posta), iptal/güncelleme, teknik (sayfa kapandı, donma…), sağlayıcı olayları. Raporlar: genel bakış, olaylar, **grafik oluşturucu**, UTM raporu + **Meta reklam hesabından harcama → ROAS**, quiz cevapları (+AI içgörü), ham veriyi dış veritabanına aktarma |
| 14 | **Pikseller** | Meta / TikTok / Snapchat, sunucu tarafı (erişim jetonu), quiz/paywall başına farklı piksel |
| 15 | **Webhook (giden)** | Birden çok hedef ("kart"), teslim görünürlüğü; türler `user_property`, `event`, `subscription`, `CompleteRegistration`; `Webhooks-Token` başlığı, 3 sn zaman aşımı |
| 16 | **Mobil** | SDK (Swift, Kotlin, Flutter, RN, Unity); **ertelenmiş derin bağlantı** `identify()` (cihaz modeli → ekran boyutu, son 48 sa); faturalama entegrasyonu RevenueCat / Adapty / Qonversion / Apphud / Superwall (yetki ver/geri al); quiz/paywall'ı uygulamaya gömme |
| 17 | **E-posta** | İşlem e-postaları + **dizi (drip)**: tetikleyici olay, gecikme, durdurma koşulu, dil sürümleri, AI ile üretim, dış ESP |
| 18 | **Herkese açık API + MCP** | Quiz/paywall/ekran/CSS/sürüm, kullanıcı, abonelik, fiyat, deney, rapor, dışa aktarma uçları; AI istemcileri için MCP sunucusu |

## Bizde karşılığı (2026-10-27)

| # | Modül | MyWeb2Wave | Fark |
|---|---|---|---|
| 1 | Proje | `uygulamalar.yaml` (yapılandırma, panelden değil); huni başına tema/logo | proje ayarları paneli yok, alan adı/yasal veri/yönlendirme yok |
| 2 | Ekip | tek yönetici (`.env`) | kullanıcı + rol yok |
| 3 | Düzenleyici | Puck; **adım = tek tür** (tanıtım, soru tekli/çoklu/metin, yorum, paywall); sürümler + geri yükleme + önizleme + eşzamanlı kayıt imzası; tema 5 değişken | ekran = blok listesi DEĞİL; blok kataloğu 4; yükleyici, e-posta, kaydırıcı… yok |
| 4 | Mantık | seçenek → hedef adım, **yalnız ileri** (A11); URL parametresi → huni kuralı (A8) | koşul motoru yok; atlama yok; koşullu değer yok; çoklu seçimde dallanma yok |
| 5 | Değişken | yok | `{değişken}` yok |
| 6 | Özel JS | **bilinçli yok** (D1: tanım HTML/JS taşımaz) | — (güvenlik kararı) |
| 7 | Paywall | huninin **bir adımı**; tek plan, faydalar, yasal bağlantılar, Stripe Checkout'a yönlenir | ayrı varlık değil; plan seçici, sayaç, downsell/upsell, Apple Pay hızlı ödeme, gömülü form yok |
| 8 | Fiyat | `Fiyatlar` Stripe'tan okur (önbellek), tek aylık plan, para birimi Stripe'ın | fiyat kataloğu tablosu yok; giriş evresi/deneme yok |
| 9 | Ödeme | Stripe (test) | sağlayıcı soyutlaması yok |
| 10 | CRM | **ziyaret** tabanlı (ziyaret, adım, cevap, ödeme); kimlik = ödemeden sonra | ziyaretler arası `user_id` ve özellik deposu yok (P5 planı) |
| 11 | Abonelik | webhook → `subscriptions`; RevenueCat'e giden | panelden iptal/iade/portal yok |
| 12 | Deney | yok (A8 kural ≠ rastgele bölme) | atama servisi yok |
| 13 | Analitik | Postgres'te ziyaret/adım/cevap/ödeme; rapor + izin oranı | olay sözlüğü/olay tablosu yok; grafik yok (P4); ROAS yok |
| 14 | Piksel | Meta + TikTok, onaylı, piksel + sunucu (A12) | huni başına piksel yok |
| 15 | Webhook | yok (yalnız RevenueCat'e giden) | — |
| 16 | Mobil | uygulama anahtarı, tek kullanımlık giriş bağlantısı (A10), bağlama kodu (A5), hak ucu, RevenueCat (A9) | SDK yok; parmak izi eşleme bilinçli yok |
| 17 | E-posta | giriş bağlantısı + kod | dizi yok |
| 18 | API | OpenAPI sözleşmesi (huni + uygulama + panel P1) | yönetim API'si yok |

## Yapısal sonuç — ileride kesilmemek için temel

web2wave'in esnekliği dört **temel** üzerinde duruyor; özellikler (blok,
sayaç, downsell, deney, CRM) bunların üstüne ekleniyor:

1. **Ekran = blok listesi + blok kataloğu (kayıt defteri).** Yeni özellik =
   yeni blok tipi; şema, düzenleyici alanı, oynatıcı bileşeni tek yerde.
   Bizde adım tek tür → her yeni öğe yeni adım türü + sözleşme + çekirdek
   doğrulama + Puck + oynatıcı (5 yerde değişiklik).
2. **Kullanıcı ve özellik deposu.** Her cevap, UTM, konum, cihaz, deney
   grubu, abonelik **aynı ad uzayında özellik**. Mantık, kişiselleştirme,
   deney, CRM, webhook hep bunu okur. Bizde veri ziyaret + ayrı tablolar.
3. **Koşul motoru (bildirimsel).** Tek kural biçimi (koşullar + VE/VEYA +
   değer) üç yerde: sonraki ekran, atla, koşullu değer. Özellik deposunu
   okur. (JS koşulu web2wave'de bunun "kaçış kapısı"; bizde D1 gereği JS
   yok — bildirimsel motor JS'siz aynı gücün çoğunu verir.)
4. **Olay sözlüğü + olay deposu.** Raporlar, deney sonuçları, pikseller,
   webhook'lar ve e-posta dizisi tetikleyicileri aynı olay akışını tüketir.

Bunların üstüne gelen modüller sırayla: fiyat kataloğu + sağlayıcı
soyutlaması → paywall'ı ayrı varlık (çok plan, deneme, downsell) → deney
(atama servisi `exp_<id>` özelliği yazar) → CRM + abonelik işlemleri →
giden webhook + yönetim API'si → ekip/roller → e-posta dizisi.

**Bilinçli farklar (korunacak, değiştirmek ayrı karar):** özel JS yok (D1,
XSS/güven), olasılıksal cihaz eşleme yok (A10: kesin giriş bağlantısı),
dallanma yalnız ileri (A11 — koşul motoru gelirse yeniden değerlendirilir).
