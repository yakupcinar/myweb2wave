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
| http://localhost:4321 | Huni (ziyaretçi sayfaları, Astro) | compose dışında `huni-dev` kabı |
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
| `/panel/huniler` | **Huniler** | Uygulama başına huniler (Düzenle · Aç · Varsayılan yap · Sil/Arşivle), yeni huni, yönlendirme kuralları | Twig |
| `/panel/huniler/{app}/{ad}` | (Düzenle) | Sürükle-bırak düzenleyici: adımlar, tema/zemin/kart, Kaydet · Önizle · Yayınla · Sürümler, AI sohbeti | React + Puck |
| `/panel/baglam` | **Ürün bağlamı** | Uygulama başına ürün, kategori, kitle, ton, yasaklar (AI bunu okur) | React |
| `/panel/anahtarlar` | **API anahtarları** | MCP için kişisel anahtar oluştur / iptal et | React |
| `/panel/cikis` | **Çıkış** | | |

Örnek: http://localhost:8081/panel/huniler/keepchat/genel

## 3. Huni sayfaları (ziyaretçi, giriş yok)

| Adres | Ne |
|---|---|
| http://localhost:4321/keepchat/ | Reklam adresi — hangi huninin açılacağını kurallar seçer, tutmazsa varsayılan |
| `…/keepchat/?k=fitness` · `?utm_source=` · `?utm_campaign=` · `?utm_content=` | Kurallara giden seçim parametreleri |
| `…/keepchat/?huni=<ad>` | Belirli bir yayınlanmış huniyi doğrudan aç (ziyaret sayılır) |
| `…/keepchat/?huni=<ad>&onizleme=<anahtar>` | Taslak önizlemesi (1 saat, ziyaret sayılmaz, ödeme kapalı) |
| `…&yayin=1&onizleme=<anahtar>` | Yayındaki sürümün önizlemesi (panelde "Aç") |
| `…&adim=<kimlik>&ekran=1` | AI ekran görüntüsü için: belirli adımdan, şeritsiz |
| http://localhost:4321/keepchat/basarili | Ödeme sonrası |
| http://localhost:4321/keepchat/iptal | Ödemeden vazgeçince |

## 4. API uçları (çekirdek, :8081)

Sözleşme: `contract/openapi.yaml`.

| Grup | Uçlar | Kim çağırır |
|---|---|---|
| Huni | `GET /huni/{app}` · `GET /huni/{app}/taslak` · `POST /huni/ziyaretler` · `POST /huni/ziyaretler/{ziyaret}/ilerleme` · `POST·DELETE /huni/ziyaretler/{ziyaret}/izin` · `POST /huni/giris-baglantisi` | huni sayfası (anahtarsız) |
| Ödeme | `POST /checkout/sessions` · `POST /webhooks/stripe` | huni · Stripe |
| Uygulama | `POST /apps/{app}/baglama/kod` · `POST /apps/{app}/baglama/dogrula` · `POST /apps/{app}/giris/kullan` · `GET /apps/{app}/kullanicilar/{kullanici}/hak` | mobil uygulama sunucusu (uygulama anahtarı) |
| Panel API | `/panel/api/rapor` · `/panel/api/uygulamalar` · `…/{app}/baglam` · `…/{app}/anahtarlar` · `/panel/api/ai/durum` · `/panel/api/ai/huniler/{app}/{huni}/sohbet` | panel (oturum + CSRF) |
| Panel huni işlemleri | `/panel/huniler/{app}/…` (yeni, taslak, durum, yayinla, sürüm geri yükle, varsayılan, kaldır, kurallar) · `/panel/medya/{app}` | panel (oturum + CSRF) |
| Diğer | `GET /medya/{id}.webp` · `GET /health` · `/mcp` | herkes · izleme · Claude |
