# Blok: Dağıtım dosyaları (test modu) — 2026-10-20

## Ne yapıldı
Tek sunucuya Docker ile üretim ortamı (C6): `deploy/compose.yaml` —
`web` (Caddy 2.11.7, Let's Encrypt TLS + derlenmiş statik huni), `core`
(çekirdeğin `uretim` imajı, kök olmayan kullanıcı, iç ağ :8080), `db`
(Postgres 18.6, port yayınlanmaz). `deploy/dagit.sh` ön kontrol → derleme →
yedek → göç → servisler → **dışarıdan TLS ile ölçüm** (`/health` sürümü ==
commit, huni 200). `deploy/yedek.sh` (pg_dump, 14 gün, cron),
`deploy/sunucu-hazirla.sh` (Docker, ufw, ssh sertleştirme, kullanıcı, cron),
`deploy/README.md` (adımlar). Çekirdek Dockerfile iki aşamalı: `taban`
(geliştirme/CI — `compose.yaml` bunu hedefler) ve `uretim`.

**Henüz sunucu yok** — her şey yerelde, gerçek alan adı yerine
`api.localhost` / `huni.localhost` (Caddy'nin yerel CA'sı, `CURL_CA_BUNDLE`
ile güvenildi; betik değişmedi). `sunucu-hazirla.sh` yalnız `bash -n` ile
denetlendi, **koşmadı** (Ubuntu sunucusu gerekiyor).

## Neden böyle
- **Caddy önde, FrankenPHP içeride:** FrankenPHP de TLS alabilir ama huniyi
  de sunmak gerekiyor; iki alan adı + statik dosya + güvenlik başlıkları tek
  yerde. Reddedilen: FrankenPHP'nin hem API hem huniyi sunması (statik huni
  PHP sürecine bağlanır, B2'nin ayırma gerekçesine ters) · CDN (henüz alan
  adı/hesap yok; huni statik olduğu için sonra taşınabilir).
- **Huni adresi derlemede:** `PUBLIC_CORE_URL` derleme argümanı; boşsa
  derleme durur — yoksa sayfa sessizce `funnel/.env`'deki localhost'a gider.
- **Sürüm = commit:** `APP_VERSION` `git rev-parse` ile; ağaç kirliyse
  dağıtım durur (commit edilmemiş kod "o sürüm" diye yayılmasın, kural 10).
- **Göçten önce yedek:** göç bozarsa geri dönülecek nokta.
- **Ön kontrol değerleri basmaz:** yalnız önek/uzunluk; çıktı log'a gidiyor.

## Ölçüm (yerel, macOS, Docker Desktop)
- `dagit.sh` iki kez uçtan uca: `DAGITIM TAMAM sürüm=d2d26e8c66d2`, sonra
  `6e413d576764`; `/health` → `{"status":"ok","db":"ok","version":"<sha>"}`
  TLS üzerinden. İlk koşu TLS doğrulamada durdu (yerel CA tanınmıyordu) —
  doğru davranış.
- Göç: 3 göç uygulandı, ikinci dağıtımda "yok" (`--allow-no-migration`).
- TLS ile gerçek istekler: huni kökeninden CORS ön isteği
  `access-control-allow-origin: https://huni.localhost`; yabancı kökene
  başlık yok · anahtarsız hak ucu 401 · imzasız webhook 400 · bilinmeyen yol
  404 genel sayfa (iz/dosya yolu 0) · `http://` → 308 `https://`.
- İmaj: kullanıcı `cekirdek`, `.env*` olarak yalnız `.env` (sırsız),
  `tests/` ve `vendor/phpunit` yok, `display_errors` kapalı, `APP_ENV=prod`.
  Huni çıktısında `api.localhost` var, `localhost:8081` yok.
- Yedek: cron gibi **boş ortamla** (`env -i`) alındı, 9884 bayt; boş bir
  veritabanına `pg_restore` 0 → 6 tablo, 3 göç kaydı.
- `bin/kontrol` (geliştirme imajı `taban` hedefiyle): YEŞİL, 41 test.
- ⚠️ Yerelde `nc localhost 5432` açık çıktı — üretim db'si değil: db durdurulunca
  da açık; Mac'te kurulu PostgreSQL 17 (tuzak §11: yanıtın kimden geldiği).

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `STRIPE_SECRET_KEY=sk_live_…` | durdu, derlemeye geçmedi ✅ |
| K2 | `MAILER_DSN=null://null` | durdu ✅ |
| K3 | `APP_SECRET` boş | compose `:?` durdurdu ✅ |
| K4 | `APP_SECRET` kısa | durdu ✅ |
| K5 | `STRIPE_WEBHOOK_SECRET` `whsec_` değil | durdu ✅ |
| K6 | çalışma ağacında değişiklik | durdu ✅ |
| K7 | compose'da sabit eski sürüm (commit'li) | servisler kalktı, **ölçüm durdurdu**: `version":"eski-surum"` ≠ commit ✅ — geri alındı, yeniden dağıtım TAMAM |
| K8 | huni `PUBLIC_CORE_URL` boş | derleme `test -n` adımında düştü ✅ |
| K9 | `core/.dockerignore` kaldırıldı | **TUTMADI** — `.env.local` yine imaja girmedi. Sebep: asıl bekçi Dockerfile'daki açık `COPY` listesi |
| K9b | `COPY` listesi → `COPY . ./`, dockerignore var | `.env.local` girmedi ✅ (ikinci bekçi çalışıyor) |
| K9c | `COPY . ./` + dockerignore yok | `.env.local`, `.env.test`, `tests` imajda ✅ — iki katman ölçüldü, yorum düzeltildi |
| K10 | db kapalıyken yedek | çıkış 1, yarım dosya bırakmadı (3 → 3) ✅ |

Ayrıca kırma dışı bulgu: `yedek.sh` cron'dan (dagit dışında) çağrılınca
compose zorunlu `APP_VERSION` yüzünden düşüyordu — dagit içinden çalıştığı
için ilk koşuda görünmedi; yer tutucu ile düzeltildi, `env -i` ile ölçüldü.

## Açık
Ç33 (yedek aynı diskte) · Ç30 (gerçek e-posta sağlayıcısı — dağıtım
öncesi gerekli, `null://` ön kontrolde reddediliyor) · sunucuda ilk koşu
(`sunucu-hazirla.sh`, gerçek alan adı, Let's Encrypt, Stripe webhook).
