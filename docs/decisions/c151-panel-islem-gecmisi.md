# Ç151 — Panel işlem geçmişi: abonelik işlemlerinin denetim izi (2026-11-17)

Kullanıcı kararı: "2. seçenek uygulansın" — panel işlemleri için ayrı denetim tablosu ve panelde görünür geçmiş
(monolog kurmak yerine). Neden gerekti: Ç150 canlı ölçümünde e-posta düzeltme denemeleri hiç iz bırakmadı
(monolog yok, yerleşik günlükçü `info`/`warning` basmıyor).

## Ne yapıldı

- Göç `Version20261117120000`: `panel_islemleri` (zaman, kullanıcı, abonelik, işlem, başarılı, ayrıntı). İşlem
  listesi `CHECK` ile sabit. `BEFORE UPDATE OR DELETE` tetikleyicisi reddeder: iz değiştirilemez, silinemez.
  E-posta yok; yalnız abonelik kimliği (KVKK silme talebi bu tabloyu gerektirmez).
- [`PanelAbonelikController::yanit`](../../core/src/Controller/PanelAbonelikController.php): altı işlemin (iptal,
  iptal_geri_al, duraklat, surdur, iade, eposta_duzelt) **her denemesi** — başarılı ya da reddedilen — yazılır;
  ayrıntı yalnız reddedilende (sunucunun hata metni). Jetonu geçersiz istek yazılmaz (işlem yapılmadı).
- `GET /panel/api/abonelikler/{abonelik}/gecmis`: son 50, yeniden eskiye ([`PanelIslemleri`](../../core/src/Panel/PanelIslemleri.php)).
- Panel › Aboneler: her satırda "Geçmiş" (`aria-expanded`); altta "2026-11-17 11:08 UTC · yakup · E-posta
  düzeltme · reddedildi: kartın son 4 hanesi eşleşmiyor". İşlemden sonra (reddedilse de) geçmiş yeniden istenir.

## Neden böyle

- **Panelde, günlükte değil:** iade gibi para işlemlerinin izi destekte görünür olmalı; sunucu günlüğüne bakmak
  ssh ister. Reddedilen: monolog (iz görünür olur ama yalnız sunucuda).
- **Olay deposunda değil:** `olaylar` ziyarete bağlı ve giden webhook'larla dışarı akar.
- **Reddedilen deneme de yazılır:** asıl risk (Ç150) tahmin denemesi; yalnız başarılıyı yazmak onu göstermez.
- **Kapsam şimdilik abonelik işlemleri:** para ve erişim değiştirenler. Diğer panel yazmaları → Ç152.

## Kırma denemeleri — 8/8 kırmızı

| # | Bozulan | Sonuç |
|---|---|---|
| G1 | hiç yazılmıyor | 2 düştü |
| G2 | yalnız başarılı yazılıyor | 1 |
| G3 | kullanıcı yazılmıyor | 1 |
| G4 | eskiden yeniye | 1 |
| G5 | başarılıda da ayrıntı | 1 |
| G6 | tetikleyici yok (test DB'sinde geçici `DROP TRIGGER`, sonra yeniden kuruldu) | 1 |
| P1 | işlemden sonra geçmiş yenilenmiyor | 1 |
| P2 | `aria-expanded` yok | 1 |

Betik: `$TMPDIR/…/scratchpad/kir-gecmis.py`.

## Ölçüm

Çekirdek `PanelAbonelikTest` 17 (3 yeni), panel `Kullanicilar.test` 17 (2 yeni; iki eski testin düğme listesine
"Geçmiş"). Tam kontrol ve canlı: aşağıda.
- **Tam kontrol:** ilk koşu biçim + PHPStan (yeni testte tip; `cs-fixer` `return` önündeki `/** @var */`'ı `/* */` yapınca
  PHPStan kör kaldı → adlandırılmış değişkene etiket) ile kırmızı; ikinci koşu ilgisiz iki panel testinin yük altında
  zaman aşımıyla kırmızı (Ç153; panel tek başına 206/206); üçüncü koşu `TEST_TOKEN=gecmis` **YEŞİL**, 794 çekirdek testi.
