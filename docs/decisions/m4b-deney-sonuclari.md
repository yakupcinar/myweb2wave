# M4b — deney sonuçları: oranlar + kazanma olasılığı (2026-11-09)

Karar `pre-setup.md` A17 · önceki `m4a-deneyler.md`.

## Ne yapıldı

Panelde her aktif / durmuş / bitmiş deneyin altında sonuç tablosu: varyant başına
**ziyaret · ödemeye geçti · abone oldu · dönüşüm (abone / ziyaret) · ödedi · kazanma
olasılığı**. Webhook tamamlanan abonelik oturumuna abonelik anını (`abonelik_at`) ve
Stripe'ın oturum tutarını + para birimini yazar. Yeni uç
`GET /panel/api/uygulamalar/{app}/deneyler/{ad}/sonuclar` (sözleşmede).

## Ölçüm (önce) ve yanlış varsayım

- Rapor "ödedi"yi `odeme_oturumlari.tamamlandi_at`'tan sayıyor; webhook bunu yalnız
  `payment_status = paid`'de yazıyor.
- Varsayımım: kartlı denemede Stripe `no_payment_required` der → deneme hiç sayılmaz.
  Kullanıcıya dönüşüm sorusunu bu (ölçülmemiş) varsayımla sordum.
- **Gerçek ölçüm çürüttü:** Stripe test hesabında tamamlanmış denemeli oturum yoktu;
  yerel huniden 7 günlük denemeli oturum test kartıyla tamamlandı → `payment_status = paid`,
  `amount_total = 0` (tuzak §52). Yani deneme "ödedi" sayılıyordu — tersi.
- Kullanıcı kararı (dönüşüm = abone oldu, deneme dahil) bundan etkilenmedi; değişen
  "ödedi"nin kaynağı: Stripe etiketi yerine tutar.
- Ayrıca: yerelde Stripe CLI yok → dev'deki gerçek ödemelerin çoğunun webhook'u hiç
  işlenmemiş (19 oturumdan 1'i tamamlanmış görünüyordu).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Dönüşüm = abone oldu (`paid` ya da `no_payment_required`), deneme dahil — **kullanıcı** | yalnız para ödeyen · ikisine ayrı olasılık | Denemeli hunide sonuç hemen; ödeme deneme bitince ve ziyarete bağlı değil (Ç118 (3)) |
| 2 | "Ödedi" = oturum tutarı > 0 (`amount_total`, küçük birim, para birimiyle; çevrilmez) | Stripe `paid` etiketi | Ölçüldü: denemede de `paid`, tutar 0 |
| 3 | Tutar gelmezse abonelik yine yazılır; tekrar gelen olay anı/tutarı değiştirmez | tutarı tamamlanma şartı yapmak (M4b taslağı) | Eksik alan sessizce abone kaybettirmesin |
| 4 | `tamamlandi_at` anlamı ve rapor / reklam olayı **değişmedi** | raporu da düzeltmek | Kapsam dışı; kullanıcı kararı gerekiyor (Ç118 (1)(2)) |
| 5 | Sayım ziyaret başına (iki oturum açan ziyaret bir) | oturum başına | Oran ziyaretçi oranı olmalı |
| 6 | Kazanma olasılığı: Beta(1, 1) önsel, P(en iyi) = ∫ fᵢ Π Fⱼ, 20 000 noktalı ızgara, yoğunluğun olduğu bölgeye (ortalama ± 10σ) daraltılmış | Monte Carlo · normal yaklaşım | Belirlenimci (test edilebilir), az veride doğru; 4 varyant 29 ms |
| 7 | Sonuçlar taslakta yok; aktif, durmuş, bitmişte var | — | Taslakta atama yok |

## Test

`DeneylerTest` +3: kazanma olasılığı **Python'un bağımsız hesabıyla** (tuzak §3): iki
varyant Evan Miller kapalı toplamı (`lgamma`) — 1/6, 0,853284, 0,960623, 0,928017,
0,164664 (geniş ↔ dar), 0,5, 0,921074 (1 M), 0,868144 (10 M, σ ≈ 1e-4); üç varyant 2 M
örnekli Monte Carlo; veri yokken / eşit veride eşit. Sonuçlar: ziyaret başına sayım, deneme
(tutar 0) abone ama ödemedi, `no_payment_required` abone, başka uygulama sayılmaz,
ziyaretsiz varyant 0, olasılık abone/ziyaret sayılarından. Panel ucu (giriş, sözleşme,
404). `StripeWebhookControllerTest` +1: beş oturum biçimi (deneme `paid`/0, ödendi,
`no_payment_required`, `unpaid`, tutarsız) + tekrar olay. Panel `Deneyler.test.tsx` +1.

## Kırma (16 deneme; ilk koşuda 4'ü TUTMADI → testler güçlendi)

| # | Bozulan | Sonuç |
|---|---|---|
| B1 | `no_payment_required` abone sayılmaz | 🔴 |
| B2 | `unpaid` de abone | 🔴 |
| B3 | tekrar olay abonelik anını ezer | 🔴 |
| B4 | dönüşüm `tamamlandi_at`'tan | ikinci koşu **🟢**: test yardımcısı iki anı hep birlikte yazıyordu → `no_payment_required` ziyareti eklendi → 🔴 |
| B5 | uygulama süzgeci yok | 🔴 |
| B6 | oturum başına sayım | ilk yama SQL hatası (sahte, §8) → geçerli yama → 🔴 |
| B7 | olasılık ödemeye geçenden | ikinci koşu **🟢** (yeni veride "a > b" ayırt etmiyordu) → olasılık abone/ziyaret sayılarına bağlandı → 🔴 |
| B8 | önsel yok | 🔴 |
| B9 | ızgara daraltılmaz | ilk koşu **🟢** (en dar test durumu tam ızgarada da çözülüyordu) → 10 M ziyaret durumu → 🔴 |
| B10 | birikimde yarım ağırlık | 🟢 — etkisi ızgara adımı mertebesinde; "simetri için" yorumu yanlıştı (simetriyi normalleştirme sağlıyor, tuzak §10) → **kod kaldırıldı** |
| B11 | seyrek ızgara (200) | ilk koşu 🟢 → 10 M durumu → 🔴 |
| B12 | panel dönüşüm ödedi'yle | 🔴 |
| B13 | panel taslakta sonuç | 🔴 |
| B14 | "ödedi" Stripe `paid` etiketinden | 🔴 |
| B15 | tutarsız oturum abone yazılmaz | ilk yama KOŞULMADI (kalıpta fazladan `\`) → 🔴 |
| B16 | tekrar olay tutarı ezer | 🔴 |

## Gerçek istek (2026-11-09, dev + Stripe test API)

- `GET /huni/keepchat?huni=sablon-dil&tohum=tohum-0001` → `{baslik: b}`; `POST /huni/ziyaretler`
  atamayla; `POST /checkout/sessions` (aylık, 7 gün deneme; teklif bitişi dev'de geçici ileri
  alındı, sonra geri) → Chrome'da Stripe sandbox "7 days free", test kartı 4242… → başarı sayfası.
- Stripe API: `status=complete payment_status=paid amount_total=0`. Stripe CLI yok → gerçek
  `checkout.session.completed` olayı API'den alınıp yerel webhook'a imzalı verildi → 200;
  satır `abonelik_at` dolu, `tutar_kucuk = 0`, `eur`.
- Servisten `sonuclar('baslik')`: b = 2 ziyaret · 1 ödemeye geçti · 1 abone · 0 ödedi; a = 0;
  olasılık 0,5 / 0,5 (Beta(2,2) ile Beta(1,1) aynı ortalama — beklenen).
- Göç doldurması dev'de: geçici abonelik + oturum çifti → geri al / uygula → `abonelik_at`
  `subscriptions`'tan, ilgisiz oturum boş, `tamamlandi_at` değişmedi; geçici veri silindi.
- Süre: 4 varyant 29 ms, 6,9 MB.
- Panel tablosu tarayıcıda açılmadı (giriş — kullanıcı, Ç117 (4)).
