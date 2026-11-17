# M2a — paywall plan seçici ve seçili plana göre ödeme koşulu (2026-11-04)

Karar `pre-setup.md` A15 (M2'nin bölünmesi ve kullanıcı kararları) · önceki bloklar
`m1a-fiyat-katalogu.md`, `m1b-teklifler.md`, `m1c-cok-para-birimi.md`.

## Ölçüm (önce)

- Paywall huninin son adımı: tek `plan`, sabit alanlar (başlık, metin, düğme,
  faydalar, yasal bağlantılar); "tam bir kez ve EN SONDA" kuralı.
- Puck'ta paywall üst düzey bileşen, blok yuvası yok (Ekran'da var).
- Fiyat kartı tek plan; ödeme koşulu (yenileme, deneme sonrası tahsilat) hiçbir
  yerde yazmıyor — yalnız yöneticinin serbest metni.
- Plan hatası "plan bu uygulamada yok" — hangi planların olduğunu söylemiyor;
  AI anahtarı tahmin etmek zorunda (AI'a plan listesi verilmiyor).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | M2 dörde bölündü: **M2a** plan seçici + ödeme koşulu · M2b teklif bitişi + sayaç, güven rozetleri, şart onayı · M2c birden çok paywall adımı (cevaba göre) · M2d gömülü ödeme + Apple/Google Pay | tek blok | Her biri ayrı ölç-kır; sayaç teklifin bitişine bağlı (A15) |
| 2 | Paywall'da `planlar` (2–3, sıralı) + `plan` = varsayılan seçili (içinde) + isteğe bağlı `onerilen` | paywall'a genel blok yuvası (Puck §42 riski) | Alan olarak eklenir: tanım geriye uyumlu (planlar yoksa tek plan), Puck alanı basit |
| 3 | Kart başlığı planın **panel adı** (huni yanıtı `fiyatlar.<plan>.ad`) | paywall'da plan başına etiket | Tek kaynak; ad değişince her hunide değişir |
| 4 | **Ödeme koşulu** seçili plandan otomatik, düğmenin hemen altında: bugün ne ödenir, ilk ödeme ne zaman, ne ile yenilenir | yöneticinin serbest metni | Yenileme bilgisinin ödeme düğmesine yakın, tutarla birlikte durması beklenir; Stripe'tan okunan değerle ayrışamaz |
| 5 | Koşulda **iptal sözü yok** | "İstediğin an iptal edebilirsin" | İptal yolu (M6, müşteri portalı) yok — söz verilemez |
| 6 | İndirimde tutar hesaplanmaz, yüzde yazılır | "İlk ay €4,99" | M1b ölçümü: Stripe yuvarlaması 1 kuruş ayrışır |
| 7 | Plan hatası uygulamanın planlarını sayar ("planlar: monthly, yearly" / "hiç plan yok — panel → Planlar") | AI istemine plan listesi koymak | Hem yönetici hem AI düzeltir; istemi uygulamaya bağlamaz |
| 8 | AI yönergesi: planlar/onerilen nasıl yazılır; fiyat, deneme, yenileme metne yazılmaz | — | Ekran kendisi yazar |
| 9 | Puck önizlemesinde kartlar plan anahtarıyla, fiyatsız | panelde fiyat okumak | Önizleme fiyat taşımıyor (L10'dan beri) |
| 10 | `onerilen` yalnız seçicide; tek dolu plan satırı seçici sayılmaz, önerilen de yazılmaz | — | Çekirdek ikisini de reddeder; Puck geçersiz tanım üretmesin |

## Test

`PlanSeciciTest` (12, saf): tek plan ve 2/3'lü seçici geçerli; tek planlı seçici,
dört plan, tekrar, liste değil, varsayılan dışarıda, bilinmeyen (planları sayan
hata), metin olmayan, seçicisiz önerilen, dışarıdaki önerilen; plansız uygulamada
panele yönlendiren hata. `PaywallPlanlariTest`: yıllık plan kataloğa, seçicili
paywall yayınlanır, huni yanıtı sözleşmeye uyar ve iki planı adlarıyla taşır, sıra
korunur, yıllıkla ödeme `price_yillik`. `HuniTest` bilinmeyen plan metni. Fiyat
yanıtlarına `ad` (4 test güncellendi). Huni: `kosulMetni` 4 durum (elle) +
oynatıcı: sıra, varsayılan seçili, Önerilen yalnız yıllıkta, koşul seçimle değişir,
ödemeye seçili plan. Panel: Puck gidiş-dönüş (sıra, önerilen), tek dolu satır → yok.

## Kırma (15 deneme, hepsi kırmızı)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | 2–3 sayı denetimi yok | 🔴 2 |
| K2 | varsayılan planlar içinde değil kabul | 🔴 |
| K3 | önerilen denetimi yok | 🔴 2 |
| K4 | hata planları saymaz | 🔴 3 |
| K5 | planlar/onerilen alanı kabul edilmez | 🔴 11 |
| K6 | fiyatta `ad` yok | 🔴 2 |
| K7 | seçici çizilmez | 🔴 |
| K8 | ödemeye varsayılan plan gider | 🔴 |
| K9 | koşul varsayılan plandan | 🔴 |
| K10 | Önerilen her kartta | 🔴 |
| K11 | denemede "Bugün ödeme yok" yok | 🔴 |
| K12 | yenileme aralığı hep "ay" | 🔴 |
| K13 | tek dolu satır seçici sayılır | 🔴 |
| K14 | seçicisizde önerilen yazılır | 🔴 |
| K15 | seçim işareti (`aria-checked`) hep false | 🔴 |

## Gerçek istek

- Gerçek `Planlar::olustur` → KeepChat `yearly` "Yıllık" 59,99 EUR/yıl (Stripe test).
- AI aracıyla (`huni_oku` → `taslak_kaydet`) sablon-fitness paywall'ına
  `planlar: [yearly, monthly]`, `onerilen: yearly`; aylığa deneme 7 gün (gerçek).
- Ekran (390×844, ekran kabı): uyarı **yok**; Yıllık kartında "Önerilen", aylık
  seçili "7 gün ücretsiz, sonra €9,99 / ay", düğme altında koşul. İlk çekimde koşul
  "her / ay" diye kırılıyordu → "her ay" bölünmez.
- Chrome (önizleme): yıllığa tıklayınca `aria-checked` geçti, koşul "Bugün €59,99
  ödenir; abonelik her yıl €59,99 ile yenilenir."
- Gerçek `POST /checkout/sessions` `yearly` → Stripe oturumu eur 5999, Adaptive kapalı.
  Sonra deneme kaldırıldı; dev'de yıllık plan ve fitness seçicisi kaldı.
- Panel düzenleyicisi (Puck alanları) tarayıcıda açılmadı (giriş kullanıcının) — Ç110.

## Kontrol

`bin/kontrol` **YEŞİL**: 549 çekirdek testi / 4660 doğrulama; huni 98, panel 130.
İlk koşu kırmızıydı: bileşen paketinin biçimi (o pakette `format` betiği yok,
`prettier --write` ile).

## Açık

Ç110.
