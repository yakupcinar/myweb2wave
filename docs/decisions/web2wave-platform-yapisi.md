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
| **M1** | Fiyat kataloğu | `fiyatlar` tablosu (uygulama başına çok plan: hafta/ay/yıl), Stripe ile eşleme (lookup_key), **giriş teklifi + deneme** (~~Subscription Schedule, en çok 2 evre~~ → M1b: deneme + tek seferlik ücret, plan başına bir teklif; A14 değişikliği 2026-11-04), **çok para birimi** (Stripe `currency_options`; ülkeden seçim, ziyaretçi seçmez) | L10 | yok (test modu) |
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

## Ek 2026-11-10 — M5–M9a sonrası: web2wave ile kalan farklar

Kullanıcı: "web2wave araştırması yap, bizim app bunun tek şirket için yapılmış hâli, referans al". Kaynak:
`docs.web2wave.com/llms.txt` (2026-11-10, 392 satır, 271 sayfa: 2 bölüm "Instructions" + "web2Wave API") ve
şu sayfalar: `auto-redirect-user-guide-language-country-based-redirects`, `project-setup`,
`handling-failed-payments-in-stripe`, `emails-after-subscriptions`, `emails`, `generate-email-sequences-with-ai`,
`post_creatives-generate`, `post_reports-quiz-answers-insights-generate`, `get_reports-chart-builder-catalog`,
`get_reports-overview`, `support-desk-setup`, `put_subscription-pause`, `upsell-subscription-with-cancellation-refund`.
Belge = üreticinin beyanı; panel çalıştırılmadı. Eski dizin kopyası saklanmadığı için "yeni" denen yalnız 11-03
notunda geçmeyenler.

**11-03'ten sonra görünen / o notta geçmeyen:**
- **Creatives** (19 uç): reklam istihbaratı (kategori trendleri, markalar, "market movers", alan adının açılış
  sayfaları) + AI ile görsel / video reklam üretimi (brief, kitle, senaryo, biçim, isteğe bağlı quiz ekranı); ön
  ödemeli cüzdan.
- **Rapor genel bakışı:** önceki dönem karşılaştırması; tahmini gelir (32–367 gün, platform LTV modeli), pROAS,
  geri ödeme günü. Süzgeçler: quiz, paywall, 5 UTM, ülke, platform, kullanıcı özelliği, ödeme sistemi, deney varyantı.
  **Chart builder** (metrik × boyut kataloğu); **cevap içgörüsü** (quiz cevapları üzerinde LLM, kuyruklu).
- **Dışa aktarım:** abonelikler, quiz cevapları, ziyaretler, deney kullanıcıları.
- **E-posta:**
  - panelde işlem şablonları (fiyat başına da);
  - yer tutucular: indirme bağlantısı, portal bağlantısı, plan / fiyat / sonraki ödeme, logo, destek e-postası, yasal
    adres, abonelikten çıkma;
  - diziler: başlatıcı "e-posta bırakıp gitti / herhangi bir satın alma / uygulama kuruldu", durdurucu
    "satın alma / uygulama kuruldu", gecikme;
  - **AI dizi üretimi:** Push to Purchase / Install / Upsell şablonları, stil paketi, taslak olarak kaydedilir;
  - harici ESP (Mailchimp, Sendpulse, Braze).
- **Dil / ülke yönlendirmesi:** ana quiz / paywall başına "dile göre" (tarayıcı) ya da "ülkeye göre" (IP); çeviriler
  ana içeriğin `Source` bağlı kopyaları. Ayrıca elle "çeviri değiştirici" bloğu. Eşleşme yoksa ne olduğu belgede yok.
- **Abonelik:** duraklat (Stripe `pause_collection`, sürdür ayrı uç), ek ücret çekme, iptal planlama; upsell'de eski
  aboneliği iptal + son faturayı iade tarifi.
- **Destek kutusu:** paylaşılan alan (`…@gethelpwithapps.com`) ya da kendi alt alan adı (MX + DKIM) ya da yönlendirme;
  e-postaların Reply-To'su buraya. Konuşma, AI yanıt taslağı, otomatik yanıtlar, şablonlar, atama / durum, günlük
  özet, sessize alma; ayrı ücretli.
- **Ödeme hatası:** paywall'da hata koduna göre eylem (açılır ekran / JS). Dunning belgede yok (Stripe'ın kendi ayarı).

### Bizde karşılığı (2026-11-10)

| Alan | web2wave | Bizde | Fark → öneri |
|---|---|---|---|
| Rapor | Önceki dönem, tahmini LTV / pROAS / geri ödeme, 12+ süzgeç, chart builder | 7/30/90 gün, UTM kırılımı, cevaba göre dönüşüm, kohort, Meta ROAS; **süzgeç ve önceki dönem yok** | **R1:** süzgeçler (huni, UTM, ülke, deney varyantı) + önceki dönem. LTV tahmini veri birikince (A1: platform verisi yok). Chart builder kapsam dışı |
| Cevap içgörüsü | LLM, quiz cevapları | Cevap dağılımı + dönüşüm; MCP `cevaplar` aracı Claude'a açık | Claude MCP ile zaten yapılabiliyor; ayrı özellik gerekmez (ölçülmedi) |
| Dışa aktarım | 4 tür CSV | Yok (olay akışı API'si + MCP var) | **R2:** panelden CSV (abonelikler, cevaplar, ziyaretler); kişisel veri maskesi kararı ister |
| E-posta şablonları | Panelde, yer tutucu, fiyat başına | 5 e-posta kodda sabit metin, yalnız Türkçe; ad / Reply-To ayarlardan (M9a) | **E1:** panelde şablon (konu + metin, tr/en, yer tutucu: indirme, portal, plan) — yasal metinlerin kalıbıyla |
| Diziler | Tetik / durdurma / gecikme panelden, AI üretim | Aktivasyon + kurtarma kodda (A22), adımlar sabit | E1 ile birlikte gecikme ve metin panelden; AI taslak huni asistanıyla aynı döngü |
| Dil / ülke | Çeviri kopyası + otomatik yönlendirme + değiştirici | Yönlendirme kuralları UTM'e bakıyor; para birimi IP ülkesinden (M1c) | **M9d** web2wave modeline uygun: kurala `ulke` ve `dil` koşulu, çeviri = ayrı huni. Eşleşmezse varsayılan huni (bizim kararımız) |
| Abonelik | Duraklat / sürdür, ek ücret, hemen iptal | Dönem sonu iptal / geri al, son fatura iadesi | **M6c:** duraklat / sürdür (Stripe `pause_collection`); ek ücret ve hemen iptal ihtiyaç olunca |
| Destek | Gelen kutusu + AI taslak + otomatik yanıt | Reply-To = destek e-postası (M9a) | **M10:** Postmark gelen e-posta webhook'u → konuşma + kullanıcı kartı + Claude taslağı |
| Ödeme hatası | Hata koduna göre paywall eylemi | Paywall'da hata metinleri (502 / ağ) | Küçük; M9c varsayılan paywall ile birlikte bakılabilir |
| Creatives | Reklam istihbaratı + AI reklam üretimi, kredi | Yok (backlog "AI pazarlama operasyonları — sonra") | Kapsam dışı: dış reklam verisi + görsel / video üretimi ayrı ürün; kullanıcı kararı |
| Kullanıcı API'si | identify, properties / events yazma, auth link | Hak, olay akışı, portal; yazma yok | İhtiyaç olunca |

**Sıra önerisi (M9'dan sonra):** M9b2 → M9c → M9d → R1 → E1 → M6c → R2 → M10 → M11. Gerekçe: M9 kalanları
canlıya geçişin önkoşulu (yasal bağlantı, varsayılan paywall, dil); R1 ücretli trafikte karar vermenin aracı
(süzgeçsiz rapor tek kampanyayı ayıramıyor); E1 kod değiştirmeden metin düzeltmeyi açar; M10 en büyük iş ve gelen
e-posta yolu ister.

**Karar (kullanıcı, 2026-11-10):** önerilen sıra kabul (M9b2 → M9c → M9d → R1 → E1 → M6c → R2 → M10 → M11); Creatives **kapsam dışı** (backlog'da "sonra").

## Ek 2026-11-10 — arayüz (panel gezildi)

Kullanıcı kendi web2wave hesabını açtı; panel Chrome'da salt okunur gezildi (Analytics, Quizzes, quiz düzenleyici +
Overview, Paywalls, paywall düzenleyici, proje ayarları, Subscriptions). Hiçbir şey kaydedilmedi; iki düzenleyiciden
çıkarken tarayıcının "kaydedilmemiş değişiklik" uyarısı atıldı (Overview'ın ürettiği ekran görüntüleri; içerik
değiştirilmedi). **Kullanıcı kararı: web2wave yapı / iş akışı referansı; görsel stil Apple'ın tasarım dili olacak,
web2wave'in koyu-yeşil stili değil.**

**Bilgi mimarisi (sol menü, gruplu):** MCP · ANALYTICS (Analytics, Chart builder, Table Report, A/B Tests) · FUNNELS
(Quizzes, Paywalls, Email Sequences) · CRM (User Visits, Subscriptions, Support Desk, Chargebacks) · ADS CREATIVES
(Trends, AI Creatives) · SERVICES (Growth & Marketing, Agency Ad Accounts, Paid Ads Financing) · SETTINGS (Projects,
Plans & Prices, Pricing, Files, Exports, Documentation). Üst çubuk: sayfa adı, plan rozeti, yardım, açık/koyu, tam
ekran, kullanıcı.

**Ortak sayfa kalıbı:** her listenin üstünde kapatılabilir tanıtım kartı (ne işe yarar + belge bağlantısı + küçük
görsel); altında süzgeç kartı (tarih aralığı, proje, arama, "Filters:" yanında hap biçiminde eklenebilir süzgeçler);
altında tablo ve satır sonunda simge eylemleri (analitik, düzenle, kopyala, AI ile çevir, sürümler, sil).

**Analytics:** tarih + proje + 15 hap süzgeç (quiz, quiz sürümü, paywall, paywall sürümü, ödeme sistemi, live/test,
5 UTM, ülke, platform, deney, kullanıcı özelliği); sekmeler Overview / Cohorts revenue / Subscriptions / Quiz
Analytics / Paywall Analytics / Install Conversion; Overview'da gruplu KPI kartları (REVENUE, ADS, SUBSCRIPTIONS,
FUNNEL, CONVERSIONS), her kartta önceki döneme göre % değişim; altında "karta tıkla grafiğe ekle, Shift ile karşılaştır"
zaman grafiği + CSV.

**Quizzes listesi:** yasal sayfalar, abonelik yönetimi ve ödeme sonrası sayfa da birer "quiz/page"; etiketler Main
quiz / Funnel stage / After pay / Subscription; sütunlar ID+sürüm, ad+dil, URL, "after quiz go to" paywall, son 24 sa
kullanıcı; satırda "Insights" ve "Creatives" düğmeleri.

**Düzenleyici (quiz ve paywall aynı):** dört sütun — ekranlar | seçili ekranın blokları (arama, +) | telefon önizlemesi
(tema seçici + "Edit styles", sürüm, LIVE bağlantısı, Overview, cihaz / yakınlaştırma) | blok özellikleri (Block
Properties / Styles; her alanın yanında "Conditions"; ham HTML / CSS alanları). Üstte Settings / JSON / Editor / Files
sekmeleri, dil, sürüm, Show Diff, Save, Save & Publish, AI Helper. Paywall'da popup'lar da ekran; "After pay go to"
ekran listesinin altında. **Overview:** ekranların oklarla bağlı kart haritası, kartta Edit + "Internal note".

**Proje ayarları:** üstte "Project settings checklist 0/8" (ödeme sağlayıcı, fiyat, alan adı, e-posta gönderici,
piksel, SDK, indirme bağlantıları, ekip); sekmeler General, Analytics, Ad Accounts, Payments, ECommerce, Chargebacks,
Deeplinks & Billing, Emails, Support Desk, Translations, API & Webhook, Members. General'da: zaman dilimi, özel alan
adı (CNAME yönergesiyle), etiketler (niş), **varsayılan tema** (adlı hazır temalar), **varsayılan quiz**, **varsayılan
paywall**, müşteri portalı adresi, **yasal veriler** (AppStore ID, Google Store ID, şirket adı, adres, ülke — ToS ve
Privacy'de kullanılır), paylaşım önizlemesi (başlık, açıklama, görsel), robots.txt, yazı tipleri.

**Subscriptions:** abonelik tarihi aralığı, proje, ID / kullanıcı / e-posta araması; hap süzgeçler (ödeme sistemi,
durum, quiz / paywall, fiyat, LTV, ülke, 5 UTM, sıralama, deney).

### Bizdeki karşılığı ve fark (arayüz)

| Konu | web2wave | Bizde | Not |
|---|---|---|---|
| Menü | 7 grup, ~25 öğe | 10 öğe, grupsuz | Gruplamak (Analitik · Huniler · Müşteriler · Ayarlar) okunurluğu artırır |
| Sayfa kalıbı | tanıtım kartı + süzgeç kartı + tablo + satır simgeleri | başlık + kartlar; süzgeç yalnız Kullanıcılar'da | Apple dilinde tanıtım kartı yerine başlık altı kısa açıklama yeter |
| Rapor | gruplu KPI kartları, önceki dönem %, hap süzgeçler, sekmeler | tek sayfa kartlar, süzgeç ve önceki dönem yok | R1 ile örtüşür |
| Düzenleyici | 4 sütun, her alanda koşul, JSON / diff / sürüm | Puck: bloklar + önizleme + alanlar, sürümler, AI sohbeti | Yapı benzer; "Show Diff" ve ekran haritası (Overview) bizde yok |
| Yasal sayfalar | quiz/page olarak, şablondan | ayrı sayfa + metin düzenleyici (M9b) | Bizimki daha basit, metin hunideki gibi düzenlenmez |
| Varsayılanlar | ayarda "Default quiz / paywall / theme" (çalışırken) | "yeni hunilere örnek" (kopya, M9c) + varsayılan huni | Kullanıcı kopya modelini seçti |
| Kurulum yönlendirmesi | proje ayarları kontrol listesi 0/8 | yok | Canlıya geçişte (Stripe, Postmark, alan adı, cron) işe yarar |
