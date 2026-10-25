# L1 — Geliştirme DB'si göç uyarısı (Ç60) + `reference.php` (Ç62)

2026-10-26 · karar `pre-setup.md` A13.

## Ölçüm (önce)
- `doctrine:migrations:status` (dev): 13/14 uygulanmış,
  `Version20261023140000` (H6b IP/UA sütunları) eksik;
  `app.odeme_oturumlari.ip_adresi` sütunu yoktu.
- `bin/kontrol` yalnız test DB'sine göç uygular → testler yeşilken yerel
  tarayıcıda ödeme yolu bu sütuna INSERT yazıp düşerdi. H6a'da da aynısı
  (`huni-izin-h6a.md` "Tarayıcıda bulunan iki kusur").
- `core/config/reference.php`: Symfony'nin ürettiği IDE şekil dosyası;
  dev'de `when@dev` + `http_client` varsayılanı eklenip test ortamında geri
  dönüyordu → çalışma ağacı her koşuda kirli. Hiçbir kod okumuyor
  (yapılandırma YAML; yalnız phpstan ve cs-fixer yollarında geçiyor).

## Karar
- `bin/kontrol`'de `core-kur`'dan sonra `doctrine:migrations:up-to-date`
  (dev) — **uyarı**, kapı değil. **Reddedilen:** kırmızı kapı (dev DB
  yerel durumdur; kapı yerel duruma dayanmamalı — `core-kur` dersi) ·
  CI'da da koşmak (CI'da dev DB'ye göç yok, hep uyarırdı → `$CI` boşsa).
- `reference.php` `core/.gitignore`'a; depodan çıkarıldı. **Reddedilen:**
  dev hâlini commit etmek (test ortamı yine değiştirir).

## Doğrulama
| Durum | Sonuç |
|---|---|
| dev DB 1 göç geride | `UYARI dev-goc` + "Out-of-date! 1 migration", `KIRMIZI=0` |
| `CI=true` | satır yok |
| göç uygulandıktan sonra | satır yok |
| ilk deneme betiği yanlış dizinden | uyarı çıktı ama log "no configuration file" — **sahte**, betik düzeltildi (tuzak §8) |
| `reference.php` silinmişken phpstan | `[OK] No errors`, EXIT=0 |
| gerçek istek: izinli ziyaret → `POST /checkout/sessions` | **201**, `odeme_oturumlari`'na UA + IP yazıldı |

`bin/kontrol` (reference.php yokken başlatıldı): **YEŞİL**, EXIT=0, 205 test / 1.294 doğrulama, `dev-goc` uyarısı yok (göç uygulanmış).

CI: `864a69f` yeşil (temiz klon, `reference.php` yok).
