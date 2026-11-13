# M6c — Aboneliği duraklat / sürdür (2026-11-12)

Karar `pre-setup.md` A28 (kullanıcı: erişim kapanır · 1–3 ay seçilir · yalnız panel) · önceki
[m6a-iptal-iade.md](m6a-iptal-iade.md).

## Ölçüm (önce, gerçek Stripe test hesabı)

`sub_1USFKYPfp6LpRKk11JBzb7b7` üzerinde, sonra eski hâline döndürüldü:
- `pause_collection: {behavior: void, resumes_at}` → `status` **`active` kalır**; yalnız `pause_collection` dolar.
- Olay yalnız `customer.subscription.updated` (`previous_attributes.pause_collection`); `customer.subscription.paused`
  / `resumed` **gelmez** (onlar `status = paused` içindir).
- **Geçmiş `resumes_at` reddedilmez**: Stripe kabul eder ve duraklatmayı hemen kaldırır.
- Duraklat ve sürdür olayları **aynı saniyede** (`created` eşit) — Ç142.

## Ne yapıldı

- **Göç** `Version20261112120000`: `subscriptions.duraklatildi`, `duraklatma_bitis` (bitiş yalnız duraklıyken; kısıt).
- **Webhook** (`SubscriptionSync::applyStatus`): `pause_collection` ve `resumes_at` her abonelik olayında yazılır.
- **Hak** (`Hak::sorgula`): erişim veren durumdaki abonelik duraklıysa ve bitişi geçmemişse `paused` sayılır →
  `aktif: false, durum: paused`. Bitiş geçmişse duraklı sayılmaz (Stripe sürdürür, olay gecikse de erişim açılır).
  Bitmiş abonelikte eski bayrak `paused` göstermez.
- **Servis** `AbonelikYonetimi::duraklat($abonelik, ?int $ay)`: 1–3 ay → `void` + `resumes_at = şimdi + ay`; `null`
  → sürdür (stripe-php `null`'ı boş alan gönderir — testteki kayıtta `''`, gerçek API'de duraklatma kalktı).
  Stripe'ın **yanıtındaki** durum ve bitiş yazılır (M6a kararı 5).
- **Uç** `POST|DELETE /panel/api/abonelikler/{abonelik}/duraklat` (gövde `{ay}`), CSRF, sözleşmede.
- **Panel Aboneler:** sürende "Duraklat" (iptal planlıda yok), onayda **Süre** seçimi (1 / 2 / 3 ay, varsayılan 1);
  duraklıda durum "Duraklatıldı · 12 Ocak 2027'ye kadar" ve "Sürdür".
  - Durum hücresi artık satır kırabiliyor; uzun tarihli durum tabloyu karttan taşırıyor, işlem sütunu kesiliyordu
    (ekran döngüsünde görüldü; `tasanlar` ölçümü kartın `overflow-hidden`'ı yüzünden yakalamadı).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Erişim bitişe göre hesaplanır (`bitis > now()`) | yalnız bayrak | Stripe sürdürünce olay gecikir ya da aynı saniyedeki olaylar ters işlenir (Ç142) |
| 2 | `paused` yalnız erişim veren durumda | her durumda | İptal edilmiş abonelikte eski bayrak yanlış durum gösterirdi |
| 3 | İptal planlıyken "Duraklat" yok | ikisi birden | Dönem sonunda bitecek aboneliği duraklatmak anlamsız; Stripe izin verse de |
| 4 | Süre ayı istemcide değil sunucuda hesaplanır | istemci tarih gönderir | Geçmiş tarih Stripe'ta hemen sürdürür (ölçüldü); sunucu 1–3 dışını reddeder |

## Kırma denemeleri — 19/19 kırmızı

Çekirdek (13): D1 `behavior` keep_as_draft · D2 süre hep 1 ay · D3a durum hep duraklı · D3b istenen bitiş yazılır ·
D4 süre denetimi yok · D5 `ay` tip denetimi yok (`"1"` geçer) · D6 sürdür yerine duraklat · D7 webhook duraklatmayı
okumaz · D8 webhook bitişi okumaz · D9 hak `paused` yok · D10 hak bitişe bakmaz · D11 bitmiş abonelikte `paused` ·
D12 liste bitişe bakmaz — hepsi 🔴.

Panel (6): P1 gövdede ay hep 1 · P2 sürdür POST · P3 durum bitişsiz · P4 iptal planlıda Duraklat · P5 varsayılan
2 ay · P6 Sürdür düğmesi yok — hepsi 🔴.

**D5 / D6 notu:** ilk koşuda yamadaki `$ay` / `$abonelik` perl'de boş değişkene döndü; PHP sözdizimi hatası başka
testleri düşürdü (çıkış 2). Kırmızı sayılmadı; kaçışla yeniden koşuldu → beklenen testte 🔴.

## Ölçüm

- **Çekirdek:** `PanelAbonelikTest` +3, `OlaylarTest` +1, `KimlikControllerTest` +1 (dört durum),
  `PanelKullanicilarTest` satır beklentisi iki yeni alanla.
- **Panel:** `Kullanicilar.test.tsx` +2 (12 test).
- **Tam kontrol:** YEŞİL (767 çekirdek testi), `UYARI dev-goc` yok, yerel, 2026-11-12. (İlk koşu: `panel-npm`
  `ECONNRESET` ağ kesintisi; ikinci koşu yeşil.)
- **Gerçek sistem** (dev DB + Stripe test API, servis doğrudan): 2 ay → DB `true, 2027-01-12`, Stripe `void,
  1799748399`; 5 ay → "süre 1, 2 ya da 3 ay olmalı"; sürdür → DB `false, null`, Stripe `null`; bilinmeyen →
  "bilinmeyen abonelik". Abonelik sonda sürüyor.
- **Ekran döngüsü** (`kullanicilar`, abonelik geçici duraklı): masaüstünde kesilme düzeldi; telefonda e-posta ezilmiyor,
  durum iki satır.
- **Açık:** RevenueCat duraklatılmış aboneliğe hak verir mi, ölçülmedi — Ç143.
