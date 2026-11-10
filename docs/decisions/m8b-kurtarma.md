# M8b — terk edilen ödeme kurtarma (2026-11-10)

Karar `pre-setup.md` A22 · önceki `m8a2-aktivasyon.md`.

## Ne yapıldı

- **Ödeme oturumu:** 1 saatte dolar (`expires_at`), kurtarma açık (`after_expiration.recovery.enabled`).
- **Webhook:**
  - `checkout.session.expired` Stripe'ın kurtarma adresini `odeme_oturumlari.kurtarma_url`'ye yazar (yalnız `https://`).
  - `checkout.session.completed` gelen kopya oturumda `recovered_from` varsa, kopyayı asıl oturumun ziyaretine bağlar
    (`kurtarildigi_oturum`). Böylece atıf, deney dönüşümü ve `odeme.tamamlandi` olayı asıl ziyarete yazılır.
- **`App\Posta\KurtarmaDizisi`** (`app:posta:dizi` içinde):
  - **Pazarlama e-postasıdır:** yalnız `huni_epostalari.pazarlama_izni_at` doluysa gider, `broadcast` akışından
    (abonelikten çıkmayı Postmark ekler).
  - Adım 1 oturum dolunca, adım 2 oturum açıldıktan 24 saat sonra; gecikmişlerden yalnız sonuncusu.
  - Durur: ödendi (aynı ziyaretten başka bir oturum da sayılır), aynı e-postayla bu uygulamada erişim veren abonelik var,
    izin geri alındı, yayın akışında bastırılmış.
  - Aynı kişiye (uygulama + e-posta) 7 günde tek dizi; 7 günden eski oturuma başlamaz.
- Göç `Version20261110180000`: `posta_dizisi.kaynak` (eski `stripe_subscription_id`), `odeme_oturumlari.kurtarma_url`,
  `kurtarildigi_oturum`.

## Ölçüm (önce, Stripe test modu, 2026-11-10)

- Belge: kurtarma adresi yalnız süresi dolan oturumda verilir (en az 30 dk, varsayılan 24 sa), 30 gün geçerli.
  Gömülü form (`ui_mode: form`) desteklemiyor; bizim kullandığımız `hosted_page` ve `embedded_page` destekliyor.
- Gerçek test oturumu açıp kapattım, ardından kurtarma adresini `ekran` kabındaki Chromium'da açtım (`curl` kopya
  oluşturmuyor, sayfa JS ile açıyor).
  - Kopya `metadata` (`app`), `client_reference_id`, dönüş adresi ve e-postayı **koruyor**.
  - Tam sayfada `hosted_page`, gömülüde `embedded_page` dönüş adresiyle açılıyor.
  - Ölçüm oturumları kapatıldı.
- Postmark yayın akışı: abonelikten çıkma bağlantısını ve `List-Unsubscribe` başlıklarını Postmark kendisi ekler,
  çıkanı o akışın bastırma listesine yazar ve Subscription Change ile bildirir (M8a1 webhook'u).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Stripe kurtarma adresi | kendi uçta yeni oturum / huniye dönüş | Ölçüm kopyanın gerekeni koruduğunu gösterdi; huni durumu bellekte, anket baştan başlar |
| 2 | Oturum 1 saat | 30 dk / 24 sa | 24 saatte ilk e-posta geç; 30 dk ödeme sayfasında düşünen kişiyi keser |
| 3 | 2 adım (dolunca, 24 sa) | PLAN'daki 15 dk / 6 sa / 24 sa | İlk adım oturum dolmadan gidemez (adres yok); 3. adım şikâyet riski |
| 4 | Yalnız huninin izni | Stripe'ın `consent_collection` | İzin huni sırasında alınıyor (Stripe: önceden alındıysa gerekmez); gömülüde e-posta zaten bizden |
| 5 | Kişi başına 7 günde tek dizi | oturum başına | Birkaç oturumu yarım bırakan kişi birkaç dizi almasın |
| 6 | Erişim veren abonelik durdurur, iptal etmiş durdurmaz | her abonelik | Eski abonenin yeniden alımı da kurtarılabilir |
| 7 | İndirim yok | `allow_promotion_codes` | Teklif kuralı tanımlı değil (PLAN: indirim yalnız açık teklifle) |
| 8 | "Doldu mu" sütunu yok | `suresi_doldu_at` | Kırma B14 yeşil kaldı: adresi yalnız süresi dolan oturum aldığı için şart hiçbir şey ölçmüyordu (§10); sütun da okunmuyordu, kaldırıldı |

## Test

- `KurtarmaDizisiTest` (9):
  - webhook: adres yazılıyor, `javascript:` reddediliyor; kopya ödenince asıl ziyarete bağlanıyor (tutar, olay) ve
    kurtarma durduruluyor;
  - izinli adrese yayın akışından bir kez; izinsize ve açık oturuma gitmiyor;
  - 24 saatte 2. adım, gecikmişse yalnız sonuncusu;
  - ödeyene, aynı e-postayla abone olana gitmiyor (büyük harfli e-posta da); iptal etmişe ve başka uygulamadaki
    aboneye gidiyor;
  - 7 günde tek dizi (başka uygulama ayrı, 8 gün sonra yeniden);
  - yayın akışında bastırılmışa, 8 günlük oturuma, izni geri alınana gitmiyor (işlem akışındaki bastırma sayılmıyor);
  - adresi olmayan oturumda adım kaydı da yok;
  - komut.
- `ParaBirimiTest` +1: Stripe'a giden `after_expiration` ve `expires_at` (+3600).

## Kırma (18/18 🔴; 1 deneme kaldırıldı)

B1 adım sırası · B2 izin · B3 yayın başlığı · B4 aynı ziyaretten ödeme · B5 e-posta küçük harf · B6 erişim süzgeci ·
B7 uygulama · B8 7 gün tek dizi · B9 kişi kuralında uygulama · B10 bastırma · B11 akış · B12 7 gün yaş · B13 adres şartı ·
B15 `adim >=` · B16 `https://` denetimi · B17 kopya bağı · B18 kurtarma parametresi · B19 1 saat — hepsi 🔴.
**B14 (`suresi_doldu_at` şartı) 🟢** → şart ve sütun kaldırıldı (karar 8).

## Gerçek sistem (2026-11-10)

- Dev `POST /checkout/sessions` → 201. Gerçek Stripe test oturumu: süre **60 dk**, kurtarma **açık**, `app=keepchat`;
  oturum kapatıldı.
- Dev'de geçici izinli ziyaret + oturum (kurtarma adresiyle):
  - `app:posta:dizi` → "Kurtarma: 1 e-posta"; Mailpit'te konu "Ödemeniz yarım kaldı", `X-PM-Message-Stream: broadcast`;
  - ikinci koşu 0;
  - satırlar ve ileti silindi.
- Gerçek `checkout.session.expired` webhook'u dev'e gelmiyor (Ç120); Postmark yayın akışı hesapta yok (Ç125).
- `bin/kontrol` YEŞİL, core 690 test.
