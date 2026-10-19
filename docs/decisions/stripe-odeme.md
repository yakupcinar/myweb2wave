# Blok: Stripe test ödemesi (çekirdek) — 2026-10-19

## Ne yapıldı
- `POST /checkout/sessions` `{"plan":"monthly"}` → `201 {"url": "https://checkout.stripe.com/…"}`;
  bilinmeyen plan 422, bozuk JSON 400 — iki durumda da Stripe'a istek gitmez.
- `POST /webhooks/stripe` — imza doğrulanır (400), olay işlenir (200).
- `subscriptions` + `processed_webhook_events` (göç `Version20261019120000`).
- Testler stripe-mock'a (resmi, MIT) gider; Stripe'a giden istek
  `KayitliStripeIstemcisi` ile kaydedilip DEĞERLER ölçülür.

## Neden böyle
- **İstemci yalnız plan adı gönderir**; fiyat ve dönüş adresi sunucuda —
  istemciden dönüş adresi almak açık yönlendirme.
- **Durumu yalnız abonelik olayları yazar**, `checkout.session.completed`
  yalnız e-postayı bağlar; daha eski olay daha yeniyi ezmez
  (`status_event_created`) — Stripe sıra garanti etmiyor (tuzak §13).
- **Tekrar (R3):** olay kimliği aynı işlemde PK'li tabloya; ikinci kopya
  kısıtta düşer, 200 döner. Uygulama kontrolü değil KISIT asıl kapı.
- **DBAL + düz SQL**, ORM değil: iki tablo, `ON CONFLICT … WHERE` SQL'de açık.
- **Webhook senkron işleniyor** (Messenger yok) — Ç17.
- Reddedilen: durumu `checkout.session.completed`'dan "active" yazmak —
  ödeme tamamlandı ≠ abonelik aktif (ör. `incomplete`), ve sırasız gelirse
  `past_due`'yu ezer (S8 tam bunu ölçüyor).

## Yolda ölçülenler
- **PHPStan max ölü catch buldu:** `catch (\JsonException)` — Symfony 7.4
  `toArray()` kendi `HttpFoundation\Exception\JsonException`'ını atıyor.
  Önce test (bozuk JSON → 400) yazıldı, catch'siz **400** ölçüldü, catch silindi.
- **Sıra kirliliği (tuzak §13):** iskelet bloğundaki 503 testi
  `DATABASE_URL`'i port 1'e çevirip geri koymuyordu; tek DB testiyken yeşildi,
  webhook testleri gelince 6 test `port 1 … refused` ile düştü. `tearDown`
  ile geri konuyor.
- `stripe-cli listen` 1.53'te `--events` zorunlu; `docker compose run -d`
  süreç ölse de 0 döndü → tuzak §22.

## Kırma denemeleri

| # | Bozulan karar | Sonuç |
|---|---|---|
| S1 | mod `payment` | 🔴 `testAylikPlan…` |
| S2 | fiyat sabit | 🔴 `testAylikPlan…` |
| S3 | dönüş adresi yapılandırmasız | 🔴 `testAylikPlan…` |
| S4 | plan beyaz listesi yok | 🔴 `testBilinmeyenPlan422…` (+uyarı) |
| S5 | imza doğrulanmıyor | 🔴 `testGecersizImza400…` |
| S6 | olay kimliği kaydedilmiyor | 🔴 `testAyniOlayIkiKez…` |
| S7 | sıra koruması (`WHERE`) yok | 🔴 `testEskiOlayYeniDurumuEzmez` |
| S8 | ödeme olayı durum yazıyor | ilk deneme **SAHTE** (PHP sözdizimi, 0 iddia, 11 hata); geçerli PHP ile **TUTMADI** (0 test) → test suçlandı: e-posta testi ödeme olayını önce gönderiyordu, çakışma yolu koşmuyordu → `testOdemeOlayiDurumYazmaz` yazıldı → 🔴 |
| S9 | tekrar yakalanmıyor | 🔴 `testAyniOlayIkiKez…` (500) |
| S10 | DB'de PK kaldırıldı | 🔴 2 test; **geri alma ilk denemede UYGULANMADI** (yinelenen satır vardı, kısıt sayısı 0 kaldı) → TRUNCATE + ADD → sayı 1, süit yeşil |

Denenmeyen: işlem (transaction) kaldırılırsa — olay "işlendi" yazılır ama
uygulama düşerse kaybolur; senaryoyu kuran test yok (Ç18).

## Gerçek istek (Stripe test API, hesap DE, `livemode=false`)
- Ürün `prod_VTArs3…`, fiyat `price_1USEVT…` 9,99 EUR/ay.
- `curl POST /checkout/sessions` → 201, `cs_test_…`; Stripe'tan geri okundu:
  `mode=subscription`, `amount_total=999`, doğru fiyat, dönüş adresleri
  `FUNNEL_BASE_URL`'den. 422 ve 400 de gerçek HTTP'de ölçüldü.
- `stripe trigger customer.subscription.created` → `--> … <-- [200]`;
  DB: 1 abonelik `active`, 1 olay.
- `stripe events resend <aynı olay>` → yine 200; DB: **hâlâ 1 olay, 1 abonelik** (R3 gerçekte).
- E-posta bağlama gerçek Checkout'ta ölçülmedi — huni bloğu (test kartıyla).

## Ölçüm
`bin/kontrol` EXIT=0 · core-test `OK (12 tests, 38 assertions)`, 0 atlanan.
