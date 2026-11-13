# Blok: H6a — reklam ölçümü onay şeridi (A12) — 2026-10-21

## Ne yapıldı
Hunide sayfanın üstünde bir onay şeridi: "Reklamlarımızın işe yarayıp
yaramadığını ölçmek için Meta ve TikTok'un ölçüm araçlarını kullanmak
istiyoruz…" — **Reddet** ve **Kabul et** eşit görünümde. Seçim bu tarayıcıda
hatırlanır (`localStorage`, yalnız seçim); önizlemede şerit yok.

Tıklama kimlikleri (`fbclid`, `ttclid`) artık **yalnız izinle** saklanıyor:
- ilk ziyaret: ziyaret UTM ile açılır, kimlik gitmez; **Kabul** → yeni uç
  `POST /huni/ziyaretler/{id}/izin` o anki kimlikleri + izin anını yazar
  (önceki değerleri silmez, ilk izin anı korunur);
- izin önceden verilmişse: kimlikler ziyaretle birlikte, `izin: true`;
- çekirdek `izin` olmadan gelen kimliği **atar** — önbellekte kalmış eski
  huni betiği (tuzak §30) gönderse bile.
Bugüne kadar kimlikler izinsiz saklanıyordu (Ç34). Yeni sütun
`huni_ziyaretleri.izin_at` (göç `Version20261021150000`). H6b'de sunucu
dönüşümü yalnız izinli ziyaretin ödemesini bildirecek.

## Neden böyle
Kullanıcı kararları A12 (`docs/pre-setup.md`): Meta + TikTok, tarayıcı
pikseli + sunucu, herkese basit onay şeridi, yalnız abonelik olayı. H6 üç
bloğa bölündü: H6a onay (bu), H6b sunucu dönüşümü, H6c pikseller.

| Karar | Elenen | Neden |
|---|---|---|
| Şerit akışta, sayfanın üstünde | alta sabit şerit | Sabit şerit küçük ekranda adım düğmelerini örterdi |
| Kabul ve Reddet eşit | "Kabul" öne çıkan | Reddetmek kabul etmek kadar kolay olmalı (hukuki doğrulama Ç12'de bekliyor) |
| Ziyaret izin beklemeden açılır, izin sonradan ayrı uçla | izin gelene kadar ziyaret açmamak | Şeridi yok sayanlar çok; organik sayım (dönüşüm paydası) kaybolurdu |
| Çekirdek izinsiz kimliği atar | yalnız istemciye güvenmek | Eski önbellekli betik (§30) ya da başka istemci izinsiz gönderebilir |
| Seçim `localStorage`'da | çerez / hiç saklamamak | Sunucuya gitmez; depo yoksa (gizli pencere) şerit yeniden çıkar, hata yok |

## Testler
- `AtifTest` (+4, 1 güncellendi): izinle ziyaret kimlik + `izin_at`; izinsiz
  kimlik atılır, UTM kalır, `izin` dizgeyse 422; sonradan izin kimlik yazar,
  ikinci izin ilk anı ve fbclid'i korur; geçersiz gövde 422, bilinmeyen
  ziyaret 404 (sözleşmeyle); **CORS** ilerleme + izin uçlarında.
- `Huni.test.tsx` (+7): ilk ziyaret (şerit var, kimlik yok), Kabul, Reddet,
  önceden izin, önceden red, önizleme, depo erişilemez.

## Kırma denemeleri

| # | Deneme | Sonuç |
|---|---|---|
| V1 | izinsiz kimlik saklanır | 🔴 PHPUnit 1 |
| V2 | çekirdek her ziyareti izinli sayar | 🔴 PHPUnit 2 |
| V3 | ikinci izin fbclid'i ezer | 🔴 PHPUnit 1 |
| V4 | ikinci izin ilk anı ezer | 🔴 PHPUnit 1 |
| V5 | izin ucu bilinmeyen alanı kabul eder | 🔴 PHPUnit 1 |
| V6 | CORS'ta izin ucu yok | 🔴 PHPUnit 1 |
| V7 | huni kimlikleri her zaman gönderir | 🔴 2 test |
| V8 | önizlemede şerit | 🔴 1 test |
| V9 | Reddet de izin bildirir | 🔴 1 test |
| V10 | `izinOku`'da `try/catch` yok | ⚪ **tutmadı** — `Storage.prototype` sahtesi happy-dom'da uygulanmıyordu; test hiç hata görmüyordu → test `window.localStorage` erişimini hata verecek şekilde yeniden yazıldı |
| V10b | aynı, düzeltilmiş testle | 🔴 1 test |
| V10c | `izinYaz`'da `try/catch` yok | 🔴 1 test |
| V11 | seçim hatırlanmaz | 🔴 2 test |

## Tarayıcıda bulunan iki kusur (testler yeşilken)
1. **Geliştirme veritabanı göçsüz** — göç yalnız test veritabanına
   uygulanmıştı; tarayıcıda `/huni/ziyaretler` **500** (`izin_at` yok).
   Huni hatayı yuttuğu için ekran normaldi (tuzak §7: bildirmek uygulamak
   değildir). `doctrine:migrations:migrate` ile düzeldi. Üretimde göç dağıtım
   betiğinde (`dagitim.md`).
2. **CORS** — izin ucu `nelmio_cors` yol listesinde yoktu: tarayıcıda istek
   **durum 0**, şerit kalktı, seçim "evet" kaydedildi ama veritabanında izin
   yoktu. Sunucu testleri ve happy-dom testleri bunu göremez → önce kırmızı
   CORS testi, sonra düzeltme (tuzak §36).

## Ölçüm
- `bin/kontrol` **EXIT=0, 149 sn**; çekirdek **184** (+4), huni 35 (+7),
  bileşen 19, düzenleyici 15. JS gzip 73.852 B (+502; şerit).
- Tarayıcı (Chrome, `?huni=fitness&utm_source=meta&fbclid=…`): şerit üstte,
  iki düğme eşit; gerçek tıklamayla **Kabul et** → izin isteği **204**,
  şerit kalktı, depo "evet"; veritabanında `fbclid=H6aTarayici1`, `izin_at`
  dolu. Sayfa `fbclid=H6aDonen2` ile yeniden açılınca şerit yok, ayrı izin
  isteği yok, `fbclid=H6aDonen2` izinle ziyarete yazıldı.
- Ortam: Vite bağımlılık önbelleği yeniden iyileştirmeden sonra
  `openapi-fetch` için **504** (Outdated Optimize Dep) verdi, sayfa
  "Yükleniyor…"da kaldı; `docker restart huni-dev` düzeltti (Ç51 (1)).
