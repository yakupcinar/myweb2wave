# Tuzaklar — hata vermeden YANLIŞ CEVAP üretenler

> CLAUDE.md §4'teki her satırın tam metni burada. Yeni tuzak: **belirti**
> (ne gördün) · **sebep** · **ölçüm** (nasıl kanıtladın) · **doğrusu**.
> İkisine birden yazılır.

## §1 — Boru çıkış kodunu yutar
**Belirti:** kırmızı süit yeşil görünür. **Sebep:** `$?` borunun son
komutunundur. **Doğrusu:** `komut > log 2>&1; echo EXIT=$?`.

## §18 — Boş kapsayıcıya bağlı değiştirici koşmaz (SwiftUI)
**Belirti:** profil fotoğrafı hiç yüklenmedi, hata/istek yok.
**Sebep:** `Group { if let resim … }.task { … }` — `Group` değiştiricileri
çocuklarına dağıtır, çocuk yokken `.task` hiç çalışmaz. **Ölçüm**
(KeepChat Ç136): sunucu günlüğünde istek sayısı, önce 0 sonra 1.
**Doğrusu:** `ZStack`.

<!-- Diğer tuzaklar ilk kez ısırdıklarında burada açılır. -->

## §19 — Test yapılandırmasındaki `<server>`, gerçek ortam değişkenine yenilir
**Belirti:** `phpunit.dist.xml`'de `APP_VERSION=test-surumu` verildi; uç
`dev` döndü. **Sebep:** `<server>` yalnız `$_SERVER`'ı yazar; Symfony önce
`$_ENV`'i okur ve compose'un konteynere verdiği gerçek `APP_VERSION=dev`
oradadır. **Ölçüm:** beklenen değer testte düz sabitti (tuzak §3) → iki test
`'dev' !== 'test-surumu'` ile düştü. Beklenti ortamdan okunsaydı yeşil
kalırdı. **Doğrusu:** `<env name="…" force="true"/>`.

## §20 — Renkli çıktıda `grep '^…'` satırı sessizce kaybeder
**Belirti:** kontrol betiği "OK (2 tests…)" satırını basmadı, hata yoktu.
**Sebep:** PHPUnit satır başına ANSI renk kodu koyuyor; `^OK` eşleşmiyor,
renk kodu aramadan **sonra** ayıklanıyordu. **Doğrusu:** önce
`sed 's/\x1b\[[0-9;]*m//g'`, sonra `grep`. Atlanan test sayısını taşıyan
satır budur — kaybolursa `skipped` görünmez (tuzak §8).

## §16 — macOS araçları farklı davranır ve hata vermez
**Belirti (grep, 2026-10-19):** aynı dosyada `grep -c '], $dbOk ? 200 : 503);'`
→ **0**, `grep -Fc` aynı metin → **1**; kırma denemesi "bulunamadı" diye
uygulanmadı. `cat -A` macOS'ta yok, hata çıktıyı yuttu. **Doğrusu:** sabit
metin ararken `grep -F`; metin değiştirirken düzenli ifadesiz bir betik
(Python `str.replace` + "tam bir kez geçiyor mu" kontrolü).

## §21 — CI sonucunu SHA ile ararken push olmadıysa ESKİ koşu gelir
**Belirti:** kırma denemesi "yeşil" döndü; oysa yama hiç uygulanmamış,
commit/push olmamıştı. **Sebep:** HEAD değişmedi → aynı SHA'nın önceki
(yeşil) koşusu yeni denemenin sonucu sanıldı. İki kez yaşandı.
**Doğrusu:** sonucu okumadan önce "uygulandı mı" (grep -F) ve "SHA yeni mi"
ikisini de bas; biri yoksa sonuç **KOŞULMADI**.

## §22 — `docker compose run -d` 0 döner, içindeki süreç ölmüş olabilir
**Belirti:** `stripe listen` arka plana atıldı (EXIT=0), `stripe trigger`
"Trigger succeeded!" dedi, ama hiçbir olay iletilmedi; hata yok.
**Sebep:** CLI ilk saniyede `must specify events…` ile çıkmıştı; `-d`
konteyneri başlatır, sürecin yaşadığını söylemez. **Doğrusu:** arka plan
süreci için `docker ps` durumu + sürecin "hazır" satırını bekle (`Ready`),
sonra işi tetikle.

## §23 — `curl -s` + köşeli parantezli adres sessizce BOŞ döner
**Belirti:** `curl -s ".../v1/events?created[gte]=…"` → çıktı yok, hata yok;
"olay yok" sanılacaktı. **Sebep:** curl `[...]`'ı kendi glob sözdizimi sayar;
geçersiz aralıkta durur, `-s` hatayı yutar. **Doğrusu:** `curl -g` (glob
kapalı) ya da parametreyi `--data-urlencode` + `-G`; boş çıktıyı sonuç
saymadan önce aracın hata verip vermediğini ölç (tuzak §17).

## §24 — `MAILER_DSN=null://null` e-postayı sessizce atar, uç yine 202 döner
**Belirti:** kod isteği 202, kod veritabanında, kullanıcıya hiçbir şey
gelmiyor; hata yok. **Sebep:** Symfony mailer tarifi `.env`'e (yani
geliştirmeye de) `null://null` yazar; null taşıyıcı gönderimi "başarılı"
sayar. **Ölçüm (2026-10-19):** aynı istek `null://null` ile → 202, DB'de 1
kod, Mailpit'te 0 ileti. **Doğrusu:** geliştirmede yakalayıcı (Mailpit),
testte `null` + `assertEmailCount`; tarif varsayılanını okumadan geçme.

## §25 — TypeScript nesne yaymada (`...`) fazla alanı denetlemez
**Belirti:** huni ödeme gövdesine `...(ziyaret ? { ziyaretId: ziyaret } : {})`
yazıldı — yanlış alan adı; `astro check` yeşil, çekirdek bilinmeyen alanı
yok sayıyor, atıf sessizce kayboluyor. **Sebep:** fazla alan denetimi
yalnız doğrudan nesne değişmezinde çalışır; yayılan nesneye uygulanmaz.
`Partial<Record<…>>` ile kurulan bir nesne de anahtar yazım hatasını
taşıyabilir. **Ölçüm (2026-10-20, `atif.md` A13):** yayma ile kırma
TUTMADI (EXIT=0); gövde sözleşmeden üretilen tipe bağlanıp alan atamayla
kurulunca iki kırma da `ts(2551)` ile düştü. **Doğrusu:** istek gövdesini
üretilen tipten bir değişkene kur, isteğe bağlı alanı atamayla ekle.

## §26 — Şifre özetindeki `$` ortam dosyasında sessizce bozulur
**Belirti:** panel girişi doğru şifreyle "Kullanıcı adı ya da şifre yanlış"
diyor; hata, uyarı yok. **Sebep:** bcrypt özeti (`$2y$13$…`) `$` içerir;
Symfony dotenv de, docker compose'un `--env-file`'ı da tırnaksız değerde
`$…`'ı değişken sayıp boşla değiştirir. **Ölçüm (2026-10-20, `panel.md`):**
`.env.test`'te tırnaksız → giriş testi düştü (60 karakterlik özet bozuldu);
compose'ta tırnaksız → konteynere 22 karakter gitti, tek tırnaklı → eşit.
Ayrıca `docker compose config` çıktısı doğru değeri de `$$` diye kaçışlıyor
— oradan okuyan denetim önce `$$`→`$` yapmalı. **Doğrusu:** özeti TEK
TIRNAK içinde yaz; dağıtım ön kontrolü özetin biçimini doğrular.

## §27 — Astro bileşen stili yuvadan (slot) gelen öğeye uygulanmaz
**Belirti:** huninin paywall'undaki "Abone ol" düğmesi tarayıcıda düz,
stilsiz; `astro check` ve derleme yeşil, hata yok. **Sebep:** bileşenin
`<style>`'ı kapsamlıdır (yalnız bileşenin kendi öğelerine sınıf ekler);
sayfanın `slot`'a koyduğu öğe bileşenin kapsamında değildir.
**Ölçüm (2026-10-20, `huni-anket.md`):** aynı `class="ana"` bileşenin kendi
düğmelerinde mavi, yuvadakinde stilsiz; `<style is:global>` → ikisi aynı.
**Doğrusu:** sayfanın tamamını kuran bileşende `is:global`, ya da stili
yuvayı dolduran dosyaya koy. Görsel sonuç yalnız tarayıcıda ölçülür.

## §28 — Postgres JSONB nesne anahtarlarının sırasını DEĞİŞTİRİR
**Belirti:** taslak doğrulamadan geçip kaydedildi, aynı taslak veritabanından
okunup yayınlanırken "alanlar tam olarak … olmalı" diye reddedildi; testte
`assertSame` aynı içerikli iki diziyi "farklı" dedi. **Sebep:** `jsonb`
anahtarları kendi düzenine göre (uzunluk, sonra alfabe) saklar; geri
okunan nesnenin anahtar sırası yazılanla aynı değil. Liste (dizi) sırası
korunur. **Ölçüm (2026-10-20, `huni-yayin.md` Y3):** anahtarları sıralı
karşılaştıran doğrulayıcıyla yayın 3 testte düştü; küme karşılaştırması
→ geçti. **Doğrusu:** nesne anahtarını sırasız karşılaştır (sırala ya da
küme); testte `assertEquals`; sıranın anlamı varsa liste kullan.

## §29 — Twig şablonuna `app` adlı değişken geçmek global `app`'i ezer
**Belirti:** panelde "v4 yayında" gibi flaş mesajları hiç görünmüyor; hata
yok (üretim). **Sebep:** `render(..., ['app' => 'keepchat'])` Twig'in global
`app`'ini (oturum, flaşlar, kullanıcı) ezer; `app.flashes` dizgede çağrılır.
Testte `strict_variables` açık → 500; üretimde kapalı → **sessizce boş**.
**Ölçüm (2026-10-20, `panel-huni.md`):** aynı şablon `strict_variables=false`
ile `app` dizgeyken `[]`, nesneyken `[tamam]` verdi. **Doğrusu:** şablon
değişkenine `app`, `_self` gibi ayrılmış ad verme (`uygulama`).

## §30 — Cache-Control'süz statik dosya tarayıcıda ESKİ hâliyle çalışır
**Belirti:** düzenleyici JS'i değiştirildi, sunucu yenisini veriyor, ama
panel eski davranışı gösteriyor; hata yok. **Sebep:** dosyada
`Cache-Control` yok, yalnız `Last-Modified`/`ETag`; tarayıcı tahmini
tazelikle önbellekteki kopyayı yeniden sormadan kullanır. Her dağıtımdan
sonra aynı şey olur. **Ölçüm:** sayfadaki betik 7154 bayt (eski), aynı
adrese elle `fetch` 7253 bayt (yeni). **Doğrusu:** adrese içerik özeti
(`?v=<md5>`); dosya değişince adres değişir.

## §31 — PHP'nin `upload_max_filesize` (2M) sınırı uygulamanın sınırını görünmez kılar
**Belirti:** kod "en çok 5 MB" diyor, 3–5 MB'lık fotoğraf yine reddediliyor —
mesaj "dosya gelmedi", 5 MB mesajı hiç çıkmıyor; hata günlüğü yok.
**Sebep:** PHP isteği uygulamaya gelmeden keser (`UPLOAD_ERR_INI_SIZE`);
FrankenPHP imajında varsayılan `upload_max_filesize = 2M`. **Ölçüm
(2026-10-21, `gorseller.md`):** konteynerde `ini_get` → 2M; testte 5 MB+1
bayt "dosya gelmedi" döndü. `conf.d/myweb2wave.ini` (6M/8M) sonrası 4,6 MB
JPEG gerçek HTTP ile 201. **Doğrusu:** uygulama sınırı yazınca PHP'nin
sınırını da ölç ve üstüne ayarla; `UPLOAD_ERR_INI_SIZE`'ı uygulamanın
mesajına çevir. Bellek de öyle: `memory_limit` 128M, GD piksel başına ~4
bayt — piksel sınırı buna göre (16 Mpx).

## §32 — Vitest CSS içe aktarmasını sessizce BOŞ dizgeye çevirir (`?raw` dahil)
**Belirti:** `import css from "./huni.css?raw"` hata vermiyor, `css` boş
dizge. CSS'te bir şeyin **olmadığını** iddia eden test (ör. "kapsamsız kural
yok") boş metinde her zaman yeşil kalır. **Sebep:** Vitest'in `test.css`
ayarı varsayılan olarak CSS dosyalarını işlemiyor, içeriği boşaltıyor; `?raw`
sorgusu da bundan kurtulmuyor (Vite'ın kendi derlemesinde dolu gelir).
**Ölçüm (2026-10-21, `huni-bilesenleri-h1.md`):** `:root` değişkenlerini
okuyan test `{}` aldı; `test: { css: { include: [/huni\.css/] } }` sonrası
6 değişken. **Doğrusu:** CSS'i metin olarak okuyan testte `css.include`
ver; ya da önce dosyanın dolu geldiğini iddia et (ör. seçici sayısı > 5).

## §33 — Testing Library'de metnin görünmesi `useEffect`'in koştuğunu garanti etmez
**Belirti:** `await findByText(...)` sonrası `document.title`, kökteki CSS
değişkeni ya da `history.state` okunuyor. Test çoğu koşuda yeşil, yük
altında (art arda kırma denemeleri) aralıklı kırmızı; ilgisiz bir kırma
denemesinde başka test düşüyor. Daha kötüsü, **olumsuz** iddia ("tema yoksa
değişken yazılmaz", "ilk adımda odak yok") effect koşmadan okununca her
zaman yeşil. **Sebep:** bu değerler pasif effect'te yazılıyor; DOM'daki metin
commit'te, effect ondan sonra. **Ölçüm (2026-10-21, `huni-oynatici-h2.md`):**
P15/P19'da D7, yeşil süitte bir kez D10 düştü. Düzeltmeden sonra 3 koşu
22/22, kırmalar tekrarlandı ve tuttu. **Doğrusu:** effect'e bağlı olumlu
iddia `waitFor` içinde. Olumsuz iddiadan önce aynı effect'in başka bir yan
etkisini bekle (başlık, ilk bildirim), sonra "yok" de.
