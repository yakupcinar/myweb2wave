# M6a — panelden dönem sonunda iptal ve son faturanın iadesi (2026-11-09)

Karar `pre-setup.md` A19 (kullanıcı: dönem sonunda iptal, son faturayı tam iade, portal Stripe'ta) · önceki `m5d-meta-harcama.md`.

## Ne yapıldı

- **Panel Kullanıcılar** satırında "İşlem" sütunu var. Abonelik sürüyorsa "Dönem sonunda iptal et",
  iptal planlıysa "İptal planlı — <dönem sonu>" ile "İptali geri al" görünüyor. Son tahsil edilen
  faturanın iade edilmemiş kalanı varsa "Son faturayı iade et (€6,99)" çıkıyor. Her işlem iki adımlı
  onay istiyor (sayfada "emin misiniz? Evet / Vazgeç", tarayıcı iletişim kutusu yok); hata satırda gösteriliyor.
- **Uçlar** (sözleşmede): `POST|DELETE /panel/api/abonelikler/{sub}/iptal` → Stripe
  `cancel_at_period_end` true/false; `POST …/iade` → son faturanın ödemesi (`InvoicePayment`, durumu `paid`)
  → `refunds.create(payment_intent, kalan tutar)`. Kabuğun CSRF jetonu gerekiyor. Stripe'ın **döndürdüğü**
  iptal durumu hemen yazılıyor (webhook gecikse de panel doğru), iade de hemen yazılıyor.
- **Webhook:** abonelik olayı `cancel_at_period_end` ve dönem sonunu (**kalemden**,
  `items.data[0].current_period_end`) yazıyor. `invoice_payment.paid` fatura ↔ ödeme bağını tutuyor
  (`fatura_odemeleri`). `charge.refunded` faturanın iadesini Stripe'ın **birikimli** `amount_refunded`'ı
  olarak yazıyor (tutarla sınırlı, azalmıyor); artış aboneliğe bağlı ziyarete yeni sözlük olayı `odeme.iade` olarak gidiyor.
- **Gelir = fatura − iade:** kaynak kırılımı, kohort (faturanın ödeme anında) ve ROAS.
- Göç `Version20261109200000`.

## Ölçüm (önce, gerçek Stripe test hesabı)

Abonelikte `current_period_end` yok (kalemde: 1794813645). `cancel_at_period_end` abonelikte. Faturada
`payment_intent` / `charge` / `payments` alanı yok; `InvoicePayment` listesi `payment.payment_intent` veriyor.
Ödemede (`charge`) `invoice` yok, `payment_intent` var. Hesapta 13 `invoice_payment.paid` olayı var, her biri
`invoice` + `payment.payment_intent` + tutar taşıyor (tuzak §54 ailesi).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | İptal dönem sonunda, geri alınabilir | hemen / seçmeli | Kullanıcı |
| 2 | Son faturanın **kalanı** tam iade | kısmi tutar seçmek | Kullanıcı; kalan, önceki kısmi iade (Stripe panelinden) varsa iki kez iade edilmesin diye |
| 3 | İade birikimli `amount_refunded`'dan | olaydaki iade tutarını eklemek | Tekrar ya da sıra dışı olay iki kez düşmez |
| 4 | Fatura ↔ ödeme bağı webhook'tan, iade anında API'den | yalnız API | Stripe panelinden yapılan iade de gelirden düşsün |
| 5 | Panel Stripe'ın yanıtını yazar | istenen değeri | Stripe reddettiyse / farklıysa panel yalan söylemesin |
| 6 | Tam iade edilmiş fatura yine "ödedi" sayılır | ödedi'den çıkarmak | "Ödedi" = para çekildi oldu (A17); gelir ayrı düşülür |
| 7 | Kohortta iade faturanın penceresinde | iade anında | Basit; iade genelde aynı dönemde |

## Test

- `PanelAbonelikTest` (6): iptal / geri al (Stripe'a giden değer + yazılan durum), Stripe yanıtı yazılıyor,
  girişsiz / jetonsuz / bilinmeyen abonelik (Stripe'a istek yok), son faturanın kalanı (eski fatura dokunulmuyor,
  `paid` olmayan ödeme atlanıyor, olay `odeme.iade` 699, ikinci istek 422 ve Stripe'a gitmiyor), faturasız iade
  yok, listede iptal planı / dönem sonu / son fatura.
- `OlaylarTest` +2: iade birikimli (300, tekrar 300, 500, 5000 → 999; olaylar 300 / 200 / 499; bilinmeyen
  ödeme yok sayılıyor; bağ faturadan önce), iptal planı ve dönem sonu kalemden.
- `PanelTest` +1: iade düşülüyor (kırılım, kohort, ROAS 0,5).
- Panel `Kullanicilar.test.tsx` +3: düğmeler duruma göre, iki adımlı onay ve jeton, Stripe hatası satırda.

## Kırma (19/19 🔴)

| # | Bozulan | Sonuç |
|---|---|---|
| A1 | iptal değeri ters | 🔴 |
| A2 | Stripe yanıtı yerine istenen | 🔴 |
| A3 | kalan yerine tam tutar | 🔴 |
| A4 | son yerine ilk fatura | 🔴 |
| A5 | ödenmemiş ödemenin kimliği | 🔴 |
| A6 | "zaten iade" denetimi yok | 🔴 |
| A7 | CSRF yok | 🔴 |
| A8 | iade birikimli değil (eklenir) | 🔴 |
| A9 | tekrar olay da yazar | 🔴 |
| A10 | olay artış değil toplam | 🔴 2 |
| A11 | iptal planı yazılmaz | 🔴 |
| A12 | dönem sonu üst düzeyden | 🔴 |
| A13–A15 | kırılım / kohort / ROAS iadeyi düşmez | 🔴 (her biri) |
| A16 | listede en eski fatura | 🔴 |
| A17 | panel onaysız gönderir | 🔴 2 |
| A18 | tam iade edilmiş faturada düğme | 🔴 |
| A19 | bitmiş abonelikte iptal düğmesi | 🔴 |

Hepsi iddia hatası. A15 ve A16'yı ölçen testler kırmadan önce eklendi; gözden geçirmede eksikleri görüldü.

## Gerçek sistem (2026-11-09, dev + Stripe test modu)

Panel ucu girişli, giriş kullanıcıda. Bu yüzden aynı servis (`AbonelikYonetimi`) dev'de gerçek Stripe test API'siyle çağrıldı:
- M4b'nin açık kalan deneme aboneliği: iptal → db `true`, Stripe `true`; geri al → `false` / `false`; yeniden iptal
  → `true` / `true`. **Son hâli: deneme sonunda biter**, ücret çekilmez (M4b'den kalan temizlik işi).
- Gerçek 9,99 EUR test faturası iade edildi: Stripe `999 eur succeeded`, db `iade_kucuk = 999`; ikinci istek
  "son fatura zaten iade edildi" (Stripe'a gitmedi).
- **Panel düğmeleri tarayıcıda denenmedi** (giriş); happy-dom testinde ölçüldü. Webhook olayları dev'e gelmiyor (Ç120).
