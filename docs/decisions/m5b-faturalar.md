# M5b — Stripe faturaları ziyarete bağlanır (2026-11-09)

Karar `pre-setup.md` A18 (kullanıcı: kohort geliri faturalardan) · önceki `m5a-olay-deposu.md` · Ç118 (3).

## Ne yapıldı

`invoice.paid` webhook'u işleniyor. Tutarı > 0 olan tahsilat `odeme_faturalari` tablosuna
yazılıyor (göç `Version20261109150000`): fatura, abonelik, uygulama, küçük birimde tutar, para
birimi, neden (`subscription_create`, `subscription_cycle` …) ve ödeme anı (`status_transitions.paid_at`).
Aboneliğin ödeme oturumuyla bağlı olduğu her ziyarete yeni sözlük olayı **`odeme.fatura`**
`{tutar, paraBirimi, neden}` ekleniyor. İlk faturanın olayı çoğu zaman ödeme oturumundan önce
geliyor. O durumda olay, oturum aboneliğe bağlandığı anda ekleniyor (`abonelik.durum` ile aynı desen).

Sözlükte M5a'dan kalan "(son hâli)" açıklamaları "değişince" olarak düzeltildi; adlar değişmedi.

## Ölçüm (önce)

- Gerçek test faturaları (API `2026-09-30.endive`, stripe-php 22): üst düzeyde `subscription`
  yok, faturanın `metadata`'sı boş. Abonelik ve `metadata.app`, `parent.subscription_details`
  altında (tuzak §54). Deneme başlangıcı tutarı 0 olan bir `subscription_create` faturası.
- Stripe hesabında **webhook uç noktası kayıtlı değil** ve yerelde Stripe CLI yok. Dev'de gerçek
  ödemelerin olayları hiç gelmiyor (Ç120).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Ayrı tablo + olay | yalnız olay | Kohort parası ödeme anıyla ve faturayla tekil tutulmalı; olay çizelgede |
| 2 | Tutar 0 yazılmaz | her faturayı yazmak | Deneme başlangıcı gelir değil; tablo kısıtı da `> 0` |
| 3 | Uygulama aboneliğin metadata'sından | faturanın metadata'sı | Ölçüldü: faturanınki boş |
| 4 | Ödeme anı `paid_at`, yoksa olayın zamanı | webhook'un geliş anı | Gecikmeli / yeniden gönderilen olay kohortu kaydırmasın |
| 5 | Oturumun ilk tutarı (`tutar_kucuk`) ayrı kalır | ödedi'yi faturadan saymak | Rapor ve deneylerin "ödedi" tanımı M5c'de birlikte ele alınacak (Ç118 (3)) |
| 6 | İadeler düşülmüyor | `charge.refunded` | Kapsam dışı (Ç120) |

## Test (`OlaylarTest` +2, gidiş testi faturayla)

Fatura oturumdan önce gelince bağlanma anında çizelgeye giriyor. Yenileme ekleniyor. Aynı fatura
iki kez gelse tek olay. Tutarı 0 olan fatura yazılmıyor. Ödeme anı `paid_at`'tan (beklenen UTC
saatleri Python'la hesaplandı; biri elle yanlış yazılmıştı, test yakaladı). Uygulama faturanın
metadata'sından değil aboneliğinkinden geliyor; aboneliksiz fatura yazılmıyor. JSONB anahtar sırası
için test yardımcısı anahtarları sıralıyor (tuzak §28 burada da çıktı).

## Kırma (8 deneme, 7 🔴)

| # | Bozulan | Sonuç |
|---|---|---|
| F1 | `invoice.paid` ilgisiz | 🔴 7 |
| F2 | uygulama faturanın kendi metadata'sından | 🔴 7 |
| F3 | tutar 0 da yazılır | 🔴, ama **hata** ile: DB kısıtı (`> 0`) reddetti, webhook işlemi düşerdi (Stripe sonsuz yeniden dener). Koddaki kontrol bu yüzden gerekli |
| F4 | tekrar fatura yine olay | 🔴 |
| F5 | bağlanınca fatura olayı yok | 🔴 |
| F6 | faturada ziyaret olayı yok | 🔴 6 |
| F7 | ödeme anı olay zamanından | 🔴 |
| F8 | aboneliksiz fatura da yazılır | **🟢**. Testi suçladım: aboneliksiz fatura gerçek biçimde `subscription_details` taşımaz, uygulama da olmaz, daha önce uygulama kontrolünde durur. Kontrole ulaşılamıyor → **kod kaldırıldı** (kural 6a, tuzak §10) |

## Gerçek istek (2026-11-09, dev)

Test hesabındaki son 4 gerçek `invoice.paid` olayı API'den alındı, `STRIPE_WEBHOOK_SECRET` ile
imzalanıp `POST /webhooks/stripe`'a verildi → 4 × 200. Tutarı 0 olan (M4b'deki deneme) yazılmadı.
Üç 999 EUR fatura `keepchat` ile ve gerçek ödeme anıyla yazıldı (2026-10-26, 11-04, 11-04). Bu
aboneliklerin dev'de ödeme oturumu bağı yok (webhook'ları hiç işlenmemişti), bu yüzden ziyaret
olayı yok. Satırlar dev'de bırakıldı (gerçek test verisi).
