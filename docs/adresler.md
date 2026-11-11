# Adresler — panel sayfaları, huni sayfaları, servisler

Yerel geliştirme adresleri (2026-10-28, çalışan sistemden okundu:
`bin/console debug:router`, `core/panel/src/uygulama/main.tsx`,
`funnel/src/pages/`). **Üretim adresi yok** — dağıtım ertelendi
(`PLAN.md`, `docs/decisions/dagitim.md`). Rota eklenince bu dosya da
güncellenir (kural 7); güncel liste:
`docker compose exec -T core bin/console debug:router`.

## 1. Servisler ve portlar

| Adres | Ne | Not |
|---|---|---|
| http://localhost:8081 | Çekirdek API + panel (Symfony / FrankenPHP) | sağlık: `/health` |
| http://localhost:4321 | Huni (ziyaretçi sayfaları, Vite + Preact) | compose dışında `huni-dev` kabı (`vite --host`) |
| http://localhost:8025 | Mailpit — geliştirmede giden e-postalar | gerçek kutuya gitmez |
| localhost:5435 | Postgres (`app` / `app`) | |
| http://localhost:8081/mcp | MCP sunucusu (Claude bağlantısı) | panelden alınan kişisel anahtar ister |
| `ekran:9222` (yalnız iç ağ) | Başsız Chrome — AI ekran görüntüsü | dışarıya port yok |
| http://localhost:8082 | `myweb2wave-ui-preview` kabı (çekirdek imajı, compose dışı) | amacı kayıtlı değil |

## 2. Panel sayfaları (yönetici girişi gerekir)

Sol menü sırasıyla:

| Adres | Menüde | Ne yapılır | Yapı |
|---|---|---|---|
| `/panel/giris` | — | Yönetici girişi (kullanıcı adı + şifre) | Twig |
| `/panel` | **Rapor** | Ziyaret, adım, ödeme, izin oranı | React |
| `/panel/huniler` | **Huniler** | Uygulama başına huniler (Düzenle · Aç · Varsayılan yap · Sil/Arşivle), yeni huni, yönlendirme kuralları | React (P2) |
| `/panel/kullanicilar` | **Aboneler** | Abonelikler (CRM): durum, maskeli e-posta, kaynak/kampanya, kişi bağlantısı; arama + durum süzgeci | React (P5) |
| `/panel/huniler/{app}/{ad}` | (Düzenle) | Sürükle-bırak düzenleyici: adımlar, tema/zemin/kart, Kaydet · Önizle · Yayınla · Sürümler, AI sohbeti | React + Puck, panel uygulamasının sayfası (P3) |
| `/panel/planlar` | **Planlar ve fiyatlar** | Fiyat kataloğu: plan aç (Stripe'ta ürün + fiyat), fiyatı değiştir (yeni Stripe fiyatı; aboneler eskisinde) | React (M1a) |
| `/panel/deneyler` | **Deneyler** | Deney taslağı, varyantlar, başlat / durdur, sonuçlar | React (M4a) |
| `/panel/webhooklar` | **Webhook'lar** | Giden webhook hedefleri: ekle, etkin/pasif, sil, teslim günlüğü | React (M7b) |
| `/panel/baglam` | **Ürün bağlamı (AI)** | Uygulama başına ürün, kategori, kitle, ton, yasaklar (AI bunu okur) | React |
| `/panel/anahtarlar` | **API anahtarları** | MCP için kişisel anahtar oluştur / iptal et | React |
| `/panel/yasal` | **Yasal metinler** | Yasal veriler + 4 belge × 2 dil; kaydet = yayınla | React (M9b1) |
| `/panel/ayarlar` | **Uygulama** | Canlıya geçiş adımları (`GET /panel/api/uygulamalar/{app}/canliya-gecis`), uygulama kimliği: görünen ad, URL şeması, App Store adresi, destek e-postası | React (M9a, P7c) |
| `/panel/cikis` | **Çıkış** | | |

Örnek: http://localhost:8081/panel/huniler/keepchat/genel

## 3. Huni sayfaları (ziyaretçi, giriş yok)

| Adres | Ne |
|---|---|
| http://localhost:4321/keepchat/ | Reklam adresi — hangi huninin açılacağını kurallar seçer, tutmazsa varsayılan |
| `…/keepchat/?k=fitness` · `?utm_source=` · `?utm_campaign=` · `?utm_content=` | Kurallara giden seçim parametreleri |
| `…/keepchat/?huni=<ad>` | Belirli bir yayınlanmış huniyi doğrudan aç (ziyaret sayılır) |
| `…/keepchat/?ulke=TR` | M1c: IP yerine bu ülkenin para birimiyle fiyat ve ödeme (yerelde IP ülke vermez) |
| `…/keepchat/?huni=<ad>&onizleme=<anahtar>` | Taslak önizlemesi (1 saat, ziyaret sayılmaz, ödeme kapalı) |
| `…&yayin=1&onizleme=<anahtar>` | Yayındaki sürümün önizlemesi (panelde "Aç") |
| `…&adim=<kimlik>&ekran=1` | AI ekran görüntüsü için: belirli adımdan, şeritsiz |
| http://localhost:4321/keepchat/basarili | Ödeme sonrası |
| http://localhost:4321/keepchat/iptal | Ödemeden vazgeçince |
| http://localhost:4321/keepchat/abonelik/ | M6b2: "Aboneliğimi yönet" — e-postaya tek kullanımlık portal bağlantısı |
| http://localhost:8081/portal/{jeton} | M6b2: e-postadaki bağlantı — onay sayfası (GET), "Portalı aç" → Stripe portalı (POST) |

## 4. API uçları (çekirdek, :8081)

Sözleşme: `contract/openapi.yaml`.

| Grup | Uçlar | Kim çağırır |
|---|---|---|
| Huni | `GET /huni/{app}` · `GET /huni/{app}/taslak` · `POST /huni/ziyaretler` · `POST /huni/ziyaretler/{ziyaret}/ilerleme` · `POST /huni/ziyaretler/{ziyaret}/eposta` (E1) · `POST·DELETE /huni/ziyaretler/{ziyaret}/izin` · `POST /huni/giris-baglantisi` · `POST /huni/portal-baglantisi` (M6b2) | huni sayfası (anahtarsız) |
| Ödeme | `POST /checkout/sessions` · `POST /webhooks/stripe` | huni · Stripe |
| Yasal sayfalar | `GET /yasal/{app}/{belge}/{dil}` — yalnız kaydedilmiş metin, yoksa 404 (M9b1) | herkes |
| Posta bastırma | `POST /webhooks/postmark` (HTTP Basic, `POSTMARK_WEBHOOK_SIFRE` boşsa 404) | Postmark |
| Uygulama | `POST /apps/{app}/baglama/kod` · `POST /apps/{app}/baglama/dogrula` · `POST /apps/{app}/giris/kullan` · `GET /apps/{app}/kullanicilar/{kullanici}/hak` · `POST /apps/{app}/kullanicilar/{kullanici}/portal` (M6b1) · `GET /apps/{app}/olaylar?imlec=&sinir=` (M7c1, olay akışı) | mobil uygulama sunucusu / dış sistem (uygulama anahtarı) |
| Panel API | `/panel/api/rapor` · `/panel/api/kullanicilar` · `/panel/api/uygulamalar` · `…/{app}/baglam` · `…/{app}/ayarlar` (M9a) · `…/{app}/varsayilanlar` (GET, DELETE) + `…/{app}/huniler/{ad}/ornek` (M9c) · `…/{app}/yasal` (+ `/veriler`, `/{belge}/{dil}`) (M9b1) · `…/{app}/anahtarlar` · `…/{app}/huniler` (+ `/{ad}`, `/{ad}/varsayilan`, `/{ad}/duzenleyici`) · `…/{app}/kurallar` (+ `/{id}`, `/{id}/tasi`) · `/panel/api/ai/durum` · `/panel/api/ai/huniler/{app}/{huni}/sohbet` · `…/{app}/deneyler` (+ `/{ad}/durum`, `/{ad}/sonuclar`) · `POST·DELETE /panel/api/abonelikler/{sub}/iptal` · `POST …/iade` (M6a) · `…/{app}/webhooklar` (+ `/{id}`, `/{id}/teslimler`, `/{id}/teslimler/{teslim}/yeniden`) (M7b) | panel (oturum + CSRF) |
| Panel huni işlemleri | `/panel/huniler/{app}/…` (yeni, taslak, durum, yayinla, sürüm geri yükle, varsayılan, kaldır, kurallar) · `/panel/medya/{app}` | panel (oturum + CSRF) |
| Diğer | `GET /medya/{id}.webp` · `GET /health` · `/mcp` | herkes · izleme · Claude |
