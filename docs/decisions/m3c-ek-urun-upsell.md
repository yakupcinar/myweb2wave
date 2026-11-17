# M3c — ek ürün kataloğu ve ödeme sonrası tek tık upsell (2026-11-04)

Karar `pre-setup.md` A16 (upsell = tek seferlik ek ürün, kayıtlı kartla tek tık, tutar
düğmede, banka isterse 3D Secure) · önceki `m3b-odeme-sonrasi.md`.

## Ölçüm (önce)

- Kayıtlı kartla ikinci ödeme altyapısı yok; ek ürün kavramı yok.
- Gerçek tamamlanmış oturumda: `customer` ve aboneliğin `default_payment_method`'u var
  (Checkout abonelik kipi kartı sonraki ödemeler için saklıyor) — upsell buna dayanabilir.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Ek ürün = Stripe ürünü + **tek seferlik** fiyat, panelden (`ek_urunler`); tutar Stripe'ta | plan kataloğuna karıştırmak | Abonelikten ayrı; L10 tek kaynak |
| 2 | Upsell ödeme sonrası ekranın alanı (`sonrasi.upsell`: ürün, başlık, metin, düğme); tutar düğmede Stripe'tan | blok türü | Yalnız o sayfada anlamlı; fiyat uydurulamaz |
| 3 | Yetki = Stripe oturumu: `complete`, ödenmiş (deneme dahil `no_payment_required`), `metadata.app` aynı, **≤ 1 saat** önce açılmış | ayrı jeton | Oturum kimliği yalnız ödeyende; süre penceresi sızıntı riskini sınırlar |
| 4 | Oturum + ürün başına **bir** satış: tekil kısıt + Stripe idempotency anahtarı; ikinci istek durumu döner, yeni çekim yok | yalnız istemcide düğmeyi kapatmak | İki tık / yenileme iki çekim olmasın |
| 5 | Ödeme **on-session** (müşteri sayfada), `allow_redirects: never`; banka isterse sır + anahtar → Stripe.js `handleNextAction`, sonra aynı istek PaymentIntent'i okuyup satışı kapatır | `off_session` | Müşteri o an tıklıyor; 3D Secure sayfada tamamlanabilir; webhook gerekmez |
| 6 | Oturumun para birimi ürününkiyle uyuşmazsa satılmaz | kurla çevirmek | M1c: sabit tutarlar; ziyaretçi başka birimde ödedi |
| 7 | Kart reddi kalıcı (`basarisiz`), Stripe ulaşılamazsa satır silinir (yeniden denenebilir) | hepsi yeniden denenir | Reddedilen kartı tekrar tekrar zorlamamak |
| 8 | Yeni uç CORS'un yazan listesinde (tuzak §36) | — | Ad GET desenine de uyduğu için köken izni yanıltıcı — ölçüt POST izni (K10) |

## Test

`EkUrunTest` (11): panelden tek seferlik fiyat (1999, `recurring` yok), geçersizde Stripe'a
gitmez; huni yanıtında ürün adı + tutar (sözleşme), yinelenen fiyatta alan yok; satış:
tutar/para birimi/müşteri/kart/`confirm`, yönlendirmesiz, `off_session` yok, ikinci istek
yeni PaymentIntent açmaz; 3D Secure: sır + anahtar, sonra kapanır; uygunsuz oturumlar
(eski, başka uygulama, ödenmemiş, açık, para birimi) çekim yapmaz; kart reddi `basarisiz`;
geçersiz istek / bilinmeyen ürün; CORS POST izni. `SonrasiTest` +1 (upsell kataloğu).
Huni: upsell bileşeni (tutar düğmede, ödendi, 3D Secure akışı, doğrulama tamamlanmazsa
ikinci istek yok, kart reddi Türkçe), ekran upsell'i yalnız fiyat + oturumla çizer. Panel:
ek ürün kartı, Puck gidiş-dönüş. Kayıt istemcisi artık yöntem + HTTP kodu taşıyabiliyor
(`"post /v1/…"`, `__durum`).

## Kırma (16 deneme; K10 ilk koşuda TUTMADI → test eksikti)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | oturum durumu denetimi yok | 🔴 |
| K2 | uygulama denetimi yok | 🔴 |
| K3 | süre penceresi yok | 🔴 |
| K4 | para birimi denetimi yok | 🔴 |
| K5 | ikinci tık yeniden çeker | 🔴 2 |
| K6 | `off_session` | 🔴 |
| K7 | kart verilmez | 🔴 |
| K8 | ek ürün yinelenen fiyatla | 🔴 |
| K9 | huni yanıtında fiyat yok (sadeleştirmeden sonra yeniden: 🔴) | 🔴 |
| K10 | CORS listesinden çıkar | ilk koşu **🟢**: ad GET desenine (`/huni/<ad>`) de uyuyor, köken izni oradan geliyordu → test POST iznini ölçüyor → 🔴 |
| K11 | 3D Secure bekleyen ödendi sayılır | 🔴 |
| K12 | kart reddi kaydedilmez | 🔴 |
| K13 | upsell ürün kataloğu denetimi yok | 🔴 |
| K14 | 3D Secure'dan sonra istek yinelenmez | 🔴 |
| K15 | oturumsuz upsell çizilir | 🔴 |
| K16 | Puck düğmesiz upsell yazar | 🔴 |

## Gerçek istek (Chrome + gerçek Stripe test API)

- Gerçek `EkUrunler::olustur` → "Kişisel koçluk paketi" 19,99 EUR tek seferlik; fitness
  `sonrasi.upsell` → v5; huni yanıtı `ekUrunler.kocluk = 19.99 EUR`.
- Chrome: huni yürüyüşü → gömülü ödeme, test kartı 4242 → abonelik; dönüş adresi
  `…/basarili?huni=sablon-fitness&session_id=…` (Stripe'ta okundu).
- Başarı sayfası ilk açılışta eski hâliyle çizildi (aynı tarayıcıda M3b'deki gibi — modül
  önbelleği, Ç116); gerçek oturum kimliğiyle yeniden açılınca ekran + upsell "Koçluğu ekle ·
  €19,99".
- Tıklama → Stripe'ta PaymentIntent `succeeded`, 1999 eur, aboneliğin kartı ve müşterisi,
  `metadata {app, ek_urun, oturum}`; DB `odendi`; ekranda "Eklendi — ödemeniz alındı."
- Aynı istek `curl` ile yeniden → `{"durum":"odendi"}`, oturumdaki ek ödeme sayısı 1.
- 3D Secure yolu gerçek kartla denenmedi (birim + bileşen testinde) — Ç116.

## Kontrol

`bin/kontrol` **YEŞİL**: 587 çekirdek testi / 4906 doğrulama. Ara kırmızılar: `sonrasi`'nın
isteğe bağlı alanları genişleyince düğme metni denetimi upsell'li tanımı yanlış reddediyordu
(test yakaladı); PHPStan 11 (gereksiz savunma denetimleri) sadeleştirildi.

## Açık

Ç116.
