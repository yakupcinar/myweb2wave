# E1b — E-postalar sayfası: panel (2026-11-12)

E1'in ikinci yarısı. E1a çekirdeği ve uçları verdi; bu blok panelde düzenliyor. E1 tamam.

## Ne yapıldı

- **Sayfa `/panel/epostalar`** ([`Epostalar.tsx`](../../core/panel/src/uygulama/sayfalar/Epostalar.tsx)): menüde Ayarlar
  grubunda, Yasal metinlerin altında.
- **Şablon başına kart:** ad ve açıklama, dil seçimi (Türkçe / İngilizce), "Varsayılan metin" ya da "Düzenlendi" rozeti,
  konu alanı, metin alanı, kullanılabilir ve zorunlu yer tutucular.
- **Kaydet:** yalnız konu ya da metin değişince açık; konu + metin o şablon/dil adresine gider.
- **Varsayılana dön:** yalnız kayıtlı şablonda görünür; `DELETE` ile kaydı siler, kart varsayılan metne döner.
- **Hata:** sunucunun 422 mesajı (ör. "metinde olmalı: {{kod}}") kartın içinde.
- **Sunucu rotası** `PanelController` (tuzak §51) ve ekran döngüsünde `epostalar` sayfası.

## Neden böyle

- **Yasal metinler sayfasının düzeni.** Aynı biçim (belge/şablon × dil); ikinci bir kart düzeni öğrenmek gerekmesin.
- **Yer tutucular düz yazı, tıklayınca eklenmiyor.** Yedi şablonda iki-dört yer tutucu var; imleç konumuna ekleme ayrı
  bir iş ve istenmedi.
- **"Kaydedildi." yazısı yok.** Kayıttan sonra yanıt kartı yeniden kurar (anahtar `guncellendi`); geri bildirim
  "Düzenlendi" rozeti ve kapanan "Kaydet".

## Kırma denemeleri — 11/11 kırmızı

| Deneme | Sonuç |
|---|---|
| B1 kayıt hep tr adresine | 1 |
| B2 gövdede eski konu | 1 |
| B3 "Varsayılana dön" hep görünür | 1 |
| B4 DELETE yerine PUT | 1 |
| B5 form anahtarında `guncellendi` yok | 1 |
| B6 "Kaydet" değişmeden de açık | 1 (2 test) |
| B7 hata gösterilmez | 1 |
| B8 menü bağlantısı yanlış adres | 1 |
| B9 silince önbellek güncellenmez | 1 |
| B10 zorunlu yerine izinli liste | 1 |
| B11 sunucu rotası yok (PHPUnit) | 1 |

Betikler: `$TMPDIR/…/scratchpad/kirp.sh` (Vitest), `kir.sh` (PHPUnit).

## Ölçüm

- **Panel:** `Epostalar.test.tsx` 4 test; **çekirdek:** `testPanelSayfasiAdresiAcilir` (girişsiz → giriş, girişli → 200).
- **Tam kontrol:** YEŞİL (762 çekirdek testi), yerel, 2026-11-12.
- **Ekran döngüsü** (`bin/panel-ekran epostalar`): ilk koşuda sayfa "E-posta şablonları yüklenemedi" gösterdi. Testler
  yeşildi, ama yerel geliştirme DB'sinde E1a'nın **ve R1a'nın** göçü koşulmamıştı (tuzak §60). Göç koşulunca yedi
  kart açık/koyu, 1440/390'da çizildi; yatay kayma yok.
  - Telefonda `tasanlar: INPUT`: uzun konu ("Aboneliğiniz hazır — uygulamaya bağlanın") alanın içinde kayıyor; olağan.
  - Telefonda `kucukHedefler: Türkçe, İngilizce`: Yasal metinlerde de aynı (ortak `bolmeli`) — Ç141.
