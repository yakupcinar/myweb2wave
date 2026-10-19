# Blok: Kimlik + e-posta kodu + hak ucu (A5) — 2026-10-19

## Ne yapıldı
Uygulamanın **sunucusu** için üç uç (hepsi `Authorization: Bearer <uygulama anahtarı>`):
- `POST /apps/{app}/baglama/kod {email}` → 202; e-postaya 6 haneli kod.
- `POST /apps/{app}/baglama/dogrula {email, kod, kullanici}` → `200 {"kisi": "<uuid>"}`;
  uygulamadaki kullanıcı kişiye bağlanır.
- `GET /apps/{app}/kullanicilar/{kullanici}/hak` → `200 {"aktif": bool, "durum": …}` / 404.
Tablolar: `kisiler` (kalıcı UUID — ileride StoreKit `appAccountToken`, Ç29),
`baglantilar` (PK uygulama + kullanıcı), `dogrulama_kodlari`. Geliştirmede
e-posta Mailpit'e (`localhost:8025`), testte bellekte. Sözleşmeye eklendi.

## Neden böyle
- **Sunucudan sunucuya, uygulama başına anahtar (A5):** uygulamanın içinde
  sır yok. Yapılandırılmamış (boş) anahtar her zaman reddedilir.
- **Kod:** `random_int`, düz değil HMAC özeti (`kernel.secret` ile), 10 dk,
  5 deneme, tek kullanım, yeni kod eskileri geçersiz kılar, (uygulama,
  e-posta) başına 10 dk'da 3 kod. Kod isteği her zaman 202 — e-postanın
  varlığı dışarıdan anlaşılmaz.
- **Hak:** kişinin doğrulanmış e-postası, uygulamanın aboneliklerindeki
  e-postayla `lower()` eşlenir; `active`, `trialing`, `past_due` erişim verir.
- **Aynı e-posta = aynı kişi**, uygulamadan bağımsız (A4: kimlik tek, hak
  uygulama başına).
- Reddedilen: kodu düz saklamak (DB okunursa bağlama ele geçer); kod
  doğrulamada uygulama kontrolüyle yetinmek (kısıt + test, tuzak §4); kişi
  oturumu/kullanıcı jetonu — bu blokta değil (yeni uygulamalar için sonra).

## Yolda ölçülenler
- **Tarif `MAILER_DSN=null://null`'ı geliştirmeye de yazmıştı** → ölçüldü:
  202, kod DB'de, Mailpit'e **0** e-posta → tuzak §24. Geliştirmede Mailpit.
- PHPStan max: DB'den gelen değerlerin denetimsiz dönüştürülmesi (3 yer) →
  tip denetimi; tanınmayan `deneme` değeri "kilitli" sayılır (kapalı başarısızlık).
- Yanıltıcı test adı düzeltildi ("başka uygulamanın kodu" → aslında "başka e-posta").

## Kırma denemeleri

| # | Bozulan karar | Sonuç |
|---|---|---|
| I1 | boş anahtar koruması yok | ilk: **TUTMADI** (hiçbir test boş jeton göndermiyordu — tahmin edilmişti) → `testBosJeton…` yazıldı → 🔴 |
| I2 | anahtar karşılaştırılmıyor | 🔴 `testYanlisAnahtar401` |
| I3 | kod düz saklanıyor | 🔴 `testKodIstegi202…` |
| I4 | deneme sınırı yok | 🔴 `testBesYanlis…` |
| I5 | süre kontrolü yok | 🔴 `testSuresiDolmus…` |
| I6 | kod kullanıldı işaretlenmiyor | 🔴 `testKullanilmisKod…` |
| I7 | yeni kod eskisini geçersiz kılmıyor | ilk: **TUTMADI** — doğrulama yalnız en son koda baktığı için eski kod zaten düşüyordu; asıl risk "yeni kod kullanılınca eski kod yeniden en son aktif olur" sırası testte yoktu → `testEskiKodYenisiKullanildiktanSonraDaGecmez` → 🔴 |
| I8 | hız sınırı yok | 🔴 `…429` |
| I9 | past_due erişimsiz | 🔴 `testPastDueErisimVerir` |
| I10 | hakta uygulama süzgeci yok | 🔴 `testBaskaUygulamaninAboneligi…` |
| I11 | e-posta normalleştirilmiyor | 🔴 3 test |

## Gerçek istek (geliştirme, Mailpit)
Yanlış anahtar 401 · kod isteği 202 → Mailpit'te 1 ileti, alıcı küçük harf,
6 haneli kod · e-postadaki kodla doğrula → `200 {"kisi": "d435616c…"}` ·
**hak `{"aktif":true,"durum":"active"}`** — önceki blokta tarayıcıda test
kartıyla ödenen `test+uygulama@` aboneliği; web ödemesi → bağlama → hak
zinciri uçtan uca · bağlanmamış 404 · aynı kod ikinci kez 422 · DB'de kod
düz değil (0).

## Ölçüm
`bin/kontrol` EXIT=0 · core-test `OK (41 tests, 180 assertions)`, 0 atlanan.
