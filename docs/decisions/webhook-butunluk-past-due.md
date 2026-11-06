# L17 — Webhook bütünlüğü (Ç18) + yenileme başarısızlığı `past_due` uçtan uca (Ç20)

2026-10-26 · karar `pre-setup.md` A13.

## Ç18 — yarıda düşen olay kaybolur mu?
**Ölçüm (önce):** `SubscriptionSync::apply` olay kimliğini ve uygulamayı aynı
`transactional` içinde yazıyor; ama "işlendi yazıldı, uygulama düştü"
senaryosunu kuran test yoktu.

**Test:** `testYaridaDusenOlayGeriAlinirYenidenGelinceIslenir` — hata sahte
servisten değil, **gerçek veritabanından**: test DB'sinde `subscriptions`'a
`sub_cokus` eklenince hata fırlatan tetikleyici. Beklenen ve ölçülen: yanıt
500 (Stripe yeniden yollar), `processed_webhook_events` ve `subscriptions`
boş; tetikleyici kalkınca aynı olay 200 ve ikisi de yazılı. Kod değişmedi —
davranış zaten doğruydu, ölçülmemişti; test ilk koşuda yeşil, kırma ile
ölçtüğü kanıtlandı:

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | olay kaydı işlemin dışında (önce) | 🔴 "1 is identical to 0" — kayıt kalır, yeniden deneme yok sayılırdı |
| K2 | hata yutulur, 200 | 🔴 "status code is 500" |

## Ç20 — gerçek Stripe test saatiyle `past_due`
stripe-mock yenileme üretemez; kararda gerçek Stripe **test** API'si
seçilmişti. Ölçüm betiği (çekirdek kabında, anahtar ortamdan, yalnız
`sk_test_` kabul eder):
1. Test saati + müşteri; `pm_card_visa` ile abonelik (`metadata.app =
   keepchat`) → **active**.
2. Varsayılan ödeme yöntemi `pm_card_chargeCustomerFail` (eklenir, tahsilatta
   reddedilir); saat +32 gün → hazır.
3. Abonelik **past_due**. Stripe olayları: `created(active)`,
   `updated(active)` ×2, `updated(past_due)`.
4. Dört olay geliştirme webhook sırrıyla imzalanıp sırayla yerel çekirdeğe:
   hepsi 200; `subscriptions.status = past_due`, `app = keepchat`.
5. `Hak::ERISIM_VEREN` `past_due`'yu içerir (Stripe yeniden denerken erişim
   sürer — S8'in koruduğu senaryo gerçek olaylarla geçti).
6. Temizlik: test saati silindi (müşteri + abonelik onunla), `retrieve` 404;
   yerel DB'deki ölçüm aboneliği silindi.

**Ölçülmeyen:** Stripe'ın olayı çekirdeğe kendisinin yollaması (yerel
çekirdek dışarıdan erişilemez; sunucu bekliyor) — olaylar Stripe'tan alınıp
imzalanarak yeniden oynatıldı. Sonraki adım (yeniden denemeler tükenince
`unpaid`/`canceled`) ölçülmedi; Stripe ayarına bağlı.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 233 çekirdek testi / 1.645 doğrulama.
