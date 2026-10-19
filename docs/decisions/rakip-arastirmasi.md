# Rakip araştırması — web'den uygulamaya bağlama, hak sorgusu, panel — 2026-10-19

> Kapsam C4: yalnız herkese açık sayfa, belge ve SDK **davranışı**. Bot
> koruması aşılmadı, panele/API'ye istek atılmadı, **kod alınmadı** (kural 12).
> Belge alıntıları kısa; davranış kendi cümlemizle. A1 değişmez — panel
> bilgisi yalnız bilgi içindir. Karar verilmedi; bulgular `acik-uclar.md` Ç24–Ç27.

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

**S3 — panel:** ölçülmedi (`app.web2wave.com` → Cloudflare 403, aşılmadı).
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

**S3 — panel:** Huni editörü (+ "AI Chat in Editor"), abonelik listesi
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

**S3 — panel:** Web paywall editörü (barındırılan bağlantı ya da
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

**S3 — panel:** Web satın alımları App Store/Play geliriyle aynı analitikte,
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

**S3 — panel:** ölçülmedi.

## Özet tablo

| | Bağlama | Hak sorgusu kimlik doğrulama | past_due / grace |
|---|---|---|---|
| web2wave | MMP derin bağlantı → 48 sa parmak izi → geri yükle; e-posta yalnız doğrulamayla | uygulamaya gömülü proje `api-key` (arka uçsuz önerilir) | **erişim yok** (`active`, `trialing`) |
| FunnelFox | derin bağlantıda `user.id`; Adapty yolunda **doğrulanmamış e-posta** varsayılan | platform SDK'sı; kendi Billing'i gizli anahtar | GRACE erişimli, RETRY belirsiz |
| RevenueCat | tek kullanımlık 60 dk jetonlu bağlantı | SDK açık anahtar / sunucu `sk_` | Stripe'ta yeniden deneme boyunca **erişim sürer** |
| Adapty | profil kimlikli URL | SDK / sunucu gizli anahtar, uygulama başına | grace **aktif** |
| Superwall | kodlu derin bağlantı | istemci SDK'sı, 24 sa yenileme | bulunamadı |

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

## Yöntem ve ölçüm
- **Kod yazılmadı → kırma denemesi uygulanmaz.** Ölçüm: her iddianın yanında
  kaynak URL ya da komut. 5 araç × 3 soru; cevapsızlar "bulunamadı" /
  "ölçülmedi" olarak bırakıldı (toplam 9 hücre).
- Tutmayan aramalar: RevenueCat `docs/web/web-billing/overview.md` → **404**;
  Superwall `web-checkout-linking-membership-to-iOS-app` yalnız platform
  seçici (iOS sayfası ayrıca açıldı); `gh api orgs/funnelfox/repos` → 404.
- `docs.web2wave.com` sayfaları açılmadı; web2wave iddiaları kendi
  depolarındaki playbook'tan (lisanssız → yalnız davranış).
