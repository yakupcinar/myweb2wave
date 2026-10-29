# L11 — b2: klasik form düzenleyicisi silindi, Puck tek düzenleyici (Ç53 (1))

2026-10-26 · kullanıcı kararı A13 ("Sil, Puck tek düzenleyici").

## Ölçüm (önce)
- `/panel/huniler/{app}/{ad}` klasik sayfa (`panel/huni.html.twig` +
  `public/varliklar/huni-duzenleyici.js`, form `POST …/yayinla` ve
  `…/surumler/{n}/taslaga`, yönlendirme + flaş); Puck `…/surukle`'de, yedek
  bağlantı "← Klasik düzenleyici".
- Puck'ın kaydet/yayınla/geri yükle uçları JSON (`/taslak`, `/durum/…`,
  b1) — klasik form uçlarının işini zaten yapıyor ve testli.

## Karar
- `panel_huni` (`/{app}/{ad}`) doğrudan Puck sayfasını açar; `/surukle`
  yolu, klasik sayfa, betik, form uçları ve `duzenleyiciDosyasi` bağımlılığı
  kalktı. Puck üst bağlantısı "← Huniler ve kurallar".
- **Reddedilen:** `/surukle` için yönlendirme (tek yönetici, eski yer imi
  için yol yazılmaz — kural 6a) · klasik uçları "yedek" diye bırakmak
  (iki düzenleyici iki doğrulama yolu demek).

## Test
`PanelHuniTest` 13: klasik 6 test kalktı — her birinin karşılığı JSON
uçlarında (XSS kaçışı → Puck sayfası testi; önizleme anahtarı → durum testi;
ikinci yayın 422, CSRF, geri yükleme → durum testleri; içerik özetli betik →
manifest testi). Yeni: düzenleme adresinde `#puck-duzenleyici` var, klasik
betik yok ve dosya yok; eski form uçları ve `/surukle` 404; sürüm sayısı
değişmedi. Önce kırmızıydı (eski sayfada Puck yok, uçlar 302).

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `huni-duzenleyici.js` geri konur | 🔴 kaldırma testi |
| K2 | Puck yine `/surukle`'de | 🔴 7 test |

## Gerçek istek (yerel çekirdek, girişsiz)
`GET /panel/huniler/keepchat/genel` → 302 `/panel/giris`;
`GET …/surukle` → 404. Puck'ın tarayıcıda açılışı kullanıcıda (panel şifresi).

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 221 çekirdek testi / 1.389 doğrulama.
