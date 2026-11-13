# M9a — uygulama kimliği panelde (2026-11-10)

Karar `pre-setup.md` A23 · önceki `m8b-kurtarma.md`.

## Ne yapıldı

- Panel **Ayarlar** (`/panel/ayarlar`): uygulama başına görünen ad, URL şeması, App Store adresi, destek e-postası.
  `GET|PUT /panel/api/uygulamalar/{app}/ayarlar` (sözleşme `UygulamaAyarlari`, CSRF).
- `uygulama_ayarlari` tablosu (göç `Version20261110200000`). KeepChat'in şeması yaml'dan taşındı. `uygulama_semalari`
  parametresi ve `MAGAZA_ADRESI_KEEPCHAT` kaldırıldı; `.env.local`'da tanımlı değildi (anahtar adıyla denetlendi).
- `UygulamaAyarlari::eposta($app)`: gönderen adı (ad) + yanıt adresi (destek e-postası). **Beş e-postanın hepsi** buradan
  kuruluyor: kod, giriş bağlantısı, portal, aktivasyon, kurtarma. Giriş bağlantısı şemayı, aktivasyon mağaza adresini
  buradan okuyor.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Ayarlar DB'de, liste yaml'da | hepsi yaml / hepsi DB | Liste sözleşme enum'uyla eşli (A4); ayarlar panelden değişsin |
| 2 | Yedek yok (satır yoksa hata) | yaml yedeği | §12: iki kaynak olunca hangisinin geçerli olduğu görünmez |
| 3 | Ad + destek her e-postada | yalnız yeni e-postalar | Ailenin tamamı (blok kuralı); no-reply'a yazan kişi kaybolmasın |
| 4 | Doğrulama sunucuda, formda `noValidate` | tarayıcı denetimi | §44 ailesi: tarayıcı sessizce gönderimi keser, sunucu gerekçe söyler |
| 5 | Şema `^[a-z][a-z0-9+.-]{1,31}$`, mağaza yalnız `https://` | serbest | iOS şema kuralı; e-postaya güvensiz adres girmesin. DB CHECK de aynı (§4) |

## Test

- `PanelAyarlarTest` (19):
  - girişsiz yönlendirme; göçün taşıdığı değerler; 404;
  - kaydet → kod e-postasında gönderen adı + Reply-To, giriş bağlantısı yeni şemayla;
  - destek boşken Reply-To yok;
  - 10 geçersiz girdi 422 (kayıt değişmez); jetonsuz 403;
  - DB kısıtı; satır yoksa şema hatası; `/panel/ayarlar` 200 (§51).
  - `tearDown` göç hâline döndürür (§13: sonraki testler `keepchat://` bekliyor).
- Giriş, portal, aktivasyon, kurtarma testlerine gönderen adı iddiası eklendi.
- Panel `Ayarlar.test.tsx` (3): yükleme ve menü, tamamı PUT + jeton, geçersiz e-postada sunucunun hatası.

## Kırma (18/18 🔴)

| # | Bozulan | Sonuç |
|---|---|---|
| C1 | Gönderen adı yok | 🔴 6 |
| C2 | Reply-To yok | 🔴 |
| C3 | Şema denetimi yok | 🔴 3 (DB kısıtı 500 verdi, 422 değil) |
| C4 | `https://` denetimi yok | 🔴 (aynı) |
| C5 | E-posta denetimi yok | 🔴 |
| C6 | Boş ad denetimi yok | 🔴 (DB kısıtı) |
| C7 | Kırpma yok | 🔴 2 |
| C8 | Şemaya yedek | 🔴 |
| C9 | Giriş şeması sabit | 🔴 2 |
| C10 | Mağaza adresi okunmaz | 🔴 |
| C11a–e | Beş e-postanın her biri eski `new Email()` | 🔴 (her biri) |
| C12 | CSRF yok | 🔴 |
| P1 | Formda `noValidate` yok | 🔴 |
| P2 | Gövde eksik gönderilir | 🔴 |

## Gerçek sistem (2026-11-10, dev)

- `/panel/ayarlar` ve `/panel/api/…/ayarlar` girişsiz 302 → `/panel/giris`. Dev DB'de `keepchat | KeepChat | keepchat`.
- Geçici destek adresi ve ölçüm satırı ile `app:posta:dizi`: Mailpit'te `From: KeepChat <no-reply@myweb2wave.local>`,
  `ReplyTo: destek-olcum@example.com`. Ayar ve satırlar geri alındı.
- Sayfa tarayıcıda denenmedi (giriş kullanıcıda).
- `bin/kontrol` YEŞİL, core 708 test.
