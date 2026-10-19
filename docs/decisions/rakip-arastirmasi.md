# Rakip araştırması — bağlama, hak sorgusu, analitik/reklam atfı, panel — 2026-10-19

> Kapsam C4: yalnız herkese açık sayfa, belge ve SDK **davranışı**. Bot
> koruması aşılmadı, panele/API'ye istek atılmadı, **kod alınmadı** (kural 12).
> Belge alıntıları kısa; davranış kendi cümlemizle. A1 değişmez — panel
> bilgisi yalnız bilgi içindir. Karar verilmedi; bulgular `acik-uclar.md` Ç24–Ç28.

## Akış nasıl işliyor (teknik olmayan özet)

1. **Reklam** — kişi Meta/TikTok reklamına tıklar; adres reklam kimliğini
   (`fbclid`/`ttclid`) ve kampanya etiketlerini (UTM) taşır. ❌ Bizde: hunide yakalanmıyor.
2. **Huni** — quiz/onboarding adımları, sonunda paywall. ✅ Bizde: statik
   KeepChat paywall'u (`/keepchat/`). ❌ Quiz adımları ve adım olayları yok.
3. **Ödeme** — Stripe ödeme sayfası, abonelik webhook'la kaydedilir.
   ✅ Bizde: Stripe test + imzalı webhook + uygulama boyutu.
4. **Reklam ağına dönüşüm** — "bu reklam satış getirdi" bilgisi tarayıcı
   pikseli + sunucudan (Meta Conversions API, TikTok Events API) gider; ağ
   reklamı buna göre optimize eder. ❌ Bizde: piksel, sunucu bildirimi ve
   çerez onayı yok.
5. **Uygulamaya geçiş** — Universal Link ya da ertelenmiş derin bağlantı
   kişiyi uygulamaya taşır, uygulama aboneliği tanır. ❌ Bizde: derin bağlantı
   ve abonelik sorgu ucu yok; e-posta koduyla bağlama tasarımda (Ç9).

## Lisans ölçümü (kaynak başına)

| Kaynak | Ölçüm | Sonuç |
|---|---|---|
| `web2wave/web2wave_swift`, `web2wave/integration-skills` | `gh api repos/web2wave/<r> --jq .license.spdx_id` | **YOK** → tüm hakları saklı; yalnız davranış |
| `RevenueCat/purchases-ios`, `RevenueCat/purchases-js` | `gh api repos/... --jq .license.spdx_id` | MIT (bakımda, son push 2026-10-19) |
| `adaptyteam/AdaptySDK-iOS` | aynı | MIT |
| `superwall/Superwall-iOS` | aynı | MIT |
| FunnelFox SDK deposu | `gh api orgs/funnelfox/repos` → 404 | **bulunamadı** |
| Belge sayfaları (hepsi) | — | lisans bildirimi **ölçülmedi**; kod alınmadığı için kısa alıntı + kendi cümle |

## 1 · web2wave (önceki kayıt: `web2wave-yigin-arastirmasi.md`)

**S1 — bağlama:** Katmanlı. (1) MMP'nin (AppsFlyer/Adjust/Branch) ertelenmiş
derin bağlantısı web2wave `user_id`'sini uygulamaya taşır; (2) yoksa kendi
"deferred deeplinks" SDK'sı cihazı **son 48 saatteki** quiz/paywall
oturumuyla eşler (`match_method` → `no_match` olabilir; parmak izi: SDK
başlıklarında ekran boyutu, saat dilimi, iOS sürümü, cihaz modeli);
(3) o da olmazsa "Erişimi geri yükle" yolu. E-postayla aramada açık kural:
"an email address alone is not proof of ownership" — doğrulama şart.
Kaynak: `gh api repos/web2wave/integration-skills/contents/docs/web2wave-mobile-integration-playbook.md` §2.1–2.4;
`docs.web2wave.com/reference/web2wave-deferred-deeplinks` (açılmadı, playbook'tan).

**S2 — hak sorgusu:** `GET /user/subscriptions?user={guid}` ya da
`GET /subscriptions/list?user_id={guid}`; başlık `api-key: <WEB2WAVE_API_KEY>`
(Bearer değil). Playbook **arka uçsuz** kurulumu önerir ("Backend (optional —
recommend 'no')") → anahtar uygulamanın içinde. Bulunamayan kullanıcı çoğu
zaman **422** `{"error":1,...}`; `error===1` HTTP 200'de de hata.
**past_due:** "Entitlement: `status ∈ {active, trialing}` is access" — past_due,
paused, canceled erişim VERMEZ (arayüzde listelenir). SDK'da aynı:
`status == "active" || status == "trialing"`.
Kaynak: playbook §5, §6.1; `Web2Wave.swift` `hasActiveSubscription` (davranış okundu).

**S3 — analitik / reklam atfı:** Piksel ve erişim jetonları panelde
"Project → Analytics"te; web2wave dışındaki sayfalar (ör. teşekkür sayfası)
JS SDK ile `purchase`/`track('Subscribe')` gönderir, web2wave dönüşümü
**sunucudan** Meta, TikTok ve diğer ağlara iletir ve quiz oturumuna bağlar.
Amplitude/PostHog/Mixpanel bu sunucu yoluna girmez, yalnız quiz/paywall
içindeki tarayıcı olaylarını alır. Adım olay adları ve çerez onayı
**bulunamadı**. Kaynak: <https://docs.web2wave.com/reference/web2wave-js-sdk>.

**S4 — panel:** ölçülmedi (`app.web2wave.com` → Cloudflare 403, aşılmadı).
Pazarlama sayfasına göre huni/quiz editörü, A/B, analitik
(<https://www.web2wave.com>); playbook'ta proje ayarları "Deeplinks & Billing →
Helper", "API & Webhook".

## 2 · FunnelFox

**S1 — bağlama:** Ertelenmiş derin bağlantı (Adapty UA, AppsFlyer `ffkey`,
Adjust `user_id`) varsayılan olarak `{{user.id}}` taşır; e-posta da
taşınabilir. **Token/süre/güvenlik notu yok; derin bağlantı tutmazsa yedek
yol anlatılmıyor.** Adapty entegrasyonunda eşleme anahtarı **"customer user
ID"; varsayılanı ödemede yazılan e-posta** ve uygulama aynı değerle
`Adapty.identify()` çağırır — belgede bu e-postanın uygulamada
**doğrulanması anlatılmıyor**. Qonversion'da `fnlfx_{profile_id}` + kimlik
(e-posta ya da özel). Kaynak: <https://funnelfox.com/docs/integrations/authentication/deep-links.md>,
<https://adapty.io/docs/funnelfox>, <https://funnelfox.com/docs/integrations/subscription-management/qonversion.md>.

**S2 — hak sorgusu:** Genelde abonelik platformunun SDK'sı (RevenueCat,
Adapty, Qonversion). Kendi FunnelFox Billing'inde sunucu API'si
`billing.funnelfox.com/{org_id}/v1`, başlık `ff-secret-key` (gizli),
`/subscription/search`. Durumlar: RECURRING, **GRACE ("Payment failed, but
the user still has temporary access")**, **RETRY** (erişim **belirtilmemiş**),
EXPIRED; `is_active` alanı var, GRACE/RETRY'deki değeri yazılmamış. Grace
süresi bulunamadı. Kaynak: <https://funnelfox.com/docs/billing/view-subscriptions-billing>,
<https://funnelfox.com/docs/develop/api-billing.md>.

**S3 — analitik / reklam atfı:** Meta Pixel + **Conversions API**
(FunnelFox sunucusundan, tarayıcı olayıyla tekilleştirme); `Purchase`
ödeme sağlayıcısından işlem gelince sunucudan atılır (huniye dönmeyen
PayPal'ı da yakalar), yalnız deneme ise `StartTrial`. TikTok Pixel +
**Events API** aynı düzende (`CompletePayment`). Amplitude'a standart huni
olayları otomatik (`screen_presented` → "Page View"), kimlik FunnelFox
profil kimliğinden; Mixpanel, PostHog, GA4 de listede. **Çerez onayı:**
huni öğesi; kategoriler gerekli / analitik / izleme; Google onay
doğrulamasıyla entegre **değil**; olayların onaya kadar bekletildiği
**bulunamadı**. Kaynak: <https://funnelfox.com/docs/integrations/tracking/set-up-meta-pixel-integration.md>,
<https://funnelfox.com/docs/integrations/tracking/set-up-tiktok-pixel.md>,
<https://funnelfox.com/docs/integrations/analytics/amplitude>,
<https://funnelfox.com/docs/elements/cookie-consent>.

**S4 — panel:** Huni editörü (+ "AI Chat in Editor"), abonelik listesi
(e-posta/durum süzgeci, abonelik geçmişi, webhook olayları); gelir raporu
bulunamadı; A/B bu sayfada yok. Ödeme: Stripe, Solidgate, Paddle.
Kaynak: <https://funnelfox.com/docs/getting-started/quickstart.md>, <https://funnelfox.com/docs/billing/view-subscriptions-billing>.

## 3 · RevenueCat Web Billing / Redemption Links

**S1 — bağlama:** Kimliği bilinen kullanıcıya bağlantıya App User ID
eklenir; anonim alıcıya **Redemption Link**: özel URL şeması + jeton
(`rc-…://redeem_web_purchase?redemption_token=…`), **tek kullanımlık, 60 dk**;
başarı sayfasında ve fatura e-postasında. Uygulama `redeemWebPurchase`
çağırır; anonim taraf varsa kayıtlar birleştirilir (alias), iki taraf da
tanımlıysa varsayılan **aktarma**. Süresi dolan bağlantıda yenisi fatura
e-postasına gider. **E-posta eşleme anahtarı değil**, yalnız teslim/kurtarma.
Kaynak: <https://www.revenuecat.com/docs/web/redemption-links.md>,
<https://www.revenuecat.com/docs/web/web-billing/web-purchase-links>.

**S2 — hak sorgusu:** Uygulama SDK'sı **herkese açık (SDK) anahtar**la;
sunucu REST API v2 **gizli `sk_`** + `Authorization: Bearer`. Hak okuma uç
yolu bu sayfada yok (ölçülmedi). **past_due:** Stripe'ta
`grace_period_expires_date` hep `null`; grace yeni işlemin bitiş tarihine
gömülü → yeniden deneme sürerken erişim **sürer**, tüm denemeler düşünce
bitiş güncellenir. Forumda "past_due olunca hemen kapatamıyorum" şikâyeti
(2025-12). Kaynak: <https://www.revenuecat.com/docs/projects/authentication.md>,
<https://www.revenuecat.com/docs/subscription-guidance/how-grace-periods-work>,
<https://community.revenuecat.com/general-questions-7/cancel-entitlements-during-grade-period-7244>.

**S3 — analitik / reklam atfı:** Huni olayları: adım başladı, adım
tamamlandı, satın almasız çıkış, satın alma (`rc_workflows_*`); "terk"
olayı yok. Hedefler: Amplitude, Mixpanel, Segment, Meta (özel olay), GTM
`dataLayer`. Meta'ya satın alma hem Pixel hem **Conversions API**; abonelik
olayları Meta adlarına eşlenir (deneme → `StartTrial`, dönüşüm/ilk alım/
yenileme → `Subscribe`), tekilleştirme için RevenueCat sipariş kimliği.
UTM: Meta dinamik makrolarıyla reklam başına adres önerisi. **Çerez onayı:**
`_fbp`/`_fbc` okunuyor, onay mekanizması **bulunamadı**. TikTok, PostHog,
AppsFlyer bu sayfada yok. Kaynak: <https://www-docs.revenuecat.com/docs/tools/funnels/integrations.md>,
<https://www.revenuecat.com/docs/integrations/attribution/meta-ads.md>.

**S4 — panel:** Web paywall editörü (barındırılan bağlantı ya da
`purchases-js`), barındırılan ödeme (Web Purchase Links, "Funnels" altında);
motorlar RevenueCat Billing (Stripe ağ geçidi), Stripe Billing, Paddle (MoR).
Müşteri listesi/grafikler web için bulunamadı. Belgeler Stripe Billing
desteğinde **çelişkili** (bir sayfa destekli, diğeri "not yet supported").
Kaynak: <https://www.revenuecat.com/docs/web/paywalls.md>,
<https://www.revenuecat.com/docs/web/web-billing/web-sdk>.

## 4 · Adapty Web Paywalls

**S1 — bağlama:** SDK'nın web paywall yöntemi **Adapty profil kimliğini
taşıyan benzersiz URL** üretir; "you don't have to call `Adapty.identify()`".
Dönüşte SDK profili **yoklar**. URL süresi bulunamadı. (FunnelFox yolu ayrı:
e-posta = customer user ID, bkz. §2.) Kaynak: <https://adapty.io/docs/web-paywall>,
<https://adapty.io/docs/ios-web-paywall>.

**S2 — hak sorgusu:** SDK'da `is_active`; **sunucu API'sinde `is_active`
yok**, `starts_at`/`expires_at` + `is_in_grace_period` +
`billing_issue_detected_at`'tan türetilir; örnek mantık **grace'teki
kullanıcıyı aktif sayar**. Sunucu: profil GET, `Api-Key` gizli anahtar +
`adapty-customer-user-id`/`adapty-profile-id`; **anahtar uygulama başına**.
İptal edilen abonelik ödenmiş dönem bitene kadar erişim tutar.
Kaynak: <https://adapty.io/docs/server-side-api-objects>, <https://adapty.io/docs/web-paywall>.

**S3 — analitik / reklam atfı:** "Adapty web pixel" her ziyaretçiye **7 gün**
geçerli bir tıklama kimliği verir; açılış adresinden Meta/TikTok tıklama
kimliklerini, kampanya/ad set/reklam kimliklerini ve UTM'yi okur; ödemede
işleme yalnız bu tıklama kimliği eklenir, satın alma Stripe/Paddle
bağlantısından gelir. Reklam düzeyi için Adapty'nin ürettiği adres
parametreleri her reklama yapıştırılır. Reklam ağına sunucudan dönüşüm
(CAPI), huni adım olayları ve çerez onayı **bulunamadı**.
Kaynak: <https://adapty.io/docs/ua-web-pixel>, <https://adapty.io/docs/ua-tag-ad-urls.md>.

**S4 — panel:** Web satın alımları App Store/Play geliriyle aynı analitikte,
"Profiles" bölümünde alıcı profili (<https://adapty.io/docs/funnelfox>).
Web paywall editörü ölçülmedi.

## 5 · Superwall Web Checkout (ek araç)

**S1 — bağlama:** Varsayılan "Redeem" kipi: satın almadan sonra **kullanım
kodu taşıyan derin bağlantı** uygulamayı açar (yüklü değilse mağaza);
SDK `willRedeemLink` / `didRedeemLink(result:)` — sonuçlar: başarı, hata,
**süresi dolmuş kod**, geçersiz kod, süresi dolmuş abonelik. Kod süresi
bulunamadı. "Redirect" kipi: kendi adresine satın alma ayrıntılarıyla.
Kaynak: <https://superwall.com/docs/ios/guides/web-checkout/post-checkout-redirecting>.

**S2 — hak sorgusu:** İstemci SDK'sı: `Superwall.shared.entitlements.web`;
kendi `PurchaseController` varsa cihaz haklarıyla birleştirilip
`subscriptionStatus` elle ayarlanır; web hakları **24 saatte bir**
yenilenir. Anahtar türü yazmıyor (ölçülmedi). Web aboneliğinde
past_due/grace **bulunamadı** ("Handled platform-side" yalnız mağaza için).
Kaynak: <https://superwall.com/docs/ios/guides/web-checkout/linking-membership-to-iOS-app.md>.

**S3 — analitik / reklam atfı:** Meta için MMP olarak kurulumu attırır ve
dönüşümü Meta'ya geri gönderir (iOS, SuperwallKit 4.16+, yeni kurulumlar,
web veri kümesi şart); ayrı bir **Meta Conversion API** entegrasyonu
abonelik olaylarını sunucudan iletir (deneme → `StartTrial`, abonelik →
`Subscribe`, dönüşüm/yenileme → `Purchase`, gelirle). Web checkout için
panelde "Checkout Conversion" grafiği; atıf etiketleri bağlantıya eklenir
ve Stripe metadata'sına akar. **Web checkout satın alımlarının Meta'ya
dönüşüm olarak gittiği bulunamadı**; çerez onayı bulunamadı.
Kaynak: <https://superwall.com/docs/integrations/meta-ads.md>,
<https://api.superwall.me/blog/whats-new-august-2026>.

**S4 — panel:** ölçülmedi.

## Özet tablo

| | Bağlama | Hak sorgusu kimlik doğrulama | past_due / grace | Reklam ağına dönüşüm | Çerez onayı |
|---|---|---|---|---|---|
| web2wave | MMP derin bağlantı → 48 sa parmak izi → geri yükle; e-posta yalnız doğrulamayla | uygulamaya gömülü proje `api-key` (arka uçsuz önerilir) | **erişim yok** (`active`, `trialing`) | sunucudan (Meta, TikTok…) | bulunamadı |
| FunnelFox | derin bağlantıda `user.id`; Adapty yolunda **doğrulanmamış e-posta** varsayılan | platform SDK'sı; kendi Billing'i gizli anahtar | GRACE erişimli, RETRY belirsiz | Pixel + CAPI, TikTok Events API | huni öğesi (3 kategori) |
| RevenueCat | tek kullanımlık 60 dk jetonlu bağlantı | SDK açık anahtar / sunucu `sk_` | Stripe'ta yeniden deneme boyunca **erişim sürer** | Pixel + CAPI (Meta) | bulunamadı |
| Adapty | profil kimlikli URL | SDK / sunucu gizli anahtar, uygulama başına | grace **aktif** | web pikseli (tıklama eşleme); CAPI bulunamadı | bulunamadı |
| Superwall | kodlu derin bağlantı | istemci SDK'sı, 24 sa yenileme | bulunamadı | MMP (iOS) + CAPI (abonelik); web checkout bulunamadı | bulunamadı |

## Bu bloğu (kimlik + e-posta kodu + hak ucu) değiştirecek bulgular

1. **past_due'nun anlamı ürün kararı ve rakipler ikiye ayrılıyor** — web2wave
   erişimi keser; RevenueCat (Stripe), Adapty, FunnelFox GRACE erişimi sürdürür.
   Hak ucumuzun hangi durumları "aktif" sayacağı tanımsız → **Ç24** (Ç20'yle bağlı).
2. **Hiçbiri "e-posta yazınca bağlanır" yapmıyor, biri hariç** — RevenueCat
   ve Superwall tek kullanımlık jeton/kod taşıyan derin bağlantı; web2wave
   e-posta için doğrulama şartı koyuyor; FunnelFox→Adapty varsayılanı
   doğrulamasız e-posta. Ç9'daki e-posta kodu kararımız doğrulamalı tarafta.
   Ek seçenek: başarı sayfası + e-postada **tek kullanımlık bağlama
   bağlantısı** (yazmasız) → **Ç25**.
3. **Hak sorgusunu kim yapıyor** — istemci anahtarı (web2wave, RevenueCat
   SDK, Superwall) ya da sunucu gizli anahtarı (Adapty, RevenueCat REST,
   FunnelFox). KeepChat'in kendi sunucusu var: KeepChat sunucusu mu sorar
   (sunucudan sunucuya, uygulama başına anahtar — Adapty gibi) yoksa
   uygulama kullanıcı jetonuyla mı? → **Ç26**.
4. **Sıfır girişli bağlama MMP'ye dayanıyor** (web2wave, FunnelFox:
   AppsFlyer/Adjust); bizde MMP yok. Parmak izi yedeği (web2wave, 48 sa)
   olasılıksal ve Apple'ın parmak izi politikasıyla gerilimli (Ç9'da not) →
   **Ç27**.
5. **Reklam ağına dönüşüm herkeste SUNUCUDAN gidiyor** (FunnelFox, RevenueCat,
   web2wave, Superwall abonelik olayları): tarayıcı pikseli tek başına
   kayıplı, sunucu bildirimi ödeme sağlayıcısının olayından atılıyor ve
   pikselle tekilleştiriliyor. Huni olayları ise ayrı yolda, ürün
   analitiği aracına (Amplitude/Mixpanel/PostHog) gidiyor. Çerez onayını
   açıkça belgeleyen tek araç FunnelFox. Bizde bunların hiçbiri yok → **Ç28**
   (Ç12'ye bağlı).

## Karar seçenekleri — analitik ve reklam atfı (karar DEĞİL; Ç28)

Her seçenekte ortak: reklam ağına dönüşüm **çekirdekten** (Stripe
webhook'u geldiğinde Meta CAPI / TikTok Events API) — rakiplerin hepsi böyle
ve B2'de para akışı zaten çekirdekte. AB ziyaretçisi için çerez onayı her
seçenekte şart (Ç12).

| | A · Hazır analitik aracı | B · Kendi panelimiz | C · Karışık |
|---|---|---|---|
| Ne | Huni olayları PostHog/Amplitude/Mixpanel'e; raporlar orada | Huni olayları çekirdeğe (Postgres), raporları biz yazarız | Olay + tıklama kimliği + UTM **bizim Postgres'te** (gerçeğin kaynağı); görselleştirme hazır araçta ya da basit kendi tablomuzda |
| Maliyet | olay hacmine göre; ücretsiz katman sınırları **ölçülmedi** | araç ücreti yok; geliştirme ve bakım bizde (huni adımı, terk, kohort raporları) | ikisinin arası |
| KVKK / GDPR | veri üçüncü tarafta; yurt dışı aktarım + işleyen sözleşmesi; AB/yerel barındırma ya da kendi sunucumuzda çalıştırma seçeneği **ölçülmedi** | veri yalnız bizde; onay yine şart | ham veri bizde, araca yalnız gereken gider |
| B2'ye etkisi | statik huniye tarayıcı betiği + onay; çekirdek değişmez (CAPI hariç) | çekirdeğe olay ucu (CORS yolu genişler), yeni tablo; huni betiği bize yazar | B + seçilen aracın betiği ya da çekirdekten aktarım |
| Rakiplerde | FunnelFox, RevenueCat, web2wave (Amplitude/PostHog'a aktarım) | — (panel grafikleri var ama ana analitik dışarıda) | web2wave/Superwall: dönüşüm kendi sistemlerinde, ürün analitiği dışarıda |

## Yöntem ve ölçüm
- **Kod yazılmadı → kırma denemesi uygulanmaz.** Ölçüm: her iddianın yanında
  kaynak URL ya da komut. 5 araç × 3 soru; cevapsızlar "bulunamadı" /
  "ölçülmedi" olarak bırakıldı (toplam 9 hücre).
- Tutmayan aramalar: RevenueCat `docs/web/web-billing/overview.md` → **404**;
  Superwall `web-checkout-linking-membership-to-iOS-app` yalnız platform
  seçici (iOS sayfası ayrıca açıldı); `gh api orgs/funnelfox/repos` → 404.
- `docs.web2wave.com` sayfaları açılmadı (S3 hariç: JS SDK sayfası arama
  özetinden); web2wave iddiaları çoğunlukla kendi depolarındaki
  playbook'tan (lisanssız → yalnız davranış).
- **2. tur (aynı gün):** 4. soru (analitik/reklam atfı/çerez onayı) ve akış
  özeti eklendi; eski S3 (panel) → S4. Çerez onayı: web2wave, RevenueCat,
  Adapty, Superwall için **bulunamadı**.
