# R2 — CSV dışa aktarım (2026-11-12)

Karar `pre-setup.md` A29 (kullanıcı: "önerin uygun" — e-posta panelle aynı maskeli) · web2wave farkı
([web2wave-platform-yapisi.md](web2wave-platform-yapisi.md) "Exports": ayrı sayfa değil, ilgili tabloda "CSV indir").

## Ne yapıldı

- **Uçlar** (panel oturumu; GET, jeton gerekmez — okuma):
  - `GET /panel/api/kullanicilar.csv` — Aboneler listesinin arama, durum ve sırasıyla, **sayfasız**.
  - `GET /panel/api/rapor/ziyaretler.csv` — raporun dönemi (`gun`) ve süzgeciyle (R1a), sayfasız.
  - `GET /panel/api/rapor/cevaplar.csv` — ziyaret başına her cevap: seçenek (çoklu seçimde her biri ayrı satır) ve
    serbest metin, `tür` sütunuyla (`Rapor::cevapSatirlari`).
- **Ziyaret kimliği** ziyaretler ve cevaplar dosyasında: iki dosya bununla birleşir. Rapor'un ziyaret listesi (JSON)
  da artık `ziyaret` alanını taşır (sözleşmede).
- **Biçim** (`App\Panel\Csv`): UTF-8 BOM + virgül, Türkçe başlıklar, `evet` / `hayır`, ek olarak indirilir
  (`aboneler-2026-11-12.csv`), `no-store`.
- **Formül koruması:** `= + - @`, sekme ya da satır başıyla başlayan hücre `'` ile başlar (kampanya adı, serbest
  yanıt ve seçenek ziyaretçiden gelir; tablo programında formül olarak çalışmasın).
- **Panel:** Aboneler sayfa başında, Rapor › Ziyaretler ve Rapor › Anket bölümlerinin sağında "CSV indir".

## Neden böyle

- **Virgül, noktalı virgül değil.** RFC 4180; Google Sheets, Numbers ve içe aktarma araçları doğrudan açar. Türkçe
  bölge ayarlı Excel çift tıkta tek sütun gösterebilir (Veri › Metinden içe aktar ile açılır) — Ç144.
- **"-5 kilo" da `'` alır.** Eksiyle başlayan metin de formül sayılabilir; sayısal olmayan hücrelerde ayrım yapmak
  yerine hepsine aynı kural (OWASP önerisi). Bizim sayısal sütunlarımız (tutar) eksiyle başlamaz.
- **Sayfasız, ama bellek içinde.** Bugünkü hacimde (yüzlerce satır) yeterli; on binlerce satırda akışla yazmak
  gerekir — Ç144.

## Kırma denemeleri — 18/18 kırmızı (C12 ilk koşuda tutmadı)

| Deneme | Sonuç |
|---|---|
| C1 BOM yok | 1 (2 test) |
| C2 formül koruması yok | 1 (2 test) |
| C3 yalnız `=` korunur | 1 |
| C4 aboneler sayfa boyutunda kesilir | 1 |
| C5 aboneler durum süzgeci yok | 1 |
| C6 ziyaretler süzgeçsiz | 1 |
| C7 serbest metin cevabı yok | 1 |
| C8 cevaplar süzgeçsiz | 1 |
| C9 ek değil, satır içi | 1 |
| C11 evet/hayır hep evet | 1 |
| C12 ziyaretler sayfa boyutunda kesilir | **ilk koşuda YEŞİL** (testte 2 ziyaret vardı) → +26 ziyaret, süzgeçsiz 28 satır → 1 |
| C13 `no-store` yok | 1 |
| Q1–Q3 Aboneler bağlantısında durum yok / sayfa var / `download` yok | 1 / 1 / 1 |
| Q4–Q6 Rapor bağlantısında süzgeç yok / gün yok / Anket'te ziyaretler dosyası | 1 / 1 / 1 |

Maske (e-posta) ayrıca bozulmadı: dosya listeyle aynı `Kullanicilar::liste`'den gelir; maske orada ölçülü (P5).

## Ölçüm

- **Çekirdek:** `PanelCsvTest` 4 test; dosya bağımsız ayrıştırıcıyla (`fgetcsv`) okunur; satır sonu ve tırnak
  hücrede kalır. `PanelTest` ziyaret satırında `ziyaret` kimliği.
- **Panel:** `Kullanicilar.test.tsx` +1, `Rapor.test.tsx` +1.
- **Tam kontrol:** YEŞİL (771 çekirdek testi, `UYARI` yok), yerel, 2026-11-12. İlk koşu kırmızıydı: php-cs-fixer
  (docblock hizası), PHPStan (`mixed` → string), rehber bağlantısı kaydı (§58) — düzeltildi.
- **Gerçek veri** (dev DB, denetleyiciyle aynı çağrılar): aboneler dosyası 7 satır + başlık (panel "7 abonelik"),
  Python `csv` (utf-8-sig) açtı, maskesiz e-posta yok.
- **Ekran döngüsü** (`kullanicilar`, `rapor-ziyaretler`, `rapor-anket`): bağlantılar yerinde; taşma ve yatay kayma yok.
