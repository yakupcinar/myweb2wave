# E1a — E-posta şablonları: çekirdek (2026-11-12)

E1'in ilk yarısı. Müşteriye giden yedi e-postanın konusu ve metni uygulama × şablon × dil olarak kaydedilir; panel
ekranı E1b.

## Ne yapıldı

- **Tablo `eposta_sablonlari`** (`app, sablon, dil` birincil anahtar). Şablon ve dil listesi, konu (1–200) ve metin
  (1–5000) uzunluğu DB kısıtında da var.
- **`EpostaSablonlari`:** `doldur` (gönderim), `liste` / `kaydet` / `sil` (panel), `oturumDili` (ödeme oturumunun
  hunisinin dili, M9d2).
  - Şablonlar: doğrulama kodu, giriş bağlantısı, portal bağlantısı, aktivasyon 1–2, kurtarma 1–2.
  - Kayıt yoksa koddaki varsayılan gider (tr bugünkü metinler, en karşılığı); e-posta her durumda gider.
- **Gönderenler şablondan:** `DogrulamaKodu`, `GirisBaglantisi`, `PortalBaglantisi`, `AktivasyonDizisi`, `KurtarmaDizisi`.
- **Dil nereden:**
  - Kod e-postası: uygulamanın isteğindeki `dil`.
  - Portal bağlantısı: abonelik sayfasının dili (huni `dil` gönderir).
  - Giriş, aktivasyon ve kurtarma: ödeme oturumunun hunisi.
  - Bilinmeyen dil tr.
- **Panel uçları:** `GET /panel/api/uygulamalar/{app}/epostalar`, `PUT …/{sablon}/{dil}`, `DELETE …/{sablon}/{dil}`
  (varsayılana dön). Sözleşme ve tipler güncel.

## Neden böyle

- **Bilinmeyen yer tutucu reddedilir.** `{{baglnti}}` gibi bir yazım hatası müşteriye ham giderdi.
- **Zorunlu yer tutucu metinde aranır, konuda değil.** Kodsuz doğrulama e-postası ya da bağlantısız giriş e-postası işe
  yaramaz. Konuya konan bağlantı da tıklanmaz.
- **Düz metin, HTML yok.** Gönderim `->text()`; HTML şablonu ayrı bir iş (sanitize, önizleme).
- **Kayıt yoksa varsayılan, satır yazılmaz.** Kod metni değişince kaydetmemiş uygulamalar da yenisini alır; "Varsayılana
  dön" yalnız satırı siler.
- **`{{uygulama}}` ayarlardaki görünen ad.** Her şablonda izinli; değeri gönderende değil `doldur`'da.

## Kırma denemeleri — 19/19 kırmızı (K7 ve K13 ilk koşuda tutmadı)

| Deneme | Sonuç |
|---|---|
| K1 bilinmeyen yer tutucu kabul | 1 |
| K2 zorunlu yer tutucu denetimi yok | 1 |
| K3 zorunlu konuda da aranır | 1 |
| K4 kayıtlı şablon yok sayılır | 1 (10 test) |
| K5 `doldur` hep tr | 1 |
| K6 `oturumDili` hep tr | 1 |
| K7 CRLF birleştirilmez | **ilk koşuda YEŞİL** → test düzeltildi → 1 |
| K8 fazla alan (`html`) kabul | 1 |
| K9 / K10 silme / kaydetme CSRF yok | 1 / 1 |
| K11 kod e-postası dili yok sayılır | 1 |
| K12 portal dili yok sayılır | 1 |
| K13 giriş e-postası hep en | **ilk koşuda YEŞİL** → test eklendi → 1 |
| K13b giriş e-postası hep tr | 1 (yeni test) |
| K14 aktivasyon 2 yerine 1 | 1 |
| K15 aktivasyon dili sabit | 1 |
| K16 kurtarma dili sabit | 1 |
| K17 `{{uygulama}}` doldurulmaz | 1 |
| K18 huni portal isteğinde dil hep tr | 1 (Vitest) |

- **K7 notu:** testteki `\r\n` metnin sonundaydı, `trim` onu zaten siliyordu; test birleştirmeyi ölçmüyordu. `\r\n`
  metnin ortasına taşındı.
- **K13 notu:** giriş e-postasının konusunu hiçbir test okumuyordu. Eklendi: hunisi bilinmeyen oturum tr, İngilizce
  huniden gelen ödeme `Your sign-in link`.
- **K4 notu:** bozma kayıt yokken DB satırı yerine `false` okutuyor; testlerin bir kısmı hatayla düştü. Kayıtlı
  şablonun gittiğini ölçen test de düştü (`testVarsayilanKayitliDilVeVarsayilanaDonus`).

Betik: `$TMPDIR/…/scratchpad/kir.sh` (bozar, filtreli PHPUnit koşar, yedekten geri alır).

## Ölçüm

- **Çekirdek:** `EpostaSablonlariTest` 3 test; `GirisBaglantisiTest` +1 test (İngilizce giriş e-postası) ve konu
  denetimi; `KurtarmaDizisiTest` +1 (İngilizce huni + kayıtlı şablon); `PortalBaglantisiTest` +1 (istek dili).
- **Huni:** `abonelik.test.ts` portal isteğinde `dil`.
- **Tam kontrol:** YEŞİL (761 çekirdek testi), yerel, 2026-11-12.
- **Davranış değişikliği:** aktivasyon e-postası mağaza adresi yoksa "App Store" yazıyor (önce yalnız "kurun") — Ç139.
- **KeepChat:** kod isteğinde `dil` göndermiyor, kod e-postası tr gider — Ç140.
