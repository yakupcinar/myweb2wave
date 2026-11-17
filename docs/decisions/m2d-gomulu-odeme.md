# M2d — gömülü ödeme (Embedded Checkout); Apple/Google Pay sunucuya ertelendi (2026-11-04)

Karar `pre-setup.md` A14 ("ödeme paywall sayfasında, gömülü"), A15 (M2'nin bölünmesi)
· kullanıcı 2026-11-04: "Apple ve Google Pay sunucu kurulunca; gömülü ödemeyle devam".

## Ölçüm (önce)

- Ödeme Stripe'ın barındırılan sayfasına yönlendirme (`url`); başarı/iptal dönüş sayfaları var.
- Stripe belgesi ve gerçek API: `ui_mode: embedded_page` + `return_url` → `client_secret`
  (url yok); hesabın API sürümü `2026-09-30.endive` → Stripe.js `js.stripe.com/endive/stripe.js`,
  `createEmbeddedCheckoutPage({fetchClientSecret}).mount(kutu)`. stripe-mock `client_secret`
  döndürmüyor (testte kayıtlı yanıt).
- Yayımlanabilir anahtar yalnız `core/.env.local`'da; huni sayfasında CSP yok; JS bütçesi
  30 KB gzip (Stripe.js dışarıdan yüklendiği için bütçeye girmez).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Gömülü ödeme varsayılan; Stripe.js yüklenemezse (reklam engelleyici, ağ, 8 sn) **barındırılan sayfaya düşer** | yalnız gömülü | Ödeme hiçbir durumda kırılmasın |
| 2 | Sunucuda yayımlanabilir anahtar yoksa `gomulu` yok sayılır (eski yol) | anahtarı huni derlemesine gömmek | Statik huni ortamdan bağımsız kalır; anahtar ödeme yanıtında gelir |
| 3 | Panel paywall'ın üstünde (telefonda alttan), kapat/Escape → paywall'a dönülür, form yok edilir | gömülü formu paywall'ın içine koymak | Paywall içeriği (seçici, koşul) olduğu gibi kalır; iptal sayfası gerekmez |
| 4 | Stripe.js `js.stripe.com`'dan, paketlenmez | `@stripe/stripe-js` paketi | PCI gereği; bütçe etkilenmez (ölçüldü: 20,3 KB) |
| 5 | Stripe sır döndürmezse 502 | boş sırla formu açmayı denemek | Beklenmeyen durum açık hata olsun |
| 6 | **Para birimi her zaman zorlanır** (ülke bilinmese de ana birim) — M1c karar 12 değişti | "ülke desteklenmiyorsa para birimi verme, Stripe ana birimi açar" | **Ölçüldü (gerçek tarayıcı):** zorlanmayan çok para birimli fiyatı Stripe kendi konum tahminiyle yerelleştirdi — test e-postası `+location_TR` ile ödeme TRY 349,99, paywall €9,99. Tuzak §50 |
| 7 | Apple/Google Pay **ertelendi** (kullanıcı) | yerelde yarım ölçüm | Alan adı doğrulaması sunucu ister; gömülü form cüzdanları kendisi gösterecek |

## Test

`GomuluOdemeTest` (3): gomulu → `embedded_page`, `return_url` başarı sayfası, success/cancel
yok, yanıt `{clientSecret, anahtar}` (sözleşme); Stripe sır döndürmezse 502; gomulu
istenmezse ve sunucuda anahtar yoksa barındırılan url. `ParaBirimiTest`: JP ve ülkesiz
istekte de ana birim (`usd`) zorlanır. Oynatıcı (Stripe yükleyicisi sahte): yüklendiyse
`gomulu: true`, panel açılır, form panelin içine takılır, anahtar ve sır doğru, yönlendirme
yok; Kapat → form yok edilir, paywall'da kalınır; Escape kapatır (odak kapat düğmesinde —
efekt koştuktan sonra, §33); yüklenemezse `gomulu` yok, barındırılan sayfaya gidilir.

## Kırma (10 deneme, hepsi kırmızı)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | sunucu gomulu'yu yok sayar | 🔴 |
| K2 | gömülüde success_url de gider | 🔴 |
| K3 | anahtarsızken de gömülü | 🔴 |
| K4 | sır denetimi yok | 🔴 |
| K5 | huni gomulu istemez | 🔴 |
| K6 | huni sırrı yok sayıp yönlenir | 🔴 2 |
| K7 | kapatınca form yok edilmez | 🔴 |
| K8 | Escape yok | 🔴 |
| K9 | Stripe.js yokken de gomulu ister | 🔴 8 |
| K10 | bilinmeyen ülkede para birimi zorlanmaz (M1c'nin eski hâli) | 🔴 |

M1c kayıttaki K10 ("birim yokken de fiyat okunur") yanlış kararı ölçüyordu; geçersiz.

## Gerçek istek (Chrome + gerçek Stripe test API)

- `app:huni yayinla keepchat sablon-fitness` → canlı huni; betikle 16 adım, şart onayı, öde.
- Panel açıldı, içinde `js.stripe.com/.../embedded-checkout-inner` iframe'i; ilk denemede
  **TRY 349,99** (paywall €9,99) → karar 6 → çekirdek yeniden başlatıldı → aynı e-postayla
  **€9,99**.
- Stripe'ın yayımlanmış test kartı (4242…, test modu, yerel uygulama) ile Subscribe →
  başarı sayfası ("Teşekkürler! Aboneliğiniz başlatılıyor…").
- Stripe: oturum `embedded_page`, `complete`/`paid`, eur 999, `metadata {app, sart_onayi: 1}`,
  abonelik oluştu; bizim DB'de oturum ziyarete bağlı (`odeme_oturumlari`).
- Huni JS 20.357 B gzip (bütçe 30.720).

## Kontrol

`bin/kontrol` **YEŞİL**: 564 çekirdek testi / 4771 doğrulama. Ara kırmızı: para birimi
zorlaması önce fiyat okuduğu için `istekler[0]`'a bakan test sıraya bağlıydı — istek adresle
bulunuyor.

## Açık

Ç113.
