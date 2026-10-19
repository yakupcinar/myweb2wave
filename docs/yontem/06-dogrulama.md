# 6 · Doğrulama ve CI

## Katmanlar: her biri öncekinin göremediğini görür

| Katman | Ne görür | Ne göremez |
|---|---|---|
| Birim/işlev testleri (PHPUnit, stripe-mock) | kararlar, kısıtlar, sözleşme | gerçek Stripe, tarayıcı, ağ |
| `bin/kontrol` | biçim, tip, statik analiz, test, derleme, sözleşme güncelliği | yerel makinenin "kendiliğinden" sağladıkları |
| CI (GitHub Actions) | temiz ortam | gerçek Stripe hesabı |
| Gerçek istek (curl + Stripe test API) | gerçek yanıt, Stripe tarafında geri okuma | tarayıcı davranışı |
| Tarayıcıda test kartları | kullanıcının gördüğü, 3DS, ret mesajları | — |

## `bin/kontrol` — tek komut, borusuz

Sıra: ortam (disk, DB, stripe-mock) → bağımlılık kurulumu → biçim →
sözleşme güncelliği → statik analiz (PHPStan **max**, `astro check`) →
test DB + göç → PHPUnit → derleme. Kırmızı adımın günlüğünün son 40
satırı basılır; atlanan test sayısı ayrıca yazılır. Ayrıntı:
`.claude/skills/kontrol/SKILL.md`.

Kontrolün kendisi de kırılarak ölçüldü: biçimi bozuk dosya, tip hatası,
sözleşme dışı alan → her biri ilgili adımı kırmızı yaptı.

## CI = aynı betik

`.github/workflows/kontrol.yml` yalnız `bin/kontrol`'ü koşar — iki ayrı
tanım kayar, tek betik kaymaz. Eylemler commit SHA'sına sabit.

- **İlk gerçek koşu gerçek açık buldu:** betik `composer install`
  yapmıyordu; yerelde `vendor/` vardı, temiz ortamda yok (EXIT=127).
- **Kırmızı kodun değil faturanın olabilir:** özel depoda koşu 4 sn'de,
  adım sayısı 0 ile düştü; sebep faturalandırma. "Koşmadı" ≠ "düştü".
- **CI'ın kendisi kırma denemesiyle ölçüldü:** yeşil → bilerek bozuk commit
  → kırmızı (doğru test) → `git revert` → yeşil (`ci.md`).
- Her iş dalda + PR'da; CI yeşilse birleştirilir; birleşen main'in koşusu
  da okunur (bir kez geçici ağ hatası — Ç22).

## Gerçek istekle doğrulama

- Uç gerçek sunucu üzerinden `curl`'lenir; yanıtın uygulamadan geldiği
  başlıkla işaretlenir (`Server: FrankenPHP Caddy`).
- Stripe'a giden oturum **Stripe'tan geri okunur** (mod, tutar, fiyat,
  dönüş adresi, `metadata.app`) — "201 döndü" yetmez.
- Webhook: `stripe-cli listen` ile gerçek olay, sonra **aynı olayı yeniden
  gönderme**; veritabanında sayım.
- Arka plan süreci "hazır" satırı görülmeden kullanılmaz (tuzak §22).

## Tarayıcıda test kartları

Stripe'ın yayımladığı test kartlarıyla senaryolar (`huni-paywall.md`):
başarılı, 3DS reddet/onayla, kart reddi, yetersiz bakiye, "kaydedilir ama
tahsil edilmez", vazgeçme, çekirdek kapalı, Stripe gerçekten hata veriyor.
Her senaryoda ekran **ve** veritabanı/Stripe olay kaydı ölçülür.
Tarayıcı aracının sınırları: 3DS penceresi başka kökenli çerçevede
(kullanıcı tıkladı, sonuç olay kaydından ölçüldü); site izinleri oturum
içinde değişebiliyor; sayfa betiği yüklenmeden yapılan tık istek
göndermeyebilir (Stripe'taki oturum sayısıyla ayırt edildi).

## Sırlar ve açık depo

- Anahtarlar `core/.env.local`'de (git dışı — `git check-ignore` ile ölçülür).
- Depo herkese açık (C5). Açılmadan önce **tüm geçmiş** tarandı; her
  commit öncesi hazırlanan değişiklikte anahtar deseni taraması (0 olmalı).
- Ajan anahtarı ekrana basmaz: önek + uzunluk ölçülür (`sk_test_`, 107).
