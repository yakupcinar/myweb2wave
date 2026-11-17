# M1b — teklifler: kartlı deneme, ücretli giriş, ilk dönem indirimi (2026-11-04)

Karar `pre-setup.md` A14 (üç teklif türü) · plan `web2wave-platform-yapisi.md`
satır 8 ("Plan ve fiyat … giriş teklifleri") · önceki blok `m1a-fiyat-katalogu.md`.

## Ölçüm (önce)

- Ödeme oturumu (`CheckoutController`) yalnız planın fiyatını açıyordu: tek
  kalem, deneme/indirim yok; `payment_method_collection` verilmiyordu (Stripe
  varsayılanı).
- Katalogda (`planlar`) teklif alanı yok; `tutarHatasi` 0'ı "deneme M1b'de"
  diye reddediyor.
- Paywall fiyatı `Fiyatlar` → huni yanıtı `fiyatlar` → `fiyatMetni` ("€9,99 / ay").
- Hak (`Hak::ERISIM_VEREN`) `trialing`'i zaten erişim sayıyor; webhook durumu
  abonelik olaylarından yazıyor — deneme için değişiklik gerekmiyor.
- Meta/TikTok dönüşüm değeri oturumun `amount_total`'ından (`DonusumAktarimi`).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Teklif **plana** bağlı, katalogda, plan başına en çok bir (`teklif_turu`, `teklif_gun`, `teklif_yuzde`, `stripe_teklif`) | paywall adımında teklif | Ödeme oturumu teklifi istemciden alamaz; plan zaten sunucu tarafında seçiliyor. Aynı planı farklı teklifle satmak = ayrı plan anahtarı (M2 paywall'ı planlar arasında seçer) |
| 2 | **Ücretli giriş = deneme günleri + ilk faturada tek seferlik ücret** (kullanıcı kararı 2026-11-04) | Subscription Schedule (A14'teki parantez) | Schedule ödemeden SONRA webhook'ta kurulur; kurulamazsa müşteri süresiz giriş fiyatında kalır — sessiz gelir kaybı + kurtarma işi. Bedeli: Stripe/RevenueCat'te abonelik "deneme" görünür, giriş süresi gün cinsinden |
| 3 | Deneme **kartlı**: `payment_method_collection: always` açıkça | Stripe varsayılanına güvenmek | Kararın kodda ve testte görünmesi; kartsız deneme ayrı bir ürün kararı |
| 4 | İndirim = **yüzde**, `duration: once` kupon | sabit tutar (`amount_off`) | Para biriminden bağımsız (M1c çok para birimi); "ilk dönem" tanımı tam olarak `once` |
| 5 | Giriş ücreti tutarı DB'de YOK, Stripe fiyatından okunur | tutarı satırda tutmak | M1a'nın L10 kuralı: tek kaynak |
| 6 | Teklifi gösterilemeyen planın **fiyatı da** yanıtta yok (giriş ücreti okunamadı / yinelenen / başka para birimi) | yalnız normal fiyatı göstermek | "9,99 € / ay" yazıp ödemede deneme + giriş ücreti açmak yanıltır; hiç yazmamak yanıltmaz (paywall metni kalır) |
| 7 | Paywall indirimli tutarı **hesaplamaz**, yüzde yazar ("İlk ay %50 indirimli, sonra €9,99 / ay") | 9,99 × 0,5'i göstermek | **Ölçüldü:** Stripe 999'un %50'sini 500 yuvarladı, tahsilat 4,99 € — huni 5,00 € yazacaktı |
| 8 | Metin: teklif başta, normal fiyat "sonra" ile; tutar–birim arası bölünmez boşluk | iki ayrı satır/bileşen alanı | Ödemede çekilecek her tutar paywall'da yazılı; bölünmez boşluk olmadan "/ ay" alt satıra tek başına düşüyordu (ekranda ölçüldü). Bileşene yeni alan M2'nin işi |
| 9 | Teklif değişince eski Stripe nesnesi en son kapanır (giriş fiyatı `active=false`, kupon silinir); kapanamazsa yalnız günlük | önce kapatmak | M1a'daki sıra: önce yeni nesne + satır, katalog eskisini artık açmaz. Silinen kupon onu almış aboneliği etkilemez (Stripe) |
| 10 | Türün almadığı alan reddedilir ("deneme teklifi yalnız gün alır (yüzde fazla)") | sessizce atmak | Yönetici yanlış türü seçmiş olabilir |
| 11 | DB kısıtı `planlar_teklif_tutarli` türün alanlarını birlikte zorlar; gün 1–90, yüzde 1–99 | yalnız uygulamada denetim | Tuzak §4; ödeme oturumu bu kısıta güvenip gün/kimlik okur |

Kapsam dışı (Ç108): M1c'de giriş ücretinin çok para birimi; Puck/AI plan
listesinde teklif görünmüyor; dönüşüm değeri.

## Test

`PlanTeklifTest` (20): deneme Stripe'a yazmaz + listede (sözleşme) · giriş:
planın ürününde, planın para biriminde, `recurring` YOK, 0.99 → 99 · indirim:
`percent_off` 50, `once` · değişince eski fiyat `active=false`, kupon silinir,
kaldırınca satır boş · 10 geçersiz gövde (Stripe'a gitmez) · 404/CSRF/Stripe
kapalı · DB kısıtları doğrudan (5 yarım teklif) · ödeme: deneme (kart her
zaman, `trial_period_days`, tek kalem), giriş (iki kalem + deneme), indirim
(`discounts`) · gösterim: giriş ücreti Stripe'tan; yinelenen ya da başka para
birimindeyse planın fiyatı null. `KayitliStripeIstemcisi`'ne `yanitlar`
(stripe-mock her fiyatı yinelenen döndürür). Huni `fiyat.test` 3, panel
`Planlar.test` 6 (teklif sütunu, form gövdeleri SAYI ve fazlasız).

## Kırma (20 deneme, hepsi kırmızı; 2'si ilk yamada KOŞULMADI)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | ödeme teklifi okumaz | 🔴 3 |
| K2 | girişte deneme yok | 🔴 |
| K3 | kartsız deneme (`if_required`) | 🔴 |
| K4 | indirimde kupon yok | 🔴 |
| K5 | giriş fiyatı yinelenen | 🔴 |
| K6 | giriş fiyatı sabit `eur` | 🔴 |
| K7 | kupon `forever` | 🔴 |
| K8 | eski giriş fiyatı kapanmaz | 🔴 |
| K9 | fazla alan kabul | 🔴 2 |
| K10 | gün üst sınırı yok (DB kısıtı 500'e çevirir) | 🔴 |
| K11 | teklifi okunamayan plan fiyatla gösterilir | 🔴 |
| K12 | giriş fiyatında yinelenen denetimi yok | 🔴 |
| K13 | para birimi denetimi yok | 🔴 |
| K14 | kaldırınca tür kalır (DB kısıtı yakaladı) | 🔴 |
| K15 | huni denemede "sonra" yok | 🔴 |
| K16 | huni indirim aralığı sabit "ay" | 🔴 |
| K17 | panel indirimde gün de gönderir | 🔴 |
| K18 | panel teklif sütunu boş | 🔴 |
| K19 | test DB'de `planlar_teklif_tutarli` kaldırıldı | ilk deneme **KOŞULMADI** (zsh `$Q` bölünmedi, §16) → 🔴; kısıt geri eklendi |
| K20 | bölünmez boşluk → düz boşluk | ilk iki deneme **KOŞULMADI** (zsh tek tırnakta ` `'ı karaktere çevirdi, §16) → Python'la 🔴 2 |

## Gerçek istek (Stripe test API, geliştirme DB'si, gerçek `Planlar::teklifAyarla`)

| Teklif | Stripe nesnesi | Gerçek `POST /checkout/sessions` → Stripe'taki oturum |
|---|---|---|
| deneme 7 gün | yok | `amount_total` **0**, kalem `recurring` 0, `payment_method_collection` always |
| giriş 3 gün 0,99 € | `price_…` one_time, 99, eur, planın ürünü | `amount_total` **99**, kalemler: recurring 0 + one_time 99 |
| indirim %50 | kupon `percent_off` 50, `once` | `amount_subtotal` 999, indirim **500**, `amount_total` **499** |

- `GET /huni/keepchat` → `fiyatlar.monthly.teklif` her türde doğru (`giris` tutarı Stripe'tan 0.99).
- Değişim zinciri: giriş → indirim: eski fiyat `active=false`; indirim → giriş: kupon **silindi**
  (`No such coupon`); sonunda teklif kaldırıldı, satır boş. Stripe test hesabında 3 pasif giriş
  fiyatı kaldı (silinemez, satışta değil).
- Paywall ekranı (sablon-fitness, gerçek huni sayfası + ekran kabı): ilk çekimde "/ ay" alt
  satıra tek başına düştü (yetim satır uyarısı) → bölünmez boşluk → "3 gün €0,99," / "sonra
  €9,99 / ay", uyarı yok (yalnız Ç107 boşluk). İndirim ve deneme metni de görüntüde.
- Huni-dev sunucusunun yeni modülü sunduğu `curl` ile okundu (§38).
- **Panel Teklif formu tarayıcıda açılmadı** (giriş kullanıcının hesabı; birim testi 6/6) — Ç108.

## Kontrol

`bin/kontrol` **YEŞİL**: 523 çekirdek testi / 4487 doğrulama; huni, panel, bileşen testleri,
derlemeler. İlk koşu kırmızıydı: biçim (huni, panel) + PHPStan 10 (mixed tür dönüşümleri,
Stripe oturum parametresinde `int|null` gün) — düzeltildi. Disk %99 dolu (2,4 GB boş).

## Açık

Ç108.
