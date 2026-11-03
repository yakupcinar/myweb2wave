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

## Ek 2026-10-27 — quiz builder: araç + AI karşılaştırması

Kullanıcı: "web2wave quiz builder'ı hem AI hem araç açısından nasıl yönetmiş, aynı seviyede miyiz, değilsek fark ne". Kaynak: `docs.web2wave.com/reference/{mcp-server,wavy-ai,blocks-list}.md` (2026-10-27 okundu; üretici beyanı, panel çalıştırılmadı). Kod alınmadı (kural 12).

| Alan | web2wave | MyWeb2Wave | Seviye |
|---|---|---|---|
| Yapı | ekran = blok listesi; belgede ~72 blok (içerik 16, soru 31, yapı 19, yükleyici 6); MCP şeması 410+ blok tipi | adım = tek tür, 6 tür (tanıtım, soru tekli/çoklu/metin, yorum, paywall) | çok geride (temel 1 / T1) |
| Stil | global tema CSS değişkenleri + **blok başına Styles sekmesi** (renk, tipografi, boşluk, kenarlık, kap) "global temanın üstüne"; 1700+ CSS değişkeni; özel CSS | huni başına 5 alan (3 renk, 4 yazı tipi yığını, köşe) + logo; blok başına yok; özel CSS bilinçli yok (D1) | çok geride (G1–G5); özel CSS farkı bilinçli |
| Mantık | koşul motoru (sonraki ekran, atla, koşullu değer), JS koşulu | seçenek → ileri hedef (A11) | geride (T3) |
| Sürüm/önizleme | geçmiş, görsel fark, geri yükleme, sürüm başına önizleme + istatistik | geçmiş, geri yükleme, sürüm başına ziyaret, imzalı önizleme (taslak + yayın, Ç70) | yakın (görsel fark yok) |
| Panel AI | Wavy AI (beta): açık quiz'e brief'ten ekran üretir/yeniden yazar; stil "görsel geçiş" isteğiyle; girdi yalnız metin; yayın insanda | A2/A3 hazır ama **kapalı** (anahtar yok); ürün bağlamı (A4); tema yönergesi (Ç71 a); yalnız taslak | kavram aynı, kullanımda değil |
| MCP araçları | 70+; quiz kur+yayınla, **`web2wave_render_quiz_screen` (ekran görüntüsü)**, `validate_quiz_json`, `web2wave_set_quiz_theme_variables`, `web2wave_patch_block_css_variables`, `web2wave_generate_theme_palette`, `plan_quiz_from_design`, `web2wave_crawl_funnel` (adresten kopya), analitik/deney/abonelik | 5: listele, oku, doğrula, taslağa yaz, önizleme adresi | çok geride |
| MCP istem senaryoları | design-to-quiz, **iterate-quiz-design (render → tasarımla fark → yama → yeniden render)**, theme-from-brand, clone-funnel-from-url … | yok (talimat metni var) | yok |
| Güvenlik | yıkıcı işlemler barındırılan uçta kapalı; anahtar = tek proje | yayın/silme aracı yok; anahtar = tek uygulama | aynı |

**Farkın nedenleri (önem sırası):**
1. **AI kör.** web2wave'de model çıktısını ekran görüntüsüyle görüyor ve tasarımla karşılaştırıp düzeltiyor (iterate senaryosu). Bizde AI yalnız JSON yazıyor; ekranın nasıl göründüğünü hiç görmüyor → görünümü iyileştirmesi için geri bildirim yok. "Hepsi aynı görünüyor" şikâyetinin ikinci kökü bu (birincisi Ç71: dar yüzey).
2. **AI'ın tavanı sözleşme.** AI yalnız `HuniTanimi`'nde olanı üretebilir: 6 tür + 5 tema alanı. web2wave'de 410+ blok tipi + 1700+ değişken. Model zekâsı farkı değil, **ifade alanı** farkı. T1 (blok kataloğu) ve G1–G5 bunu çözer; araç şeması sözleşmeden üretildiği için AI kendiliğinden büyür.
3. **Bilinçli farklar** (korunur): özel CSS/JS yok (D1, XSS), dış font yok (KVKK). web2wave'in kaçış kapıları bizde kapalı → aynı çeşitliliği yapılandırılmış alanlarla vermek gerekiyor (daha çok iş, daha güvenli).
4. **Süre.** web2wave yıllardır; bu depo 2026-10-19'da başladı.

Yakın olduğumuz yerler: sürüm/geri yükleme/önizleme, yalnız-taslak güvenlik modeli, sunucuda tam doğrulama + Türkçe hata ile AI düzeltme döngüsü, ürün bağlamı. Öneri ve sıra: Ç72.

## Ek 2026-11-03 — quiz builder sonrası: kalan modüller ve sıra

Kullanıcı: "ana özellik eklendi ve test edildi (sunucu hazır değil, dış işler sonra); web2wave'in başka özelliklerine bakıp sırayla eklemek için plan yap". Kaynak: `docs.web2wave.com/llms.txt` (2026-11-03, 392 satır) + yeni/değişen sayfalar `support-desk`, `ltv-predictions`, `meta-ad-account-spend-and-roas`, `web2wave-js-sdk`, `multicurrency-auto-currency-switching`, `managing-subscription`, `experiments`, `plans-prices`, `email-sequences`. Üretici beyanı; kod alınmadı (kural 12).

**10-27'den beri web2wave'de yeni:** Support Desk (gelen kutusu + kullanıcı kartı + AI yanıt taslağı + otomatik yanıt; ayrı ücretli), LTV tahmini (kohort başına 1/3/6/9/12 ay; az veride platform geneli anonim veri), Meta reklam hesabı → harcama + ROAS sütunu (TikTok/Google "yakında"), dış siteler için JS SDK (dış ödeme sayfasından CAPI olayı), rol `Support Manager`.

**Bizde durum (10-27 tablosuna göre değişen):** temel 1–4 (T1 blok kataloğu, T2 özellik deposu, T3 koşul motoru, T4 olay deposu) ✅; panel React + grafik (P4) + kullanıcılar listesi (P5) ✅; MCP (A5a) ✅. Quiz builder tarafında yapısal açık kalmadı; kalan fark **para ve büyüme modülleri**.

### Kapsam dışı (bilinçli — değiştirmek ayrı karar)

| web2wave | Neden yok |
|---|---|
| Çok ödeme sağlayıcısı (Paddle, Primer, Solidgate…) | Ç10: canlıda da Stripe |
| Özel JS / `window.w2w` / dış JS SDK | D1 (tanım JS taşımaz); ödeme dış sitede değil, Stripe webhook'u zaten çekirdekte |
| Mobil SDK + olasılıksal derin bağlantı | A10: kesin giriş bağlantısı |
| LTV'de platform geneli veri | A1 tek kiracı — paylaşılan veri yok; yalnız kendi kohortlarımız (M5c) |
| Support Desk'in faturalı ürün hâli, çok proje, ekip davet akışı | A1: tek şirket; ekip gerekirse M9 |

### Sıra — her biri ayrı `/blok`, bağımlılık + gelire etki

| # | Modül | İçerik | Bağımlı | Dış girdi |
|---|---|---|---|---|
| **M1** | Fiyat kataloğu | `fiyatlar` tablosu (uygulama başına çok plan: hafta/ay/yıl), Stripe ile eşleme (lookup_key), **giriş teklifi + deneme** (Subscription Schedule, en çok 2 evre), **çok para birimi** (Stripe `currency_options`; ülkeden seçim, ziyaretçi seçmez) | L10 | yok (test modu) |
| **M2** | Paywall ayrı varlık | huniden bağımsız paywall + T1 kataloğuna paywall blokları: **plan seçici**, dinamik ödeme koşulları (seçili plana göre), sayaç (yapışık), indirim+sayaç, güven rozetleri, şart onayı; huni → paywall yönlendirmesi T3 kuralıyla (cevaba göre farklı paywall); sayfada Stripe gömülü ödeme (Embedded Checkout) + Apple/Google Pay — ayrı alt blok | M1, T1, T3 | Apple Pay alan adı doğrulaması sunucu ister |
| **M3** | Downsell + upsell | kapatma/geri tuşunda downsell penceresi (indirimli plan), ödeme sonrası **tek tık upsell** (kayıtlı kartla `off_session`), "ödeme sonrası" sayfası = ekran | M2 | yok |
| **M4** | Deneyler | **yönlendirme** (huni/paywall varyantına %) + **içerik** (`deney.<id>` özelliği T2'ye, T3 koşulunda); yapışık atama, durumlar (Taslak→Aktif→Durdur/Bitti, Taslağa dönüş yok, başlayınca kilit), sonuç: hacim, adım dönüşümü, ödeme oranı, ARPU — T4 olaylarından | M2 (paywall testi için), T2–T4 | yok |
| **M5** | Analitik | (a) **cevap dağılımı** raporu (soru başına; serbest metin hariç, Ç90) (b) **UTM raporu** (kaynak/kampanya → ziyaret, ödeme, gelir) (c) **kohort geliri** (başlangıç haftası × ay) — LTV tahmini yalnız kendi verimizle, sonra (d) Meta harcama içe aktarma → ROAS | T4, P4 | (d) Meta uygulaması + OAuth dönüş adresi = sunucu |
| **M6** | Abonelik yönetimi | panelden iptal (hemen / dönem sonu), iade, duraklat; **müşteri portalı** = e-posta koduyla giriş (var) + abonelik listesi + iptal + iptalde downsell; ücretli trafikten ÖNCE (AB'de kolay iptal beklentisi, Ç12 hukuk) | M1, kimlik | hukuk metni Ç12/Ç56 |
| **M7** | Giden webhook + yönetim API | birden çok hedef, `subscription` / `event` / `user_property` türleri, imzalı başlık, teslim günlüğü + yeniden deneme (H6b kuyruk deseni); MCP araç seti genişler (rapor, deney, abonelik okuma) | T4, M6 | yok |
| **M8** | E-posta | işlem e-postaları (abonelik başladı, ödeme düştü, iptal) + **dizi**: T4 olayı tetikler (ör. "quiz bitti, ödemedi"), gecikme, durdurma koşulu; şablon yer tutucuları; AI ile dizi taslağı (A2 döngüsü) | T4, M6 | **e-posta sağlayıcısı Ç30**, alan adı SPF/DKIM = sunucu |
| **M9** | Proje ayarları + roller | panelde uygulama ayarları (yasal veri → şartlar/gizlilik metni, varsayılan paywall/tema, alan adları, dil/ülke yönlendirmesi); gerekirse ikinci panel kullanıcısı + 2–3 rol | — | alan adları = sunucu |
| **M10** | Destek kutusu (isteğe bağlı) | gelen e-posta → konuşma + kullanıcı kartı (P5 + abonelik), AI yanıt taslağı | M8 | gelen e-posta yolu |
| **M11** | Çeviri (isteğe bağlı) | huni metinlerinin dil sürümleri + tarayıcı diline göre seçim (T3 koşulu) | — | — |

**Neden bu sıra:** M1–M3 doğrudan dönüşüm/gelir (web2wave'in paywall ağırlığı bunu gösteriyor: ~20 paywall bloğu, downsell, upsell, çok para birimi); M4 bunları **ölçerek** iyileştirmenin aracı ve paywall varlığı olmadan yarım kalır; M5 ücretli trafikte karar verdiren rapor; M6 canlı ödemenin ön koşulu; M7–M11 sunucu/dış girdiye bağlı ya da tek şirkette düşük öncelik. Sunucu gelmeden yapılabilen: M1–M4, M5 (a)–(c), M6 (hukuk metni hariç), M7.
