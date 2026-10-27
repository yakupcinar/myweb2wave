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
etkisini bekle (başlık, ilk bildirim), sonra "yok" de. **Olay da öyle:**
effect'te eklenen dinleyiciye (`popstate`) olay göndermeden önce dinleyiciyi
ekleyen effect'in yan etkisini bekle — H4'te yerelde yeşil, CI'da kırmızıydı
(`huni-gecisler-h4.md`).

## §34 — Gizli sekmede animasyon zaman çizelgesi durur: "içerik görünmez kaldı" yalanı
**Belirti:** tarayıcı aracıyla ölçülen sayfada CSS animasyonu
`playState=running`, yüzlerce ms sonra opaklık hâlâ 0 — içerik görünmez
kalıyor gibi. **Sebep:** sekme arka planda (`document.visibilityState ===
"hidden"`); Chrome kare çizmiyor, `document.timeline.currentTime` 0'da
duruyor. Aynı sebeple FCP/LCP kaydı da yok (H0, H2). **Ölçüm (2026-10-21,
`huni-gecisler-h4.md`):** zaman çizelgesi 0 → ekran görüntüsü bir kare
çizdirince 18.056 ms, animasyon bitti, opaklık 1. **Doğrusu:** zamanlamaya
bağlı her ölçümden önce `visibilityState` ve `document.timeline.currentTime`
yaz; gizliyse sonuç "ölçülmedi"dir, "bozuk" değil.

## §35 — Betikle doldurulan alanın DOM değeri uygulamanın durumu değildir
**Belirti:** tarayıcı aracında `input.value = …` + `input` olayı; alan yeni
değeri gösteriyor, ardından basılan düğme **eski** veriyle çalışıyor —
"uygulama gecikmeli / hatalı" sonucuna varılır. **Sebep:** olay uygulamanın
dinlediği öğeye ulaşmadı (Puck aynı alanı iki kez çiziyor; görünmeyen kopyaya
yazıldı) — DOM değişti, uygulamanın deposu değişmedi. **Ölçüm (2026-10-21,
`huni-dallanma-h5.md`):** "eski taslak yayınlandı" gözlemi bu yüzdendi; not
ve veritabanı hiç değişmemişti; buna dayanan "düzeltme" geri alındı.
**Doğrusu:** değişikliğin uygulamaya ulaştığını önce uygulamanın kendi
göstergesinden oku (durum notu, kayıt, ağ isteği); gerçek klavye/fare olayı
(`computer` aracı) ya da `form_input` kullan.

## §36 — CORS listesine eklenmeyen yeni uç tarayıcıda sessizce düşer
**Belirti:** yeni huni ucu sunucu testlerinde ve happy-dom testlerinde
yeşil; tarayıcıda ekranda her şey normal (şerit kalkıyor, seçim kaydoluyor)
ama veritabanına hiçbir şey yazılmıyor. **Sebep:** `nelmio_cors` yalnız
listedeki yollara izin veriyor; ön kontrol başarısız → `fetch` hata verir,
huni bildirim hatalarını bilerek yutar (akış durmasın). Sunucu testinde
köken yok, happy-dom'da CORS yok. **Ölçüm (2026-10-21, `huni-izin-h6a.md`):**
Resource Timing'de izin isteği **durum 0**, `izin_at` boş; yol listesine
eklenince 204. **Doğrusu:** tarayıcıdan çağrılan her yeni uç için önce
`OPTIONS` + `Origin` ile CORS testi (kırmızı), sonra listeye ekle.

## §11 — Aracın yanıtı ölçtüğün şeyin yanıtı sanılır (örnek: tarayıcı uzantısı 503)
**Belirti:** Claude in Chrome ağ kaydı çekirdeğe giden çapraz kökenli
POST/DELETE'i **503** gösterdi (`ilerleme`, `izin`; 2026-10-26).
**Sebep:** uzantının kaydı; sunucu isteği işlemişti. **Ölçüm:** çekirdek
günlüğünde rota eşleşti + işlem commit edildi; DB'de satır var; aynı istek
sayfadan `fetch` ile 204. **Doğrusu:** uzantının durum kodunu kanıt sayma;
sunucu günlüğü ya da DB'den oku.

## §37 — iCloud'daki masaüstünde üretilen dizin çakışma kopyası alır
**Belirti:** `composer install` → `cache:clear` aralıksız kırmızı:
`Failed to remove directory "/app/var/cache/de~" … Directory not empty`;
kod değişmemişken. **Sebep:** masaüstü iCloud Drive'a eşitleniyor; iCloud
önbellek dizinlerinde `Symfony 2`, `pools 2` gibi 0 B yer tutucu kopyalar
yaratıyor, silme yarışı yarıda kalan `de~`'yi bırakıyor ve sonraki her koşu
ona takılıyor. **Ölçüm (2026-10-26):** `defaults read com.apple.finder
FXICloudDriveDesktop` = 1; `find core/var -name "* 2"` üç dizin; `du` 0 B,
bağlantı sayısı 65535; silmek 5 dk'yı aştı. **Doğrusu:** projeyi iCloud
dışında tut (ya da `.nosync`); kırmızıda önce `find . -name "* 2"`.

## §38 — Docker'daki Vite geliştirme sunucusu macOS'taki değişikliği görmez
**Belirti:** `huni-bilesenleri/huni.css` değişti, testler yeni kuralı
gördü; tarayıcı (yenileme dahil) **eski stili** çizdi, hata yok.
**Sebep:** `huni-dev` kabı günlerdir açık; bağlı dizindeki dosya olayı
kaba ulaşmadı, Vite modülü önbellekten verdi. **Ölçüm (2026-10-27,
`huni-mobil-gorunum.md`):** `curl …/@fs/huni-bilesenleri/huni.css` yeni
sınıfı **0** kez, aynı adres `?t=1` ile **1** kez içerdi; kap yeniden
başlayınca 1. **Doğrusu:** tarayıcıda görsel ölçümden önce sunulan dosyayı
`curl` ile oku; eskiyse `docker restart huni-dev`.
