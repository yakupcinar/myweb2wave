# G2 — Mağaza aboneliğinin bitişini okumak (2026-11-20)

Blok G (PLAN; akış Ç159): App Store abonesini web aboneliğine geçirirken web aboneliği mağaza aboneliğinin
bittiği an başlar. Bitişi bilen tek yer RevenueCat (uygulama `Purchases.logIn(kisi)` yapar → müşteri kimliği = kişi).

## Karar

- `App\RevenueCat\MagazaAboneligi::oku(app, kisi)` → `{bitis, yenilenecek}` · `null` (erişim veren mağaza aboneliği
  yok) · `'kapali'` (ayar yok) · `'ulasilamadi'` (RevenueCat hatası — geçiş başlamaz, tahminle tarih konmaz).
- **REST v2** (`GET /v2/projects/{proje}/customers/{kisi}/subscriptions`), v1 değil: v1 `GET /subscribers` müşteriyi
  yoksa oluşturur (yan etki); v2 belgede 404 dönüyor (oluşturma davranışı belgede yok — canlıda ölçülecek).
- Sayılan: `gives_access: true` ve mağazası web faturası olmayan (`stripe`, `rc_billing`, `paddle`, `paypal` hariç).
  Bitiş `current_period_ends_at` ile `ends_at`'ın büyüğü (belge: `ends_at` yalnız `has_already_renewed`'da ileri).
  Birden çok abonelikte en geç biten.
- `yenilenecek`: `will_renew`, `will_change_product`, `requires_price_increase_consent`, `has_already_renewed`.
  `will_not_renew` / `will_pause` → false (kullanıcı iptal etmiş). G5 (unutma kontrolü) bunu kullanır.
- Ayar uygulama başına (`uygulamalar.yaml` › `revenuecat_v2`): proje kimliği + v2 gizli anahtarı
  (izin `customer_information:subscriptions:read`, yalnız okuma). Biri boşsa kapalı.
- Reddedilen: kişinin kendi beyanı ("aboneliğim 12 Aralık'ta bitiyor") — yanlışsa çift ödeme; StoreKit'ten
  uygulamanın göndermesi — her uygulamaya iş, RevenueCat zaten biliyor.

## Test ve kırma

`MagazaAboneligiTest` 5 test. Kırma 7/7 kırmızı: K1 erişim bakılmaz · K2 Stripe sayılır · K3 `ends_at` yok ·
K4 ilk gelen seçilir · K5 yenileme hep true · K6 404 hata sayılır · K7 ayarsızda istek gider (test doğrulamayla değil
PHP uyarısıyla düştü — tanımsız anahtar; yine kırmızı).

## Ölçülmedi

Gerçek RevenueCat v2 yanıtı (alan adları belgeden). Gerekli: RevenueCat proje kimliği + v2 gizli anahtarı (kullanıcı
oluşturur, sunucuya kendisi girer) ve Test Store'da bir abonelik. Bilinmeyen müşteride v2'nin 404 döndüğü ve müşteri
oluşturmadığı da orada ölçülecek.
