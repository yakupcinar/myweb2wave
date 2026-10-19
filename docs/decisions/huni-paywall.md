# Blok: Huni paywall + sözleşme (R2) — 2026-10-19

## Ne yapıldı
- **Sözleşme** `contract/openapi.yaml` — tek kaynak. Çekirdek testleri gerçek
  yanıtı buna göre doğrular (`tests/Support/Sozlesme.php`, league validator);
  huni tipleri buradan **üretilir** (`funnel/src/sozlesme/api.ts`);
  `bin/kontrol` `sozlesme-guncel` adımı yeniden üretip `cmp` ile karşılaştırır.
- **Huni**: `index.astro` paywall (tipli `openapi-fetch` istemcisi, React yok),
  `basarili.astro`, `iptal.astro`. Çekirdek adresi `PUBLIC_CORE_URL` (derlemede).
- **Ç16 kapandı**: Stripe `ApiErrorException` → **502** `payment_provider_unavailable`
  + günlük satırı; huni ayrı mesaj gösterir.
- **CORS**: yalnız `FUNNEL_BASE_URL` kökeni, yalnız `/checkout/sessions`,
  yalnız POST/OPTIONS. Tarifin varsayılanı (localhost'un her portu, bütün
  yollar, DELETE/PUT) daraltıldı.

## Neden böyle
- **Üretici ayrı paket (`contract/`, TS 5.9.3):** openapi-typescript 7.13
  peer `typescript ^5.x`; huni TS 6. `--legacy-peer-deps` uyumsuzluğu gizler.
- **Doğrulayıcı doğrudan league 0.24** (osteel sarmalayıcısı yok).
- **E2E Playwright ile otomatik DEĞİL**: Stripe barındırılan Checkout'un
  otomatik test edilmesini önermiyor; gerçek senaryolar tarayıcı aracıyla elle.

## Yolda ölçülenler
- **Kendi ölçüm hatam:** sürüm filtresi `x.y.z` aradı, `0.24` gibi iki
  parçalı sürümleri kaçırdı → league "2021'den beri bakımsız" göründü; tarihe
  göre sıralayınca 0.24 (2026-05) çıktı.
- **Yalan yorum:** catch'e "ayrıntı günlüğe" yazdım ama günlük yoktu →
  `LoggerInterface` eklendi, testte `[error] Stripe Checkout oturumu açılamadı` görüldü.
- `psr/log` doğrudan bağımlılık yapıldı (yalnız dolaylıydı).

## Kırma denemeleri

| # | Bozulan karar | Sonuç |
|---|---|---|
| P1 | huni sözleşmede olmayan alan (`data.adres`) | 🔴 `funnel-tip` |
| P7 | huni olmayan plan (`yearly`) | 🔴 `funnel-tip` |
| P2 | sözleşme değişti, tip üretilmedi | 🔴 `sozlesme-guncel` + `core-test` |
| P3 | çekirdek alan adı (`url`→`link`) | 🔴 sözleşme doğrulaması |
| P4 | CORS webhook'a açık | 🔴 `testWebhookYolundaCorsYok` |
| P5 | CORS joker köken | 🔴 `testCorsYalnizHunininKokenineIzinVerir` |
| P6 | 502 yok (500) | 🔴 `testStripeaUlasilamazsa502` |
| P8 | sözleşmede 502 yok | 🔴 sözleşme doğrulaması |

## Gerçek tarayıcı (Claude in Chrome, Stripe test, hesap DE)

| # | Senaryo | Ekran | Olaylar / DB |
|---|---|---|---|
| E1 | `4242…` başarılı | `/basarili` | `subscription.created` **önce**, `checkout.session.completed` sonra (ters sıra!) → `active`, e-posta `test+huni@` bağlı |
| E2b | 3DS reddet | Stripe hata | `payment_intent.payment_failed` `authentication_failure`; abonelik/olay YOK |
| E2a | 3DS onayla | `/basarili` | normal sıra → `active`, `test+3ds@` bağlı |
| E3 | `…0002` red | "Your credit card was declined" | olay yok, abonelik yok |
| E4 | `…9995` yetersiz bakiye | "…insufficient funds" | olay yok, abonelik yok |
| E5 | `…0341` | "declined" | **tahmin TUTMADI**: `incomplete` abonelik beklendi; Checkout ilk ödemesi başarısızken abonelik HİÇ oluşturmuyor (Stripe'ta 0 `incomplete`) |
| E6 | geri ok | `/iptal` "Ödeme tamamlanmadı" | değişiklik yok |
| E7 | çekirdek durdurulmuş | "Bağlantı kurulamadı…" | — |
| E8 | geçersiz Stripe anahtarı (gerçek 401) | "Ödeme sağlayıcısına şu an ulaşılamıyor…" | gerçek `502` + `Access-Control-Allow-Origin` (yoksa tarayıcı gövdeyi okuyamazdı) + günlük satırı |

- E2'nin 3DS penceresi çapraz kökenli iframe: araç tıklayamadı (2 deneme),
  kullanıcı elle tıkladı; sonuç Stripe olay kaydından **ölçüldü**.
- Eklenti izinleri oturum içinde değişti (Stripe'ta okuma, localhost'ta
  ekran görüntüsü reddedildi) — sonuçlar sekme başlığı/`find` ile okundu.
- Ekran kaydı (50 kare) dışa aktarılmadı; sekme kapanınca grup gitti.

## Ölçüm
`bin/kontrol` EXIT=0 · core-test `OK (15 tests, 43 assertions)`, 0 atlanan.
