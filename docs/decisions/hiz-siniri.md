# Hız sınırı — herkese açık uçlar IP başına (2026-11-17)

Kullanıcı isteği: "ödeme oturumuna hız sınırını ekle; sunucuyu yorma amaçlı istekleri de yakalayalım, gerekli
olanları bulalım, yapılmadıysa limit getirelim."

## Ölçülen başlangıç

- Hız sınırı yalnız üç yerde vardı: `/huni/ziyaretler` (IP, dakikada 120, Ç34), `/mcp` (anahtar başına), panel AI
  sohbeti (saatlik). Panel girişinde `login_throttling` (dakikada 5).
- Alan içi sınırlar: e-posta kodu (e-posta başına 10 dk'da 3, kod başına 5 deneme), portal bağlantısı (e-posta başına),
  giriş bağlantısı (ödeme oturumu başına). Bunlar e-posta/oturum başına; IP'den çok farklı e-postayla gelen yükü
  durdurmuyor.
- Sınırsız herkese açık uçlar: ödeme oturumu (her istek Stripe'ta oturum açar), ek satış (kayıtlı karttan ödeme),
  portal ve kod e-postaları, giriş/doğrulama/portal jetonları, huni okuma/yazma uçları, yasal, medya, simge.

## Karar

[`App\Guvenlik\HizSiniri`](../../core/src/Guvenlik/HizSiniri.php): `kernel.request` öncelik 64. Yönlendirmeden (32)
önce: var olmayan adreslere tarama da sayılır. CORS'tan (nelmio, 250) sonra: tarayıcının ön isteği buraya gelmez.
Sınırlar [`rate_limiter.yaml`](../../core/config/packages/rate_limiter.yaml), IP başına, kayan pencere:

| Grup | Uçlar | Sınır |
|---|---|---|
| ödeme | `POST /checkout/sessions` | 10 / 10 dk |
| e-posta | `POST /huni/portal-baglantisi`, `POST /apps/*/baglama/kod` (ortak kova) | 5 / 10 dk |
| kimlik | `POST /huni/giris-baglantisi`, `/apps/*/giris/kullan`, `/apps/*/baglama/dogrula`, `/portal/*` | 30 / 10 dk |
| ek satış | `POST /huni/ek-satis` | 10 / 10 dk |
| genel | diğer her şey | 300 / dk |

Muaf: `/health`, `/webhooks/`, `/panel`, `/mcp`. Aşan istek `429 {"error":"too_many_requests"}` + `Retry-After`.
Sözleşmede ortak `CokIstek` yanıtı, 13 herkese açık yola eklendi; önceden kendi 429'u olan üç yolun açıklaması
güncellendi. Tipler yeniden üretildi.

## Neden böyle

- **Tek merkez, controller başına değil:** yeni bir herkese açık uç sınırsız kalamaz (genel sınır), adı unutulan uç
  olmaz. Reddedilen: her controller'a `RateLimiterFactory` (ziyaret ucundaki kalıp; o uç olduğu gibi kaldı).
- **Ödeme 10, backlog'daki 5 değil:** canlıda tek IP'den gerçek kullanıcı 2 dakikada 5 oturum açtı
  (`odeme_oturumlari`, 2026-11-17 06:53–06:55).
- **E-posta uçları ortak kova:** saldırgan uçtan uca geçip sınırı katlayamaz; Gmail'in günlük gönderim kotası.
- **Genel 300/dk:** bir huni ziyareti dakikada ~20–40 istek (tanım, ziyaret, ilerleme, medya, simge); mobil operatör
  ortak IP'si (CGNAT) arkasında ~10 eşzamanlı kişiye yer.
- **Muaflar:** Stripe/Postmark webhook'ları imzalı ve yeniden dener (429 olay kaybettirir); panel oturumlu, girişin
  kendi sınırı var; `/mcp` anahtar başına sınırlı; `/health` izleme.
- **Reddedilen:** Caddy'de sınır (standart imajda modül yok, özel derleme). Backlog'daki "ödeme için geçerli ziyaret
  zorunlu" ve "gizli tuzak alanı" bu blokta yok: A6'ya göre ziyaret tanınmasa da ödeme açılır — ayrı karar.
- **Sayaç havuzu `hiz.sayaclari`:** üretimde `cache.app` (dosya; tek FrankenPHP kabı). Testte bellek içi ve her
  istekte sıfırlanır, süitin `127.0.0.1`'i birbirini düşürmez; sınır testi kendi dosya havuzunu koyar (tuzak §61).

## Kırma denemeleri — 14/14 kırmızı (K11 ilk koşuda tutmadı → ölü kod)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | ödeme sınırı 10→11 | 1 düştü |
| K2 | ödeme eşlemesi yok (genel sınıra düşer) | 1 |
| K3 | kod ucu e-posta kovasında değil | 1 |
| K4 | `/portal/*` sınırsız | 1 |
| K5 | ek satış eşlemesi yok | 1 |
| K6 | genel 300→301 | 1 |
| K7 | anahtar IP değil sabit | 3 |
| K8 | öncelik 16 (yönlendirmeden sonra) | 2 |
| K9 | `/health` muaf değil | 1 |
| K10 | `/webhooks/` muaf değil | 1 |
| K11 | `OPTIONS` sayılıyor | **tutmadı** |
| K11b | öncelik 300 (CORS'tan önce) | 1 |
| K12 | `Retry-After` yok | 2 |
| K13 | sözleşmede ödeme 429'u yok | 1 hata |

K11: nelmio (250) tarayıcının ön isteğini benden önce yanıtlıyordu; `OPTIONS` denetimi hiç çalışmayan koddu →
kaldırıldı (ön istek olmayan `OPTIONS` taramadır, sayılmalı). Testin ölçtüğü şey sıralama; K11b onu kanıtlıyor.
Betik: `$TMPDIR/…/scratchpad/kir-hiz.py`.

## Ölçüm

`HizSiniriTest` 6 test, 1273 doğrulama. Tam kontrol ve canlı ölçüm: aşağıda.
- **Tam kontrol:** ilk iki koşu başka oturumun eşzamanlı süiti / `npm ci`'siyle kırmızı (aynı test DB'si, paylaşılan
  `node_modules`; düşenler eşzamanlılığa duyarlı `GidenWebhookTest`, `OlaylarTest` ve huni testleri). Tek başına
  33/33 yeşil. Üçüncü koşu `TEST_TOKEN=hiz`: **YEŞİL**, 786 çekirdek testi.
