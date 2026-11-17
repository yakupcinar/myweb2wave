# M5d — Meta reklam harcaması → kampanya ROAS (2026-11-09)

Karar `pre-setup.md` A18 (kullanıcı: Marketing API, `utm_campaign` = kampanya adı, değer aktarımı sonra) · önceki `m5c-raporlar.md`.

## Ne yapıldı

- **Harcama çekme:** `app:reklam-harcamalari:cek [--gun 7]` (`App\Atif\MetaHarcamalari`). Ads Insights'tan
  `GET /{sürüm}/act_…/insights` isteği atılıyor: `level=campaign`, `time_increment=1`, `time_range`,
  `fields=campaign_id,campaign_name,spend,account_currency`, 100 satırlık sayfalar. Sonuç
  `reklam_harcamalari` tablosuna (kampanya × gün, küçük birim, hesabın para birimi) yazılıyor
  (göç `Version20261109180000`). Son N gün her çekişte üzerine yazılıyor, çünkü Meta yakın günleri düzeltiyor.
  Hata olursa hiçbir şey yazılmıyor ve hata metni anahtarı içermiyor.
- **Ayarlar:** `META_AD_ACCOUNT_ID` (`act_…`) + `META_INSIGHTS_ACCESS_TOKEN` (ads_read), sürüm
  `META_GRAPH_API_VERSION`. Biri boşsa özellik kapalı ve istek atılmıyor.
- **Panel "Meta kampanyaları":** kampanya başına aralıktaki harcama, aynı adlı `utm_campaign` ile **Meta'dan**
  gelen ziyaretler (fbclid ya da kaynak meta/facebook/fb/instagram/ig), bunların "ödedi" sayısı ve
  fatura geliri. ROAS = hesabın para birimindeki gelir / harcama. Başka para birimi listede görünüyor, çevrilmiyor.

## Ölçüm (önce)

Meta'nın başvuru sayfaları (`marketing-api/insights`, `reference/ad-account/insights`) okundu:
`time_increment=1` günlük satır veriyor; `time_range {since, until}` ve until günü dahil. `spend` ondalık metin,
`campaign_name` / `account_currency` / `date_start` alanları var. Sayfalama imleçli, en çok 37 ay geriye. Meta hesabı ve anahtarı yok,
yanıt biçimi buradan alındı (Ç121).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Ayrı Insights anahtarı | CAPI anahtarını kullanmak | En az yetki: CAPI yalnız olay yazar, bu okur (ads_read) |
| 2 | Son 7 gün her gün yeniden yazılır | yalnız dün | Meta harcamayı birkaç gün düzeltir |
| 3 | Sonraki sayfa `cursors.after` ile | `paging.next` adresine gitmek | O adres erişim anahtarını taşır, istemci hatasında loga düşebilir |
| 4 | Tutar metinden çevrilir (`ParaBirimi::metindenKucuk`) | `(float) × 100` | PHP 8.4: `1.005 × 100` → 100 (§56) |
| 5 | Eşleşme ad + Meta kaynağı | yalnız ad | Aynı adlı Google kampanyasının geliri Meta harcamasına yazılmasın |
| 6 | Gelir: aralıkta gelen ziyaretlerin bugüne kadarki faturaları | yalnız aralıktaki faturalar | Harcama o ziyaretleri getirdi; geliri sonradan gelse de onundur |
| 7 | Ad değişirse son ad | kampanya kimliğiyle eşlemek | Kullanıcı kararı (ad); eski adla gelenler eşleşmez (Ç121) |
| 8 | Değer aktarımı yok | tahmini LTV | Kullanıcı: kohort verisi birikmeden yanlış olur |

## Test

- `MetaHarcamalariTest` (5): istek parametreleri ve sayfalama (ikinci sayfa imleçle; `next` olmayan sayfada duruyor),
  biçim dışı satır atlanıyor, aynı gün yeniden yazılıyor (ad da), HTTP 500'de yarım çekiş yazılmıyor, ağ
  hatası metninde anahtar yok, ayar eksik / hesap biçimi yanlışsa istek atılmıyor, tutar biçimi servis
  üzerinden (1.005 → 101, 0.29 → 29, JPY 1234.5 → 1235; 1e3 / -1 / 1,5 atlanıyor), konsol.
- `PanelTest::testMetaKampanyaRoas`: elle hesaplanan ROAS 0,67. Tam 30 ve 40 gün önceki harcama aralık dışı,
  aynı adlı Google kampanyası sayılmıyor, TRY geliri ROAS'a girmiyor, ad değişince son ad kullanılıyor.
- Panel `Rapor.test.tsx`: "Meta kampanyaları" hücreleri, ROAS null → "—".

## Kırma (13/13 🔴)

| # | Bozulan | Sonuç |
|---|---|---|
| D1 | sayfalama yok | 🔴 2 |
| D2 | imleç gönderilmez | 🔴 |
| D3 | until dışlayıcı sanılır | 🔴 |
| D4 | `(float) × 100` çevirimi | ilk koşu **🟢** — test 1.005'i servisten geçirmiyordu. Ben bunu "PHP round ön yuvarlar, çevirici gereksiz" diye yorumlayıp çeviriciyi kaldırdım; servis düzeyi test 100 ölçtü (PHP 8.4.26), yorum yanlıştı, çevirici geri geldi (§56). Sonra ikinci koşu SQL kısıtı hatasıyla kırmızıydı (`-1` biçim denetimini de kaldırıyordu, sahte); yama yalnız çevirimi değiştirecek şekilde düzeltildi → 🔴 |
| D5 | yarım yukarı yuvarlama yok | 🔴 |
| D6 | aynı gün yazılmaz | 🔴 |
| D7 | hesap biçimi denetlenmez | 🔴 |
| D8 | Meta kaynağı eşleşmesi yok | 🔴 |
| D9 | harcama aralığı bir gün fazla | 🔴 |
| D10 | ROAS tüm para birimlerinden | 🔴 |
| D11 | kampanyanın ilk adı | 🔴 |
| D12 | hata öncesi sayfalar yazılır | 🔴 |
| D13 | panel ROAS null → "0,00×" | 🔴 |

## Gerçek sistem (2026-11-09, dev)

Göç uygulandı. `app:reklam-harcamalari:cek` ayarsız çalıştırıldı: çıkış 1, "Meta harcaması kapalı …". Rapor ucu girişsiz 302
(giriş). **Gerçek Meta Insights isteği atılmadı**: hesap ve anahtar yok (Ç121). Günlük cron tanımlı değil (üretim yok).
