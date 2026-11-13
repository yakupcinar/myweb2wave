# M1a — fiyat kataloğu: planlar veritabanında, panelden Stripe'a

2026-11-03 · karar `pre-setup.md` A14 · plan `web2wave-platform-yapisi.md` § Ek 2026-11-03 (M1).
M1 üç bloğa bölündü: **M1a katalog** (bu) · M1b teklifler (kartlı deneme,
ücretli giriş dönemi, ilk dönem indirimi) · M1c sabit tutarlı çok para birimi
(Adaptive Pricing kapanır).

## Ölçüm (önce)
- Planlar `config/packages/uygulamalar.yaml`'da: `keepchat: {monthly: '%env(STRIPE_PRICE_KEEPCHAT_MONTHLY)%'}`
  — tek plan; yeni plan = yaml + ortam değişkeni + dağıtım (`deploy/compose.yaml` `:?` zorunlu).
- Sözleşmede `plan` **enum [monthly]** iki yerde (ödeme isteği, `PaywallAdimi`) → AI ve Puck başka plan üretemez.
- `%uygulamalar%` 9 sınıfta uygulama kaydı olarak da okunuyor (`isset`); plan haritası olarak 5 yerde
  (`Fiyatlar`, `CheckoutController`, `Huniler` ×2, `PanelHuniApiController`).
- Gerçek KeepChat fiyatı (Stripe test): 9,99 EUR / ay.

## Karar
- **`planlar` tablosu** (app, anahtar, ad, stripe_urun, stripe_fiyat; PK app+anahtar; anahtar biçimi CHECK).
  Reddedilen: yaml + ortam değişkeninde çok plan — her yeni plan kod/dağıtım ister, panel yok.
- **Tutar tabloda YOK** — gösterilen fiyat yine Stripe fiyat nesnesinden (`Fiyatlar`, L10 korunur).
  Reddedilen: tutarı tabloda tutmak (iki kaynak, Stripe'la ayrışabilir — Ç21'in aynısı).
- **Plan = Stripe ürünü; fiyat değişimi = aynı üründe yeni fiyat**, eski fiyat `active=false`
  (Stripe fiyatı değiştirilemez; mevcut aboneler eski fiyatta kalır). Eski fiyatın kapatılması
  düşerse katalog yine yeni fiyatta (günlüğe uyarı) — huni eski fiyatı açmaz.
- **Anahtar değişmez, plan silinmez** (yayındaki huniler anahtarla bağlı). Reddedilen: silme — yayındaki
  paywall'ı sessizce `unknown_plan`'a düşürürdü.
- Panel: para birimi `EUR/USD/GBP/TRY`, aralık `week/month/year` (M1c'ye kadar plan tek para birimi).
  Tutar en çok 2 ondalık (9.999 sessizce 10,00 olmasın); 0 yok (deneme M1b'de).
- Önce "plan var mı" bakılır → Stripe'ta boşuna ürün açılmaz; asıl bekçi PK (yarışta sahipsiz ürün
  kalabilir, günlüğe yazılır — tek yönetici, ölçülmüş risk değil).
- **Eski fiyat taşıma: `app:plan-ice-aktar <app> <anahtar> <price_…>`** (ürün kimliği fiyattan).
  Ortam değişkeni `STRIPE_PRICE_KEEPCHAT_MONTHLY` kaldırıldı (`.env`, `.env.test`, `deploy/`).
  Reddedilen: göçte ortam değişkenini okumak (göç ortama bağlanmamalı) · katalog boşsa yaml'a düşen
  yedek zincir (tuzak §12).
- Sözleşme: `PlanAnahtari` (desen), `PanelPlanlari`; uçlar `GET/POST /panel/api/uygulamalar/{app}/planlar`,
  `PUT …/planlar/{anahtar}/fiyat`. Puck plan listesi ve AI şeması katalogdan (enum kalktı; çekirdek doğrular).
- Testlerin ortak `monthly` planı `tests/bootstrap.php`'de yazılır (eskiden `.env.test`); önceki
  koşudan kalan planlar orada silinir.

## Test
`PanelPlanTest` (16): girişsiz kapalı · yeni plan → Stripe ürün + fiyat istek değerleri (9.99 → 999, `eur`,
`year`, fiyat açılan ürüne bağlı), katalog satırı, listede fiyat Stripe'tan · var olan anahtar Stripe'a
gitmez · DB kısıtları doğrudan (PK, CHECK; tuzak §4) · 7 geçersiz alan (Stripe'a gitmez) · CSRF/404 ·
Stripe kapalı → yazılmaz · fiyat değişimi (aynı ürün, eski para birimi/aralık, eski fiyat kapanır, satır
yeni kimlikte) · ödeme + huni doğrulaması + düzenleyici plan listesi katalogdan · içe aktarma (ikinci kez
yazılmaz, bilinmeyen uygulama). Panel `Planlar.test.tsx` (4): fiyat biçimi, okunamayan fiyat, yeni plan
gövdesi (tutar sayı, jeton), fiyat değişimi gövdesi, çekirdek hatası.

Yan bulgu (tuzak §44): `step="0.01"` + `min="0.01"` happy-dom'da kayan nokta yüzünden 59'u geçersiz sayıp formu
GÖNDERMİYOR (hata yok; ölçüldü — `step="any"` ile gitti). Alan `step="any"`, sınır çekirdekte. Olumlu
iddia düştüğü için yakalandı; "istek gitmez" diyen olumsuz bir test yalancı yeşil olurdu.

## Kırma (15 deneme, hepsi kırmızı)
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `Fiyatlar` kataloğu okumaz | 🔴 3 test (liste fiyatı, FiyatTest) |
| K2 | ödeme sabit haritadan | 🔴 ödeme katalogu okur |
| K3 | huni doğrulaması sabit listeden | 🔴 katalogda olmayan plan kabul |
| K4 | "plan var mı" bakılmaz | 🔴 Stripe'a gitti |
| K5 | ana → küçük birim ×100 yok | 🔴 2 test |
| K6 | eski fiyat kapatılmaz | 🔴 fiyat değişimi |
| K7 | yeni fiyat para birimi sabit `eur` | 🔴 fiyat değişimi |
| K8 | katalog satırı güncellenmez | 🔴 fiyat değişimi |
| K9 | 2 ondalık sınırı yok | 🔴 üç ondalık |
| K10 | Stripe hatası başarı döner | 🔴 Stripe kapalı |
| K11 | düzenleyici plan listesi sabit `['monthly']` | ilk deneme 🟢 **TUTMADI** — hiçbir test ölçmüyordu (sabit liste testteki tek planla aynıydı); test eklendi (iki planlı katalog) → 🔴. İkinci denemede filtre yeni testi kapsamıyordu (koşulmadı) → düzeltildi → 🔴 |
| K12 | panel tutarı metin gönderir | 🔴 |
| K13 | okunamayan fiyat boş yazılır | 🔴 |
| K14 | fiyat değişiminde fazla alan | 🔴 |
| K15 | DB'de CHECK kaldırıldı (test DB, geri eklendi) | 🔴 biçimsiz anahtar yazıldı |
Biçimlendirici + PHPStan düzeltmelerinden sonra K1–K14 yeniden koşuldu: hepsi 🔴.

## Gerçek istek
- Dev DB göçü + `app:plan-ice-aktar keepchat monthly <gerçek test fiyatı>` → `GET /huni/keepchat`
  HTTP 200, `fiyatlar.monthly = 9.99 EUR month` (gerçek Stripe test API).
- `POST /checkout/sessions` `monthly` → 201 + `checkout.stripe.com/c/pay/cs_test_…`; `yearly` → 422 `unknown_plan`.
- `olustur`'un parametreleri gerçek Stripe test API'sine (stripe-mock yalnız biçimi doğrular):
  ürün + fiyat 5999 `eur` `year`, fiyat aynı üründe, `livemode=false`; sonra ikisi de `active=false`
  ("M1a ölçüm (silinecek)" Stripe test hesabında arşivli duruyor).
- Panel sayfası kullanıcı tarayıcısında denenmedi (giriş kullanıcının hesabı) — Ç96.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 437 çekirdek testi / 3.560 doğrulama (macOS, Docker Desktop, ~2 dk 40 sn;
disk %97 dolu, 6,5 GB boş — kırmızı görülmedi).

## Açık
Ç96 (panel sayfası tarayıcıda) · dağıtımda ilk koşu: `app:plan-ice-aktar` (`dagitim.md` § Açık).
