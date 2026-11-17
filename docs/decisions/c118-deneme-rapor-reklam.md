# Ç118 (1)(2) — denemede rapor ve reklam olayı (2026-11-09)

Önceki `m4b-deney-sonuclari.md` · tuzak §52 · karar `pre-setup.md` A17.

## Ne yapıldı

Kullanıcı kararı (2026-11-09): "raporu düzelt, reklama deneme başladı olayı gitsin".

- **Panel Rapor:** "Ödedi" artık oturum tutarı > 0'dan sayılıyor. Kartlı deneme (Stripe
  `paid`, tutar 0) yeni **"Deneme başladı"** sütununda ve satır aşamasında. Özet ve kaynak
  kartlarındaki dönüşüm = (deneme + ödedi) / ziyaret, yani abone olan ziyaret. Bu, deneylerle
  aynı tanım (A17). Günlük grafikteki seri "Tamamlanan ödeme (deneme dahil)" diye adlandırıldı.
  Olay deposundaki `odeme.tamamlandi` adı ve anlamı değişmedi (T4: ad değişmez).
- **Reklam olayı:** tutarı 0 olan oturumda sunucu (Meta CAPI / TikTok Events API) `Purchase` /
  `CompletePayment` yerine **`StartTrial`** gönderiyor; değer 0, para birimi ve event ID aynı
  kalıyor. Tarayıcı pikseli aynı adı seçiyor: `POST /huni/giris-baglantisi` yanıtına `deneme`
  alanı eklendi (sözleşmede). İki ağ olayları ad + event ID ile tekilleştirir, bu yüzden iki
  tarafın aynı adı göndermesi gerekir.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Deneme = abone oldu, hiçbir oturumunda tutar > 0 yok (ziyaret başına) | oturum başına · `subscriptions.status = trialing` | Sayımlar zaten ziyaret başına; durum dev'de çoğu zaman boş (abonelik olayı işlenmemiş) ve sonra değişir |
| 2 | Satırda tutarı > 0 olan oturum önce, sonra en yeni abonelik anı | yalnız en yeni oturum | Hem deneme hem ödeme açan ziyaret "ödedi" görünmeli |
| 3 | Dönüşüm = abone (deneme + ödedi) | yalnız ödedi | Denemeli hunide günlerce 0 olurdu; deneylerdeki tanımla aynı olmalı (kural 7) |
| 4 | Ağlara `StartTrial` (Meta ve TikTok standart olayı), değer 0 | tahmini değerle `Purchase` · `Subscribe` | Kullanıcı kararı; tahmini değer M5 ileri analitiğinde (LTV) |
| 5 | Piksel adı `giris-baglantisi` yanıtından | ayrı uç · sayfada Stripe okumak | Sayfa bu yanıtı zaten bekliyor; tutar aynı webhook işleminde yazılıyor, yarış yok |
| 6 | Huni oturumu olmayan ödemede `deneme: false` | — | Piksel yalnız izinli huni ziyaretinde ateşlenir; sunucu olayı da yalnız huni oturumunda |
| 7 | Tutarı bilinmeyen eski oturum (göçten önce) ne ödedi ne deneme | eski Stripe etiketine dönmek | Yedek zincir (§12); üretimde böyle kayıt yok, yalnız dev |

## Test

- `PanelTest` +1: deneme / deneme + ödeme / tutarsız `paid` oturumlu üç ziyaret. Sayım
  `deneme 1, ödedi 1`; satırlar `deneme başladı (trialing, maskeli)`, `ödedi`, `ödemeye geçti`.
  Ödeme denemeden bir gün önce: "en yeni oturum" seçimi yanlış sonuç verir.
- `StripeWebhookControllerTest` +1: `paid` + tutar 0 olduğunda iki ağa `StartTrial` gidiyor,
  değer 0, EUR, aynı event ID.
- `GirisBaglantisiTest` +1: tutar 0 → `deneme: true`; 999 ve hunisiz → `false`; yanıt sözleşmeye uyuyor.
- Huni `reklam-pikselleri.test.ts` +1, panel `Rapor.test.tsx` güncellendi: dönüşüm 2/4 = 50 %.
  Yalnız ödedi'den sayılsa 25 % çıkardı.

## Kırma (12/12 🔴)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | sunucu denemede de Purchase | 🔴 1 |
| K2 | TikTok hep CompletePayment | 🔴 1 |
| K3 | `deneme` hep false | 🔴 1 |
| K4 | `deneme` tutar ≥ 0 | 🔴 1 |
| K5 | piksel Meta hep Purchase | 🔴 1 |
| K6 | piksel TikTok hep CompletePayment | 🔴 1 |
| K7 | satırda abone = ödedi | 🔴 1 |
| K8 | satırda en yeni oturum | 🔴 1 |
| K9 | deneme sayımı ödeyeni de sayar | 🔴 2 |
| K10 | ödedi Stripe etiketinden (`tamamlandi_at`) | 🔴 1 |
| K11 | satırda abone `tamamlandi_at`'tan | 🔴 1 |
| K12 | panel dönüşümü yalnız ödedi | 🔴 1 |

Ölçülmeyen: `basarili.ts` yalnız uç yanıtındaki `deneme`'yi piksele geçiriyor ve bu sayfanın
testi yok. Uç gerçek istekle ölçüldü (aşağıda); tarayıcıdaki piksel çağrısı ölçülmedi (dev'de
piksel kimliği yok).

## Gerçek istek (2026-11-09, dev)

- M4b'deki gerçek 7 günlük denemeli Stripe oturumu: pencere için `odeme_tamamlandi_at` geçici
  olarak şimdiye alındı. `POST /huni/giris-baglantisi` → 201, `deneme: true`. Sonra eski değere
  döndürüldü ve üretilen jeton silindi.
- Dev'de 30 günlük Rapor servisi: organik `ödemeye geçti 15 · deneme 1 · ödedi 0`, reklam
  `3 · 0 · 0`. Eski kod 2 "ödedi" sayıyordu: deneme ile göçten önceki tutarsız bir oturum.
