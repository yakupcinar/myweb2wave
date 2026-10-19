# Blok: İskelet — 2026-10-19

## Ne yapıldı
Astro (huni) + Symfony 7.4 (çekirdek) + Postgres 18 iskeleti, hepsi
Docker'da; ilk davranış `GET /health`; tam doğrulama `bin/kontrol`.
Ekranda: `curl localhost:8081/health` → `200 {"status":"ok","db":"ok","version":"…"}`,
DB durunca `503 {"status":"degraded","db":"down",…}`.

## Neden böyle — kararlar `pre-setup.md` §B3
Sürümler, digest sabitleme, araçların konteynerde koşması.

## Yolda ölçülenler
- Tarif `DATABASE_URL`'e `serverVersion=16` yazdı (bizde 18) → düzeltildi.
- `composer require` ağ hatasıyla yarıda kaldı; `composer.json`'da `"*"`
  kısıtları **kaldı** (geri alınmadı) → silinip yeniden kuruldu, `^` kısıtlar.
- PHPStan max iskeletin kendi iki dosyasında hata buldu (`public/index.php`,
  `tests/bootstrap.php`) → dışlanmadı, düzeltildi.
- `composer.json` PHP `>=8.2` / çalışma 8.4 → `>=8.4`.
- `undici@8.12.2` Node ≥22.19 istiyor, makinede 22.15 → huni araçları Node
  24.21.0 konteynerine alındı (`EBADENGINE` 0).
- `@astrojs/check` TS 7'yi desteklemiyor (peer `^5 || ^6`) → TS 6.0.3.
- Tuzak §19 (`<server>` vs `$_ENV`) ve §20 (renk kodu + `grep '^'`) bu blokta doğdu.

## Kırma denemeleri

| # | Bozulan karar | Sonuç |
|---|---|---|
| K1 | DB gerçekten sorgulanmıyor (`SELECT 1` silindi) | 🔴 1 test (`…yokken503Doner`) |
| K2 | sürüm sabit `'dev'` | 🔴 2 test |
| K3 | DB yokken de 200 | 🔴 1 test |
| K4 | `strlen(123)` tip hatası | 🔴 `core-stan` EXIT=1 (+ `core-test`: strict_types çalışma anında da patlatıyor) |
| K5 | PHP biçimi bozuk | 🔴 `core-bicim` EXIT=8 |
| K6 | Astro'da `const x: number = "…"` | 🔴 `funnel-tip` EXIT=1 |
| K7 | Astro biçimi bozuk | 🔴 `funnel-bicim` EXIT=1 |

Her denemede: yama uygulandı mı (grep) · geri alma birebir mi (dosya
karşılaştırması) — 7/7 evet. K4–K7 `bin/kontrol`'ün **toplam** çıkışını da
1 yaptı (betik kırmızıyı yutmuyor).

## Gerçek istek
FrankenPHP üzerinden (`Server: FrankenPHP Caddy` — cevap uygulamadan, tuzak
§11): DB ayakta 200 · `docker compose stop db` → 503 · geri → 200;
`version` = verilen `APP_VERSION` (`yerel-deneme`).

## Ölçüm
`bin/kontrol` EXIT=0, ~45 sn (ilk `npm ci` 23 sn) · core-test
`OK (2 tests, 4 assertions)`, 0 atlanan · macOS, Docker Desktop 29.7.2.
