# Blok: E2.2 — huni başına tema (A8) — 2026-10-20

## Ne yapıldı
Huni tanımına isteğe bağlı `tema`: `anaRenk`, `arkaPlan`, `metinRengi`
(`#rrggbb`, küçük harf), `yaziTipi` (`sistem` · `serif` · `yuvarlak` ·
`mono`), `kose` (0–32 px). Çekirdek (`App\Huni\Tema`) biçimi ve
**okunurluğu** yayından önce doğrular: metin/arka plan kontrastı en az
4,5:1 (WCAG AA). Huni sayfası temayı CSS değişkenlerine `setProperty` ile
uygular; düğme yazısı ana rengin üstünde siyah/beyazdan kontrastı yüksek
olanı. Tema yoksa varsayılan görünüm (çekirdek ve huni aynı değerler).
Panel düzenleyicisinde "Görünüm" bölümü: "bu huniye özel" kutusu, üç renk
seçici, yazı tipi, köşe ve canlı kontrast göstergesi (bilgi; karar
çekirdekte).

## Neden böyle
- **A8:** görünüm huni (etiket) başına.
- **Yalnız cihaz yazı tipleri:** dışarıdan font (Google Fonts vb.)
  ziyaretçinin IP'sini üçüncü tarafa yollar → KVKK/çerez onayı (Ç12). Kendi
  sunucumuzdan font E2.3 (görsel/dosya altyapısıyla) sonrası düşünülebilir.
- **Sınırlı değer kümesi, serbest CSS değil:** renk kalıbı `^#[0-9a-f]{6}$`
  — `#000000;}body{…}` gibi kaçışlar reddedilir (test var, kırma T3).
- **Okunurluk bir kural, bir öneri değil:** okunmayan huni satmaz;
  panelde canlı gösterge, yayında red.

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| T1 | kontrast denetimi yok | 🔴 `testOkunurlukSiniri` |
| T2 | renk kalıbı büyük harf kabul ediyor | 🔴 |
| T3 | renk kalıbının sonu açık (`$` yok) | 🔴 `geçerli renkten sonra css` |
| T4 | köşe aralığı yok | 🔴 2 test |
| T5 | tanım temayı doğrulamıyor | 🔴 |
| T6 | parlaklıkta gama düzeltmesi yok | 🔴 bilinen değerler (21:1, #767676 4,54, #777777 4,48) |

## Ölçüm
- `bin/kontrol` YEŞİL: 141 test (öncesi 126). Kontrast bilinen WCAG
  değerleriyle ölçüldü (kodla değil — tuzak §3).
- **Tarayıcı (yerel):** fitness hunisinde "özel tema" açıldı; arka plan
  `#0b1220` + metin `#1f2937` → panel "1.28:1 — yayınlanamaz", Kaydet →
  çekirdek aynı oranla reddetti. Metin `#f8fafc`, ana `#16a34a`, yuvarlak,
  20 px → "17.89:1 ✓", kaydedildi, `fitness v2 yayında`. Huni: koyu arka
  plan, yeşil düğme, 20 px köşe; sayfadan okunan `--ana-yazi: #000000`
  (yeşilde siyah daha okunur), yazı tipi `ui-rounded…`; `genel` hunisinin
  teması yok (varsayılan).
- **Bulgu:** düzenleyicide onay kutusu genel `input { width: 100% }` yüzünden
  satırın ortasına uzuyordu → CSS düzeltildi (tarayıcıda görüldü).
- Renk seçici (yerel tarayıcı penceresi) otomasyonla tıklanamıyor; değerler
  sayfadaki girişe betikle yazılıp `input` olayı tetiklendi.

## Açık
E2.3 görseller · E2.4 sürükle-bırak · kendi sunucumuzdan yazı tipi (sonra).
