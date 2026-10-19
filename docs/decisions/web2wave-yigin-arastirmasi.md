# web2wave nasıl kurulmuş — yığın araştırması, 2026-10-19

> Amaç: yığın kararına (pre-setup §B) girdi. Yalnız **dışarıdan gözlenebilen**
> şey ölçüldü; korumalı uçlar (`app.web2wave.com` → Cloudflare 403) aşılmaya
> çalışılmadı.

## Ölçümler

| Katman | Ölçüm | Bulgu |
|---|---|---|
| Pazarlama sitesi | `curl -sL www.web2wave.com` → HTML'de `webflow.*.js`, "Last Published" yorumu | **Webflow**, Cloudflare arkasında |
| Panel | `curl -sI app.web2wave.com` → 403, `server: cloudflare` | bot korumalı, ölçülmedi |
| Huni / API | `curl -sI api.web2wave.com/<yok>` → `XSRF-TOKEN=eyJpdiI6…` (Laravel şifreli çerez biçimi) + `quiz_session` (Laravel'in `<APP_NAME>_session` varsayılanı) | **PHP / Laravel** |
| Huni sayfaları | aynı 404 sayfası: sunucuda üretilmiş HTML, `window.user_id` gömülü, `jquery 3.7.1`, `js/functions.js`, `js/common.js`, Sentry | **sunucuda HTML + jQuery**, SPA yok |
| Uygulama SDK'sı | `github.com/web2wave/web2wave_swift` (lisans: YOK → tüm hakları saklı, **kod alınmaz**, kural 12) | aşağıda |

**SDK davranışı** (yalnız davranış, kendi cümlemizle):
- Taban `https://api.web2wave.com`, başlıkta `api-key`.
- `GET /api/user/identify` — **giriş yok**; başlıkta ekran boyutu, saat
  dilimi, iOS sürümü, cihaz modeli. Web'de ödeyen kişiyi uygulamayı yükleyen
  cihazla **olasılıksal parmak iziyle** eşliyor (ertelenmiş derin bağlantı).
- `GET /api/user/subscriptions?user=…` → `hasActiveSubscription`.
- `GET|PUT /api/user/properties` — kullanıcı özellikleri.
- RevenueCat / Adapty / Qonversion / Apphud / Superwall profil kimliği bağlama.
- `showWebView` — huniyi uygulama içinde WebView'de açma.

## Neden bu yığın — ÇIKARIM (kaynak bulunamadı)

İş ilanı ya da yazı bulunamadı; aşağısı gözlemden çıkarım:
- Huni sayfası reklamdan gelen soğuk trafik karşılar → ilk boyama hızı
  dönüşümü belirler → sunucuda HTML + az JS (jQuery) mantıklı.
- Ürün "quiz" olarak başlamış (`quiz_session`); Laravel hızlı CRUD + panel.
- Pazarlama sitesi ürünü kodlamadan ayrı (Webflow) — mühendislik huniye.

**Ders:** dil farklılaştırıcı değil. Uygulama Swift olsa da huni bir **web**
ürünü; uygulama onunla yalnız HTTP API üzerinden konuşur. Dil "uygulamaya
özel" seçilmez; huniyi hızlı sunabilen ve ekibin akıcı olduğu dil seçilir.

## Yığın seçenekleri — bu proje için

| | Go (`net/http` + `html/template` + Postgres) | Laravel (Blade + Cashier + Postgres) |
|---|---|---|
| Huni sayfası hızı | sunucuda HTML, tek binary | sunucuda HTML (web2wave'in yolu) |
| Abonelik durumu (webhook) | elle: `stripe-go` + 4-5 olay | **Cashier hazır** (Stripe; Paddle için ayrı paket) |
| Senin akıcılığın | KeepChat'in güncel yığını; gopls eklentisi açık | KeepChat ilk yığını; sonra bırakıldı |
| Dağıtım | KeepChat'in OCI + Docker Compose yolu aynen | PHP-FPM + web sunucusu, yeni yol |
| KeepChat'te Laravel'i bıraktıran sebep | — | kalıcı WebSocket süreci (R1) — **burada yok** |
