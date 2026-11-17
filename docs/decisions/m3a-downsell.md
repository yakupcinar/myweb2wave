# M3a — downsell penceresi (2026-11-04)

Karar `pre-setup.md` A16 (kullanıcı 2026-11-04: tetik = geri tuşu + ödeme panelini
kapatma; upsell = tek seferlik ek ürün; ödeme sonrası = hunide düzenlenebilir ekran).

## Ölçüm (önce)

- Paywall'da geri tuşu bir önceki adıma dönüyor (`popstate`, işleyici ilk çizimde bağlı,
  güncel adımı göremiyor). Gömülü ödeme paneli kapatılınca paywall'a dönülüyor.
- Ziyaretçiyi paywall'dan çıkarken tutan hiçbir şey yok.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Tetik: paywall'da geri tuşu + gömülü ödeme panelini ödemeden kapatma (kullanıcı) | masaüstü çıkış niyeti; yalnız panel | Mobil reklam trafiğinde çalışan iki an |
| 2 | Paywall başına, ziyaretçi başına **bir kez**: sekme oturumu deposu (yenilemede de), depo kapalıysa bellek | her geri denemesinde | Geri tuşunu kilitlemek (tuzak hissi) yok; ret → ikinci geri normal çalışır |
| 3 | Geri tuşunda tarih girdisi geri itilir (`pushState`) — sayfada kalınır | geri gidip pencereyi önceki adımda göstermek | Teklif paywall bağlamında anlamlı |
| 4 | Downsell = katalogdan bir plan + başlık/düğme (+ metin); fiyat ve ödeme koşulu pencere Stripe'tan yazar | yöneticinin fiyat metni | M2a ile aynı: tek kaynak, uydurma tutar yok; AI'a "sahte aciliyet yazma" |
| 5 | Kabul = o planla ödeme (gömülü/barındırılan); paywall şart onayı istiyorsa pencerede de aynı kutu (aynı durum) | onaysız kabul | Şart onayı kuralı downsell'de delinmesin |
| 6 | Pencere tema renkleriyle (ödeme paneliyle aynı katman) | Stripe beyazı | Huninin parçası |

## Test

`DownsellTest` (7): geçerli (metinli/metinsiz); düğme yok, fazla alan, nesne değil,
bilinmeyen plan (planları sayar), boş başlık, uzun metin. Oynatıcı (5): ilk geri →
pencere, paywall'da kalınır, fiyat + koşul (elle); ret → ikinci geri önceki adıma,
pencere yok; kabul → downsell planıyla ödeme; panel kapatma → pencere; depo kapalıyken
de bir kez; sekmede gösterilmişse (yenileme) açılmaz. Puck gidiş-dönüş.

## Kırma (10 deneme; K6 ilk iki koşuda TUTMADI → test eksikti)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | plan katalog denetimi yok | 🔴 |
| K2 | alan kümesi denetimi yok | 🔴 2 |
| K3 | başlık denetimi yok | 🔴 |
| K4 | geri tuşu pencere açmaz | 🔴 2 |
| K5 | sayfada kalınmaz | 🔴 |
| K6 | bellekteki "bir kez" işareti yok | ilk koşu **🟢**: depo kapalı durumu hiç denenmiyordu → test eklendi; ikinci koşu yine **🟢**: `Storage.prototype.getItem` sahtesi happy-dom'da hiç tetiklenmedi → `window.sessionStorage` erişimi hata verdirildi → 🔴 |
| K7 | yenilemede hatırlamaz | 🔴 |
| K8 | panel kapatma tetiklemez | 🔴 |
| K9 | kabul paywall'ın planıyla öder | 🔴 |
| K10 | Puck boş düğmeyle downsell yazar | 🔴 |

## Gerçek istek

- Fitness hunisine (AI aracı + `app:huni yayinla`) downsell: yıllık plan. İlk denemede
  metne "Ayda yaklaşık 5 €" yazdım — kendi yönergemizin yasakladığı elle fiyat; kaldırıldı (v3).
- Chrome, canlı huni, 16 adım betikle; paywall'da **gerçek `history.back()`** → pencere,
  paywall'da kalındı; metin: başlık, açıklama, "€59,99 / yıl", şart onayı (paywall istiyor),
  düğme kilitli, koşul satırı. "Hayır, teşekkürler" → ikinci `history.back()` → "Kilo verme
  planın hazır", pencere yok.

## Kontrol

`bin/kontrol` **YEŞİL**: 571 çekirdek testi / 4780 doğrulama.

## Açık

Ç114.
