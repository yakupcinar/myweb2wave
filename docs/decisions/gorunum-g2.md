# G2 — huniyle birlikte sunulan yazı tipleri (2026-10-30)

Kullanıcı: önce "sunucu mu soruyorsun?" — hayır, fontların **nereden** geldiği: (1) cihaz fontları (bugün), (2) Google Fonts bağlantısı, (3) font dosyalarını huninin kendi dosyalarıyla birlikte sunmak. Rakip kontrolü: **FunnelFox** müşterinin yüklediği fontu kendi CDN'inden sunuyor (= 3), Google Fonts'tan söz etmiyor; **web2wave** iki yol sunuyor — özel CSS'le Google Fonts `@import` (tasarım kuralında "şiddetle önerilir") ya da yüklenen dosya + `@font-face` (= 3). Kullanıcı kararı: **3**.

## Karar
- **Kaynak:** Fontsource `@fontsource-variable/{nunito,lora,manrope,outfit}` 5.3.0 (2026-07-19, iki hafta kuralı ✓), Google Fonts'un sürümlü kopyası; hepsi **OFL-1.1**, lisans dosyaları `huni-bilesenleri/fontlar/OFL-*.txt`. Paket CSS'i alınmadı; `@font-face` tanımları `fontlar/fontlar.css`'te bizim (aralıklar paketten).
- **Yalnız gerekeni:** her aile değişken (tüm kalınlıklar tek dosya), latin + latin-ext (Türkçe ğ ş ı İ), `unicode-range` ile — tarayıcı yalnız kullanılan aileyi ve sayfadaki karakterlerin alt kümesini indirir. `font-display: swap` (font gelene kadar yedek yığın, görünmez metin yok).
- **Boyut (iki dosya, tüm kalınlıklar):** Nunito 72 KB · Lora 56 KB · Manrope 39 KB · Outfit 46 KB; test bütçesi aile başına ≤ 80 KB.
- **Yazı tipleri:** `serif` → Lora (+ Georgia yedeği), `yuvarlak` → Nunito (bugün yalnız Apple'da `ui-rounded` vardı; artık her cihazda aynı), yeni `modern` (Manrope), `geometrik` (Outfit); `sistem` ve `mono` cihazda kalır. **Görünür değişiklik:** mevcut `serif`/`yuvarlak` hunileri artık her cihazda Lora/Nunito ile çizilir.
- **Teslim:** `huni.css` başında `@import "./fontlar/fontlar.css"`; huni ve panel derlemesi dosyaları içerik özetli adlarla paketler (§30). Ziyaretçi üçüncü tarafa bağlanmaz. CSS kapsam bekçisi `@import` satırını seçici saymaz (gerekçeli).
- **AI:** yazı tipi listesi yönergeye `Tema::YAZI_TIPLERI`'nden kendiliğinden girer; "iletişim / sosyal" örneği `modern`.
- Reddedilen: Google Fonts bağlantısı (KVKK, Ç12) · npm bağımlılığı olarak kurmak (huni-bilesenleri bağımlılıksız; dosyalar + lisans depoda, sürüm sabit) · müşterinin kendi fontunu yüklemesi (FunnelFox gibi — lisans müşteride; ileride ayrı iş) · tam karakter kümesi (Kiril/Yunan gereksiz ağırlık).

## Ölçüm
- Testler: `tema.test.ts` "fontlar (G2)" — barındırılan aileler tam olarak serif/yuvarlak/modern/geometrik (liste boşken font başına testin sessizce koşmaması için, §8), her aile için 2 `@font-face`, dosyalar var, `font-display: swap`, ≤ 80 KB; **KVKK bekçisi:** `huni.css` ve `fontlar.css`'te hiçbir `http(s)://` yok, fontlar `@import` ile · ortak örnek dosyası (`tema-ornekleri.json`) yazı tipi listesi → `TemaTest` + `tema.test.ts` · AI örneğinde yeni font.
- Kırma 8/8 kırmızı: K1 `@import` yok · K2 yuvarlak Nunito'suz · K3 dosya yok · K4 Google adresi · K5 PHP listesi eksik · K6 `swap` yok · K7 AI örneği eski font · **K8 bütçe — ilk deneme tutmadı: yerine konan dosyayla toplam 71,7 KB, sınırın altındaydı (denemenin kusuru); K8b 90 KB'lık dosyayla kırmızı (127 788 > 81 920).** Prettier `fontlar.css`'te tırnağı değiştirince test tek tırnağa bağlıydı → tırnaktan bağımsız yapıldı, K3 yeniden koşuldu: kırmızı.
- Derleme: huni ve panel çıktısında 8 `.woff2` içerik özetli adla; çıktılarda dış font adresi yok.
- Gerçek sistem (ekran kabı, KeepChat taslağı yedeklendi → **aynen geri yüklendi**): beş yazı tipi çekildi — Lora, Nunito, Manrope, Outfit gerçekten çiziliyor (sistemden ~%2,7 piksel farklı; G1'de `yuvarlak` kapta sistemle aynıydı, fark %0); Türkçe karakterler doğru.
- CI: koşu 40966250257, SHA a5fde31, `KONTROL: YEŞİL`, 289 test (imaj ve derleme sıfırdan).
- `bin/kontrol` yeşil (289 çekirdek testi).
- **Ölçülmedi:** gerçek iPhone/Android'de ilk yükleme süresi ve font değişimi anı (FOUT); yavaş ağda.
