# Web'den ödeyen kişi uygulamaya nasıl girer — başkaları nasıl yapıyor (araştırma, 2026-11-03)

Kullanıcı 2026-11-03: "Ana yöntem e-posta kodu olsun; başkaları bunu nasıl yapıyor, araştır." (Ç9). Aynı gün Ç10 kararı: **canlıda da Stripe** (Paddle ücretleri yüksek, reddedildi; geliştirme aşamasındayız). Kod değişmedi — bu belge karşılaştırma ve öneri.

Önceki araştırma huni araçlarının bağlama mekanizmasını kapsıyordu (`rakip-arastirmasi.md` § RevenueCat Redemption Links, Adapty, web2wave; `giris-baglantisi.md`). Burada yeni olan: araçların kendi giriş yöntemleri ve e-posta kodunun iyi uygulamaları.

## Başkaları nasıl yapıyor

| Araç / kaynak | Yöntem | Kimlik anahtarı | Yedek yol |
|---|---|---|---|
| **FunnelFox** (belgesi okundu) | 4 yol: e-posta + şifre (Firebase/Supabase), OAuth (Google/Apple), **ertelenmiş derin bağlantı** (AppsFlyer/Adjust, `user_id`), **e-posta + sihirli bağlantı** | e-posta ya da kullanıcı kimliği | Derin bağlantı "tutmayabilir" → **her zaman yedek yöntem** önerir (sihirli bağlantı). Apple "Hide My Email"de kayıtlı e-posta `privaterelay.appleid.com` olur |
| **Qonversion** (belgesi okundu) | **Tek kullanımlık kurtarma bağlantısı** e-postayla; Universal Link / App Link ile uygulama açılır, girişsiz hak verilir | bağlantıdaki jeton | Uygulama kurulu değilse aynı adres "Uygulamada aç" sayfası gösterir ve **jetonu harcamaz**; 30 gün geçerli; kaybolursa e-postayla yeniden ister — yanıt **her durumda aynı** (e-posta yoklamasına karşı) |
| **RevenueCat** (önceki araştırma) | Redemption Link: özel şema + tek kullanımlık **60 dk** jeton | jeton | e-postayla yenisi |
| **Purchasely** | tek dokunuşla kurtarma bağlantısı, girişsiz | jeton | — |
| **Adapty** | ertelenmiş derin bağlantı + "customer user id" | kullanıcı kimliği | yedek giriş önerir (sihirli bağlantı / e-posta) |
| Geliştirici yazısı (dev.to) | ödemeden sonra tek kullanımlık giriş jetonu | jeton (yalnız özeti saklanır, kısa ömür, günlüğe yazılmaz) | — |

BetterMe, Noom gibi uygulamaların kendi yöntemi açık kaynaklarda bulunamadı (ölçülmedi).

**Ortak desen:** (1) ödemeden hemen sonra **girişsiz kısa yol** (jetonlu bağlantı / derin bağlantı), (2) o tutmazsa **e-postaya dayalı yedek** (kod ya da sihirli bağlantı). Kimse tek yönteme güvenmiyor.

## E-posta kodu — iyi uygulamalar (kaynaklar aşağıda; çoğu satıcı yazısı, sayılar kural değil)

| Konu | Önerilen | Bizde (`DogrulamaKodu.php`, `GirisBaglantisi.php`) |
|---|---|---|
| Kod mu bağlantı mı | Mobilde **kod** (bağlantı başka cihazda açılınca karışır; e-posta tarayıcıları bağlantıyı "tıklayıp" harcayabilir, kodu harcayamaz) | ✅ kod (ana yöntem) + ödemeden sonra tek kullanımlık bağlantı |
| Uzunluk | 6 hane yaygın (Okta); OWASP (ikincil kaynak) mümkünse 8 | 6 hane |
| Süre | 5–15 dk (Okta varsayılan 5) | ✅ 10 dk |
| Deneme | 3–5 sonra kilit | ✅ 5 / kod |
| Gönderim sınırı | e-posta başına saatte 3–5 | ✅ pencerede 3 kod |
| Tek kullanım, yeni kod eskiyi bitirir | evet | ✅ |
| Saklama | yalnız özet + kısa ömür + sınır (özet tek başına yetmez) | ✅ `kod_ozeti` + süre + sınır |
| E-posta yoklamasına karşı | kayıtlı olsun olmasın aynı yanıt | ✅ `POST /baglama/kod` hep 202 (ya da 429) |
| Bağlantının süresi | RevenueCat 60 dk, Qonversion 30 gün | 24 sa, tek kullanım |

**Kaba tahmin:** 6 hane, 5 deneme × pencerede 3 kod → bir saldırganın bir pencerede tutturma olasılığı ≈ 15 / 1.000.000. Hız sınırıyla yeterli; 8 haneye geçmek kullanıcıyı zorlar, şimdilik gerek yok.

## Bizde eksik olan (öneri, sırayla)

1. **Uygulama kurulu değilken giriş bağlantısı boşa gider.** Bugün bağlantı özel şema (`uygulama_semalari`); uygulama yoksa hiçbir şey olmaz, e-posta "önce kur, sonra dokun" diyor. Qonversion gibi: bağlantı **huni alanında bir sayfa** olsun (`/keepchat/giris?…`) — iOS'ta **Universal Link** ile uygulamayı açar, kurulu değilse "App Store'dan kur, sonra bu sayfaya dön" der ve **jetonu harcamaz**. Gerekenler: huni alanında `apple-app-site-association` dosyası (statik — Caddy'de sorun yok) + KeepChat'te Associated Domains. **Alan adı ister → dağıtımla birlikte** (sunucu).
2. **KeepChat'te kod alanı otomatik doldurma:** iOS 17+ kodu Mail'den önerir; alanın `textContentType(.oneTimeCode)` olması gerekir. KeepChat'te ölçülen tek şey: hesap ekranında `.password` / `.newPassword` alanları var (`ios/Sources/Screens/HesapEkranlari.swift`) — kod ekranı ayrı oturumda kontrol edilmeli (KeepChat deposu).
3. **Apple "Hide My Email":** Apple ile giriş eklendiğinde uygulamadaki e-posta `…@privaterelay.appleid.com` olur, web'deki ödeme e-postasıyla eşleşmez → bugünkü "Aboneliğimi bağla" (ödeme e-postasına kod) tam bu durumu çözüyor; **birleştirme yalnız doğrulanmış e-postayla** (Ç9 notu aynen geçerli).
4. **Gönderim güvenilirliği:** kod e-postası gelmezse yöntem çöker — gerçek e-posta sağlayıcısı (Ç30) + SPF/DKIM dağıtımla.

## Karar önerisi

E-posta kodu ana yöntem olarak **olduğu gibi kalır** (değerler sektör aralığında). Değişecek tek büyük şey (1): giriş bağlantısını Universal Link + yedek sayfaya çevirmek — sunucu ve alan adı gelince, KeepChat tarafıyla birlikte.

## Kaynaklar

- FunnelFox — sign-in methods: <https://funnelfox.com/docs/integrations/authentication/sign-in-methods.md>
- Qonversion — web funnel redemption: <https://documentation.qonversion.io/docs/web-funnel-redemption>
- Purchasely — web2app: <https://docs.purchasely.com/docs/web2app>
- Adapty — what is web-to-app: <https://adapty.io/blog/what-is-web-to-app-and-how-does-it-work/>
- dev.to — web2app, app opens already logged in: <https://dev.to/utkarsh4517/the-web2app-funnel-where-users-pay-on-the-web-and-open-the-ios-app-already-logged-in-even-before-456f>
- Scalekit — OTP vs magic links: <https://www.scalekit.com/blog/otp-vs-magic-links-passwordless-authentication>
- Okta — email authentication: <https://help.okta.com/en-us/Content/Topics/Security/mfa/email.htm>
- LoginRadius — email OTP: <https://www.loginradius.com/glossary/email-otp>
- coldletter — email OTP (OWASP özetleri, ikincil): <https://coldletter.com/blog/email-otp/>
- Hide My Email eşleşmeme örneği: <https://skiandsnowboardlive.helpscoutdocs.com/article/180-apple-sign-in>
