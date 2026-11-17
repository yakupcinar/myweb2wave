# E1 — huninin e-posta adımı (2026-11-03)

**İstek:** kullanıcı 2026-11-03: "add email capture step first, then M1b" —
Ç97'nin (1) numaralı eksiği; en iyi hunilerde kişisel sonuç / paywall'dan
hemen önce adres alınır (satın almayana yeniden ulaşma).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Soru bloğunun yeni yanıt biçimi: `secimTuru: "eposta"` (serbest yanıt ailesi: seçeneksiz, düzensiz, `yanitZorunlu` olabilir) | ayrı adım türü (`tur: eposta`) | Ekran blokları (başlık, metin, kutu) e-posta ekranında da gerekli; ayrı tür onları ya kopyalar ya kaybeder |
| 2 | İsteğe bağlı `izinMetni` (1–200) varsa pazarlama izni kutusu çizilir, işaretsiz başlar; yoksa izin alınmaz | izni hep sormak · kutuyu önceden işaretli | Metin yazarın (hukuki ifade huniye göre); önceden işaretli kutu AB'de geçerli izin değil |
| 3 | Adres ayrı uca (`POST /huni/ziyaretler/{z}/eposta`) ve ayrı tabloya (`huni_epostalari`) | ilerleme ucunun `cevapMetni`'si | Serbest yanıtlar rapora (Rapor), AI'a ve koşul olaylarına gidiyor — adres oraya düşmemeli |
| 4 | E-posta cevabı koşul özelliği DEĞİL (`cevap.<e-posta adımı>` reddedilir, panel seçicisi göstermez) | `var/yok` izni | Tanıma adres yazılma yolu kapanır; "verdi mi" dallanması bugün istenmedi |
| 5 | Ödeme oturumu `customer_email` ile açılır (ziyaret aynı uygulamanınsa) | adresi istemciden almak | Ziyaret kimliği zaten gidiyor; başka uygulamanın ziyareti adres sızdırmaz |
| 6 | Adres doğrulanmaz, kişiye (`kisiler`, A5) bağlanmaz | doğrulama kodu | A5 yalnız doğrulanmış adresi bağlar; burada amaç doldurma + izinliyse M8 |
| 7 | İzinsiz adres 90 günde silinir (`app:huni-metin-cevaplari-sil` aynı koşuda); izinli kalır | süresiz saklama | Amacı yalnız ödeme sayfasıydı; izinli adres M8 dizisine |
| 8 | Adres küçük harfle saklanır; DB kısıtı da (`eposta = lower(eposta)`) | yalnız uçta normalleştirme | Abonelik eşleşmesi `lower(email)` — tuzak §4: kısıtı ayrı test ölçer |
| 9 | Geçersiz/eksik adreste düğme kapalı (`Adim`); oynatıcıda ikinci denetim YOK | iki katman | Kırma K16: oynatıcı denetimi kaldırılınca test yeşil kaldı — kapalı düğme tıklanmıyor, ikinci denetim ölçülemez (kural 6a) |

İstemci biçim denetimi kaba (`x@y.z`); çekirdek `FILTER_VALIDATE_EMAIL`.
Arada kalan adres (ör. Unicode yerel kısım) istemcide geçer, çekirdekte 422
olur; oynatıcı hatayı yutar, huni sürer, adres kaydedilmez → Ç98.

## Değişen yerler

Göç `Version20261103140000` · `EpostaController` · `Ziyaretler::epostaKaydet/eposta`
· `CheckoutController` (customer_email) · `MetinCevaplariSilCommand` · CORS
(tuzak §36) · `Tanim` (tür, izinMetni, koşul yasağı, AI yönergesi) ·
sözleşme (uç + `secimTuru` + `izinMetni`) → huni/panel tipleri + AI şeması ·
`huni-bilesenleri` (alan, kutu, düğme kapalılığı, CSS) · oynatıcı ·
`ziyaret.ts` · panel (Puck alanı, dönüştürme, bekçi, koşul seçicisi).
Test TRUNCATE listelerine `huni_epostalari` (FK yüzünden Postgres listesiz
TRUNCATE'i reddediyor). ⚠️ İlk toplu `sed` `FROM huni_metin_cevaplari`
sorgularını da çapraz birleşime çevirdi — boş tabloyla çapraz birleşim 0
satır, `say(...) === 0` iddiası yalancı yeşil olurdu; yalnız TRUNCATE
satırlarında bırakıldı, `git diff` ile ölçüldü.

## Kırma denemeleri (20; 19 kırmızı, 1 kodu kaldırttı)

| # | Bozulan | Düşen test |
|---|---|---|
| K1 | uçta FILTER_VALIDATE_EMAIL | EpostaTest geçersiz gövde |
| K2 | izin hep false | izin ilk anı |
| K3 | izin anı `coalesce` | izin ilk anı |
| K4 | `customer_email` | ödeme sayfası adresle |
| K5 | `eposta()` uygulama süzgeci | başka uygulamanın ziyareti |
| K6 | silmede `pazarlama_izni_at IS NULL` | izinli kalır |
| K7 | CORS'ta `eposta` | AtifTest CORS |
| K8 | küçük harf normalleştirme | adres küçük harfle |
| K9 | koşulda e-posta yasağı | HuniTest "e-posta cevabı koşulda" |
| K10 | `izinMetni` yalnız e-postada | HuniTest |
| K11 | e-posta serbest yanıt ailesinde | HuniTest (5) |
| K12 | fazla alan reddi | EpostaTest |
| K13 | adres ilerlemeye de gider | oynatıcı E1 |
| K14 | oynatıcıda izin hep false | oynatıcı E1 |
| K15 | düğme bozuk adreste açık | oynatıcı E1 |
| K16 | oynatıcıda ikinci geçerlilik denetimi | **yeşil** → kod kaldırıldı (karar 9) |
| K17 | panel koşul seçicisinde e-posta süzgeci | yapilandirma (7) |
| K18 | dönüştürmede `izinMetni` | donustur |
| K19 | bekçide zorunlu e-posta kapalı | akis |
| K20 | e-postada düğme gönderir | oynatıcı E1 (2) |

Her deneme `cp` yedeğiyle geri alındı, `cmp` ile doğrulandı.

## Ölçümler

- `bin/kontrol` YEŞİL (oynatıcı denetimi kaldırıldıktan sonra yeniden): 454 çekirdek testi / 3654 iddia; panel 116, huni 90, bileşen 96.
- Gerçek istek (dev, 8081): uç 204, bozuk adres 422, `OPTIONS` + `Origin:
  http://localhost:4321` → izinli köken; satır `deneme.e1@ornek.com | t`.
- Gerçek Stripe test API: oturum `cs_test_…` geri okundu →
  `customer_email = deneme.e1@ornek.com`, `livemode=false`.
- `uyku-ornek` taslağına `eposta` ekranı (yükleme → **e-posta** → özet);
  telefon boyu ekran görüntüsü: alan, işaretsiz kutu, kapalı düğme.
  Tarayıcıda gerçek ziyaretle yürünmedi (huni yayında değil) → Ç98.
