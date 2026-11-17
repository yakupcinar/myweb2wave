# M5c — kaynak kırılımı, cevaba göre dönüşüm, kohort geliri (2026-11-09)

Karar `pre-setup.md` A18 (kullanıcı: üç rapor da) · önceki `m5b-faturalar.md`.

## Ne yapıldı

Panel Rapor'a (`GET /panel/api/rapor`, sözleşme `PanelRaporu.utm` / `.kohort`, `cevaplar[].abone`):

- **Kaynak kırılımı:** kaynak / ortam / kampanya (+ reklam işareti) başına ziyaret → ödemeye geçti
  → deneme → ödedi → dönüşüm → gelir. Gelir, aralıktaki ziyaretlerin bugüne kadar tahsil edilmiş
  faturaları; para birimi başına, çevrilmeden. En çok ziyaretli 200 grup.
- **Cevaba göre dönüşüm:** Cevaplar tablosuna "Abone" ve "Dönüşüm" sütunları (seçen ziyaretten abone olan, deneme dahil).
- **Kohort geliri:** satırlar ziyaret haftası (UTC, pazartesi), sütunlar ilk 24 saat / 7 / 30 / 60
  gün. Hücre = ziyaret başına birikimli tahsilat. Pencere haftanın en genç ziyareti için dolmadıysa
  hücre "—" (yarım pencere düşük gelir gösterirdi). Dolmuş ama gelir yoksa "0".
- **"Ödedi" tek tanım:** `Rapor::ODEDI` = ilk ödeme tutarı > 0 **ya da** aboneliğin tahsil edilmiş
  faturası. Rapor (sayım, satır, kırılım) ve deneyler aynı sabiti kullanıyor. Deneme ile başlayıp
  sonra ödeyen artık "ödedi" sayılıyor (Ç118 (3)'ün kalanı).
- Satırlardaki "kaynak" da artık tek SQL tanımından geliyor (`Rapor::KAYNAK`); PHP'deki kopya kaldırıldı.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Gelir faturalardan, para birimi başına | çevirip tek toplam | Kur kaynağı yok; toplanan farklı para yanıltır |
| 2 | Kohort ziyaret başına | abone başına | Huni / reklam kararı ziyaret başına gelirle verilir (ROAS'a hazırlık) |
| 3 | Kohort olgunluğu haftanın en genç ziyaretinden | en yaşlıdan · hiç | Yarım dolmuş pencere geliri düşük gösterir |
| 4 | Pencere 0 = ilk 24 saat (`< ziyaret + 1 gün`) | aynı takvim günü | Saat dilimi / gece yarısına bağlı olmasın |
| 5 | "Ödedi" ilk ödeme ya da fatura | yalnız ilk ödeme | Deneme sonrası ödeyen ödemiş sayılmalı; fatura olayı kaçarsa ilk ödeme yine sayılır |
| 6 | Cevap son hâli | ödeme anındaki cevap (olay deposundan) | Basit; olay deposu ileride bunu mümkün kılar |
| 7 | Kırılımda gelir ayrı sorgu + PHP'de anahtarla birleşim | ilişkili alt sorgu | İlk sürüm her grup için tüm ziyaretleri yeniden tarıyordu; üstelik NULL reklam işaretinde eşleşmiyordu (aşağıda) |

## Test

- `PanelTest::testUtmCevapVeKohort`: 10 ziyaret, beklenenler elle hesaplandı (tuzak §3), yorumda
  hesap. Deneme + iki fatura, ilk ödeme + fatura, yalnız ödemeye geçen, faturasız deneme, TRY, yalnız
  kaynağı farklı grup, yalnız ortamı farklı grup, ortam NULL ve gelirli organik, ödemeye geçmeyen,
  aralık dışı (100 gün).
- `testKohortOlgunlukHaftaninEnGencZiyaretinden`: 30 gün öncesini içeren haftada pazartesi 00:00 ve
  "30 gün önce" ile hafta sonu arası. Ziyaretler her gün aynı haftaya düşüyor, 30 gün penceresi
  ikisinin arasında kalıyor.
- `DeneylerTest`: deneme sonrası fatura → ödedi 2.
- Panel `Rapor.test.tsx`: üç tablonun hücreleri; iki para birimi "€29,97 · ₺20,00"; "0" ile "—" ayrımı.

## Kırma (15/15 🔴, dördü ilk koşuda tutmadı)

| # | Bozulan | Sonuç |
|---|---|---|
| C1 | ödedi faturasız | 🔴 2 |
| C2 | deneyler kendi ödedi tanımı | 🔴 |
| C3 | kohort olgunluk yok | 🔴 2 |
| C4 | pencere 0 = 0 gün | 🔴 |
| C5 | kişi başı yerine toplam | 🔴 |
| C6 | olgunluk en yaşlı ziyaretten | ilk koşu **🟢**: testte bir haftanın ziyaretleri aynı yaştaydı → olgunluk testi → 🔴 |
| C7 | cevap abonesi = ödedi | 🔴 |
| C8 | kırılımda deneme ödeyeni de sayar | 🔴 (ilk kalıp iki kez geçiyordu → KOŞULMADI, düzeltildi) |
| C9 | gelir eşleşmesi kaynaksız | ilk sürümde 🟢 (kampanya zaten ayırıyordu) → yalnız kaynağı farklı grup → 🔴 |
| C10 | gelir eşleşmesi ortamsız / NULL ortam | ilk sürümde 🟢 → organik gelirli ziyaret eklenince test **gerçek hatayı** buldu: REKLAM NULL, organik gelir boştu (§55). Sorgu yeniden yazıldı; sonra yalnız ortamı farklı grup → 🔴 |
| C11 | panel kırılım dönüşümü yalnız ödedi | 🔴 |
| C12 | panel olgun değil "0" | 🔴 |
| C13 | panel cevap dönüşümü | 🔴 |
| C14 | gelir aralık dışı ziyaretten | ilk koşu 🟢 → 100 günlük ziyaret → 🔴 |
| C15 | ödemeye geçti tüm ziyaret | ilk koşu 🟢 → oturumsuz ziyaret → 🔴 |

Yeniden yazılan sorguda `coalesce(REKLAM, false)` savunması gereksizleşti (iki sorgu aynı CTE'den,
NULL anahtar iki yanda aynı). Kaldırılıp ölçüldü: yeşil. Kod kaldırıldı (§10). İlk sürümdeki `DISTINCT`'ler
de imkânsız bir durumu savunuyordu (her Checkout oturumu kendi aboneliğini açar), kaldırıldı.

## Gerçek sistem (dev, 116 ziyaret)

`Rapor` servisi 90 günle: kırılım 13 grup, kohort 4 hafta, cevaplar 17 satır. Süre (3 koşu):
kırılım 31,0 / 0,9 / 0,9 ms, kohort 2,6 / 0,6 / 0,6 ms. İlk sürümün kırılımı tek ilk koşuda 107 ms
idi (aynı koşulda karşılaştırma değil). Dev'de gelir yok: faturaların ödeme oturumu bağı yok (Ç120).
**Panel tarayıcıda açılmadı** (giriş kullanıcıda); tablolar happy-dom testinde.
