# Tuzaklar — hata vermeden YANLIŞ CEVAP üretenler

> CLAUDE.md §4'teki her anahtarın tam metni burada. Yeni tuzak: **belirti**
> (ne gördün) · **sebep** · **ölçüm** (nasıl kanıtladın) · **doğrusu**.
> İkisine birden yazılır.

## §1 — Boru çıkış kodunu yutar
**Belirti:** kırmızı süit yeşil görünür. **Sebep:** `$?` borunun son
komutunundur. **Doğrusu:** `komut > log 2>&1; echo EXIT=$?`.


## §2 — (özet; ayrıntılı kayıt yok)

`grep -i` Türkçe `ı`/`İ` ile **yanlış "bulunamadı"** der. Harf sınıfı kullan ya da `-i`'siz ara.

## §3 — (özet; ayrıntılı kayıt yok)

Bir iddiayı **ölçtüğü kodun kendisiyle** hesaplama — beklenti de bozulur, test yeşil kalır. Bekleneni bağımsız hesapla.

## §4 — (özet; ayrıntılı kayıt yok)

Uygulamadaki "bu ad alınmış mı" kontrolü, arkasındaki **DB kısıtını görünmez kılar**; kısıtı doğrudan ölçen ikinci test yaz.

## §5 — (özet; ayrıntılı kayıt yok)

Biçimlendirici "liste" kipinde biçimsiz dosyada da **çıkış kodu 0** dönebilir (`gofmt -l`); ölçüt çıktının boş olması.

## §6 — (özet; ayrıntılı kayıt yok)

"Hata döndü mü" yetmez — **hangi hata**: zaman aşımı da hatadır. Türüyle/koduyla karşılaştır.

## §7 — (özet; ayrıntılı kayıt yok)

Bir yapılandırmayı **bildirmek, uygulandığı anlamına gelmez** (göç dosyası, CI yaml, deploy yaml). Etkisini çalışan sistemden oku.

## §8 — (özet; ayrıntılı kayıt yok)

Uygulanmamış bir **kırma denemesi** testi suçsuz yere "ölçmüyor" ilan ettirir: yama tutmadıysa sonuç "yeşil" değil **KOŞULMADI**'dır. Derleme hatasıyla kırmızı olan deneme de sahtedir. Atlanan test (`skip`) BAŞARI görünür — `passed` mı `skipped` mi oku.

## §9 — (özet; ayrıntılı kayıt yok)

**Çift tırnak içindeki ters tırnak kabukta KOMUT olarak çalışır** — `echo "… \`make test\` …"` gerçekten süiti başlatır, commit mesajı metni yer.

## §10 — (özet; ayrıntılı kayıt yok)

*"Bu satır ŞART"* yorumu **ölçülmediyse büyük ihtimalle yanlıştır.** Savunmayı tek başına kaldır ve koştur; yorumu ölçümle yaz.

## §12 — (özet; ayrıntılı kayıt yok)

**Yedek zincir kusuru maskeler**: alanın dolu olması asıl yolun çalıştığını kanıtlamaz. Bir sütunun DOLU olması da doğru olduğunu göstermez — *içinde ne var* diye sor.

## §13 — (özet; ayrıntılı kayıt yok)

Yeşil bir süit **ölçülmemiş bir sıra/zaman varsayımı** taşıyabilir; yeni iş onu bozmaz, açığa çıkarır. Kırmızının üç şıkkı: yarış · mantık · **zaman aşımı**.

## §14 — (özet; ayrıntılı kayıt yok)

Ölçümden önce **ortamı say**: dolu disk, eşzamanlı ağır koşum, iki test aynı simülatörde/DB'de — hepsi kodu suçlayan sahte kırmızı üretir. `df -h` ilk komut.

## §15 — (özet; ayrıntılı kayıt yok)

`kill $!` alt kabuğu öldürür, içindeki komutu değil (öksüz süreç sonraki koşuyu bozar) — alt kabukta `exec`. `pkill -f <desen>` kendi kabuğunu da öldürür.

## §17 — (özet; ayrıntılı kayıt yok)

Log'da "yok" sonucu çoğu zaman **soru** hatasıdır (yanlış etiket, varsayılan zaman penceresi, yanlış log biçimi). Önce log'a yazıldığı bilinen bir kontrol grubunu ara.

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

**Kabuk zsh (2026-11-04, `v6-nis-sablonlari.md`, `m1b-teklifler.md`):** bu
makinede komutlar zsh'te koşuyor. (1) `K="python3 kir.py"; $K …` → "no such
file": zsh değişkeni kelimelere **bölmez**; aynı sebeple döngüde
`ayarla $t` (`t="giris 3 0.99"`) tek argüman gitti — üç ölçüm teklifsiz
alındı, "yeşil" sanılabilirdi. (2) Tek tırnak içindeki `'\u00a0'` programa
**karakter** olarak ulaştı (`repr` → `'\xa0'`), kırma yaması "0 kez eşleşti".
**Doğrusu:** komut için işlev (`K() { …; }`), bölünecek değişken için
`${=t}`; ters bölülü metni kabuktan değil Python içinden ver; yamanın
eşleşme sayısını her zaman yazdır (§8).

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
**Aynı aile (M6b2, 2026-11-09, `m6b2-portal-web.md`):** yeni uç yalnız kökene bakan testten listede yokken de geçti. `/huni/portal-baglantisi` alttaki yalnız-GET huni tanımı desenine (`^/huni/[a-z0-9_-]+`) de uyuyor; köken başlığı yine geliyor, ama POST izinli değil. Ön istek testinde `Access-Control-Allow-Methods` da ölçülür (kırma W8).

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

## §39 — Testing Library `getByText`/`findByText` gizli öğeyi de bulur
**Belirti:** "önizlemede ikinci adımdan başlar" testi `findByText("Kiminle?")`
ile yeşil olurdu — adım ekranda değilken. Tersine "ilk adım yok" iddiası
(`queryByText("Merhaba")` null) kod doğruyken kırmızı.
**Sebep:** huni oynatıcısı bütün adımları DOM'da tutar, görünmeyenleri
`hidden` ile gizler; metin sorguları `hidden`'ı (ve `display:none`'ı)
**süzmez** — bulunmak görünmek değildir. **Ölçüm (2026-10-27,
`ai-ekran-goruntusu.md`):** kod yokken "gerçek ziyarette adım yok sayılır"
testi `queryByText("Kiminle?")` ile kırmızıydı; DOM'da `<h1>Kiminle?</h1>`
gizli adımın içindeydi. **Doğrusu:** görünürlüğü ayrıca ölç —
`el.closest("[hidden]") === null` (ya da `toBeVisible`) — ve olumlu iddiayı
da görünürlükle yaz.

## §40 — Boyamadan sonra uygulanan tema + CSS geçişi = ilk görünüm ara renk
**Belirti:** aynı temayla çekilen ekran görüntülerinde düğme bazen
varsayılan mavi, bazen tema rengi; hata yok, testler yeşil.
**Sebep:** tema değişkenleri `useEffect`'te (ilk boyamadan sonra) köke
yazılıyor; düğmede `transition: background-color 120ms` var → öğe önce
varsayılanla çiziliyor, sonra geçişle temaya dönüyor. Ziyaretçi her
açılışta parlama görüyor. jsdom boyama yapmadığı için Testing Library
iki effect'i ayırt edemez — test hep yeşil. **Ölçüm (2026-10-27,
`gorunum-g3.md`):** 6 çekimde 4'ü ara renk → `useLayoutEffect` ile 6/6
hedef renk. **Doğrusu:** ilk çizimi etkileyen değişkeni boyamadan önce
yaz (`useLayoutEffect` / sunucuda); görsel iddiayı gerçek tarayıcıda,
birden çok çekimle ölç.

## §41 — Veritabanı sıralaması ≠ JS sıralaması (aynı liste, farklı sıra)
**Belirti:** aynı seçenek listesi sunucuda "a-1 a_1 a1", tarayıcıda "a-1 a1 a_1"; hata yok, iki taraf ayrı ayrı yeşil.
**Sebep:** Postgres `ORDER BY` veritabanının sıralamasını (en_US.utf8: noktalama yok sayılır) kullanır; JS `sort()` kod birimi sırası.
**Ölçüm (2026-11-02, `t2-ozellik-deposu.md`):** ortak örnekli eşlik testi (`OzellikEsligiTest`) ilk koşuda tam bu farkla kırmızı.
**Doğrusu:** iki dilin karşılaştırdığı sırada `COLLATE "C"`; eşliği ortak örnek dosyasıyla iki tarafta ölç.

## §42 — Puck yuvadaki blok değişince üst bileşenin `resolveData`'sı koşmaz
**Belirti:** üst bileşen (ekran) yuvadaki bloğa göre alanını ayarlayacak — birim testi yeşil, düzenleyicide hiçbir şey olmuyor.
**Sebep:** `resolveData` bileşenin KENDİ verisi değişince/eklenince/yüklenince çağrılır; yuvadaki çocuk değişimi üstü tetiklemez.
**Ölçüm (2026-11-02, `t1c-tasima-olcumu.md`):** tarayıcıda soru bloğu çokluya çevrildi, ekranın düğmesi gelmedi.
**Doğrusu:** çocuklara bakan kural düzenleyici bekçisinde (her veri değişiminde saf işlev + `setData`); davranışı tarayıcıda ölç.

## §43 — Puck sayfanın stilini önizleme çerçevesine kopyalar
**Belirti:** düzenleyici başka bir uygulamanın içine taşınınca önizleme huniden farklı çizilir (koyu zemin, başka yazı tipi, dar içerik); hata yok, testler yeşil.
**Sebep:** Puck'ın çerçevesi kabuk belgesinin `<style>`/`<link>` düğümlerini kopyalar — kabuğun genel sıfırlaması (Tailwind preflight, `body` kuralları) huniye de uygulanır.
**Ölçüm (2026-11-03, `p3-duzenleyici-sayfasi.md`):** panel stiliyle önizlemede 16 öğenin 39 özelliği farklı (genişlik 520 → 438 px, zemin koyu), düzenleyici kabuğunda 253 fark.
**Ters yön (2026-11-03, kullanıcı panelde ölçerken):** tembel yüklenen düzenleyici parçasının CSS'i (Puck + `huni.css`) çıkınca da sayfada kalır; `huni.css`'in `:root --metin`'i panelinkiyle **aynı ad** → düzenleyiciden dönünce panel yazısı koyu zeminde koyu (Kullanıcılar okunmaz). Testler yeşildi; yalnız gerçek gezinme sırasıyla görüldü.
**Doğrusu:** yalıtım iki yönlü — düzenleyici açıkken kabuğun stil düğümüne `media="not all"` (kopyası da kapalı gelir), çıkınca kabuk geri açılır ve **düzenleyicinin** stil düğümü kapanır (her biri işaret kuralıyla bulunur); eski sayfa koşuluyla hesaplanmış stilleri ve gezinme sırasını (aç → çık → başka sayfa → yeniden aç) gerçek tarayıcıda ölç.

## §44 — happy-dom `step`/`min`'li sayı alanında geçerli değeri reddeder, form gönderilmez
**Belirti:** testte forma tutar yazılıp gönder düğmesine basılır; hiçbir istek gitmez, hata da yok. Olumlu iddia ("istek gitti") kırmızı; olumsuz iddia ("istek gitmez") **yalancı yeşil**.
**Sebep:** happy-dom adım geçerliliğini kayan noktayla hesaplar: `step="0.01"` + `min="0.01"` iken (59 − 0,01) / 0,01 tam sayı çıkmaz → alan geçersiz → `submit` olayı hiç oluşmaz. Gerçek tarayıcı bunu ondalık hesapla geçerli sayar.
**Ölçüm (2026-11-03, `m1a-fiyat-katalogu.md`):** aynı test `step="0.01"` ile 3/4 kırmızı (istek listesi yalnız GET), `step="any"` ile istek gitti.
**Doğrusu:** ondalık sınırını sunucuda doğrula, alanda `step="any"`; "gönderilmez" testinin önce gönderilebilen bir kontrol grubunu ölçtüğünü gör.

## §45 — Çatı değişince bağlama kökü (`<div id="…">`) flex zincirini sessizce koparır
**Belirti:** telefonda alta yapışması gereken düğme içeriğin hemen altında durur, ekranın altı boş; hata yok, testler yeşil, düzen "zevk meselesi" gibi görünür.
**Sebep:** tam yükseklik `main`'den adımlara flex zinciriyle iner (`flex: 1`). Astro'nun `astro-island`'ı `display: contents`'ti (kutu üretmez); Vite + Preact geçişinde yerine konan düz `<div id="huni">` blok kutu üretir, zincir orada kopar.
**Ölçüm (2026-11-03, `v1-tasarim-dongusu.md`):** ekran ölçümü 16 adımın çoğunda altta %33–58 boşluk; tarayıcıda hesaplanmış stil zinciri `MAIN(flex) → DIV#huni(block) → .adimlar(flex:1)`. `display: contents` sonrası düğme altta (boşluk %4).
**Doğrusu:** çatı/sarmalayıcı değişince yerleşimi gerçek tarayıcıda ölç (ekran_goruntusu → `olcum`); bağlama kökü düzen zincirindeyse `display: contents` ve onu ölçen test.

## §46 — Bir `display` kuralı tarayıcının `[hidden]` kuralını ezer
**Belirti:** `hidden` öznitelikli öğe sayfada görünür; HTML'e bakan test özniteliği bulur, yeşil kalır.
**Sebep:** `[hidden] { display: none }` tarayıcının varsayılan stil sayfasında, en düşük öncelikle; aynı öğeyi seçen herhangi bir yazar kuralı (`.simge img { display: block }`) onu ezer.
**Ölçüm (2026-11-03, `v3a-fluent-simgeler.md`):** tek renk simgede yoklama için gizlenen siyah SVG maskenin üstünde çizildi — simgeler ana renk yerine siyah (ekran görüntüsünde görüldü).
**Doğrusu:** `display` veren kuralın yanına `…[hidden] { display: none }`; görünürlüğü öznitelikten değil çizimden ölç (ekran görüntüsü / hesaplanmış stil).

## §47 — Testin sahte verisi denetlenen kodla aynı `memory_limit`'i paylaşır
**Belirti:** test yerelde yeşil, CI'da `Allowed memory size … exhausted` ile süreç ölür; kod değişmedi.
**Sebep:** sahte (ör. büyük görsel üreten HTTP sahtesi) ve denetlenen kod (aynı görseli çözen) aynı PHP sürecinde; ikisi birlikte sınıra yakın. Yerelde aynı 128M ile geçmesi şanstı (sıra/çöp toplama).
**Ölçüm (2026-11-03, `v3b-stok-fotograf.md`):** 1880×2820 sahte JPEG + gerçek çözme → CI'da tam süitte 128 MB aşıldı (test tek başına 78,7 MB — birikmiş süit belleğiyle aştı); 1130×1700 (küçültmeyi yine ölçer) ile 9 testlik sınıf 69 MB.
**Doğrusu:** sahte veriyi davranışı ölçen en küçük boyda tut, bir kez üret; yerel yeşili "sınıra ne kadar yakın" sorusuyla oku (`Memory:` satırı).

## §48 — Satır içi YAML'da tırnaksız virgül JS üreticisinde metni sessizce keser
**Belirti:** sözleşmeden üretilen TypeScript tipleri hatasız üretilir, tip denetimi yeşil; ama alanın açıklaması yarıda ("Ana birimde"). PHP tarafındaki sözleşme testleri ise `Malformed inline YAML string` ile düşer.
**Sebep:** `tutar: { type: number, description: Ana birimde, planın para biriminde }` — akış eşlemesinde virgül ayırıcıdır. JS ayrıştırıcısı (openapi-typescript) metni ilk virgülde bitirip kalanını değersiz bir anahtar sayar; Symfony YAML reddeder.
**Ölçüm (2026-11-04, `m1b-teklifler.md`):** `npm run uret` EXIT=0, üretilen açıklama `Ana birimde`; aynı dosya PHPUnit'te 10 testte ParseException.
**Doğrusu:** satır içi eşlemede virgül, iki nokta ya da `#` içeren metni çift tırnağa al; sözleşme değişince yalnız tip üretimine değil PHP sözleşme testlerine de bak (`bin/kontrol` ikisini koşar).
**Yeniden ölçüldü (2026-11-10, M9b1):** `description: Kaydedilmişse kaydedilen, değilse taslak` → üretilen TS'de yalnız "Kaydedilmişse kaydedilen" (uret EXIT=0); PHP sözleşme testi `Malformed inline YAML` ile düştü. Tırnaklayınca ikisi de düzeldi.

## §49 — Tek tırnaklı satır içi betikte yorumdaki kesme işareti tırnağı kapatır
**Belirti:** `deploy/dagit.sh` hiç çalışmaz (`syntax error near unexpected token`); hata satırı bir **yorum**. Daha kötüsü: kesme işareti sayısı çiftse `bash -n` geçer, betik çalışır, ama gömülü kodun bir parçası kabuk komutu olur — hata vermeden yanlış kod koşar.
**Sebep:** `python3 -c '…'` içindeki her şey kabuk için tek tırnaklı dizgedir; içerdeki `#` Python'un yorumudur, kabuğun değil. "`$'ı`" ya da "bash'te" gibi bir kesme işareti dizgeyi bitirir.
**Ölçüm (2026-11-04, `m1c-cok-para-birimi.md`):** `bash -n` macOS bash 3.2 ve bash 5.2'de 36. satırda hata (2026-10-20'den beri, `028f150`). Düzeltirken iki kesme işareti daha eklendi → `bash -n` 0 döndü; gömülü blok çıkarılıp `compile` edilince eksik olduğu görüldü.
**Doğrusu:** gömülü kodda kesme işareti kullanma (ya da `<<'EOF'` heredoc'a taşı); ölçüt `bash -n` değil — gömülü bloğu çıkar ve kendi diliyle derle/çalıştır.

## §50 — Para birimi verilmeyen çok para birimli Stripe fiyatı ödemede kendiliğinden yerelleşir
**Belirti:** paywall ana birimi gösterir (€9,99), ödeme sayfası başka birimde açılır (TRY 349,99); hata yok, testler yeşil.
**Sebep:** fiyatta `currency_options` varsa ve oturumda `currency` verilmezse Checkout müşterinin konumunu kendisi tahmin edip o para birimini seçer (belgede "You didn't specify a currency" koşulu). Bizim IP tahminimiz bilinmiyor/desteklenmiyor dediğinde Stripe'ınki başka bir şey diyebilir. Testte stripe-mock bunu hiç yapmaz.
**Ölçüm (2026-11-04, `m2d-gomulu-odeme.md`):** yerel IP (ülke yok) + test e-postası `test+location_TR@…` → oturum `try 34999`; paywall €9,99. `currency` zorlanınca aynı e-postayla `eur 999`.
**Doğrusu:** paywall'da gösterilen para birimini ödeme oturumunda HER ZAMAN `currency` ile zorla (bilinmeyen ülkede ana birim); "Stripe ana birimi açar" diye varsayma. Stripe'ın `+location_XX` test e-postasıyla gerçek API'de ölç.

## §51 — İstemci yönlendirmeli panelde yeni sayfa menüden açılır, doğrudan adresle 404
**Belirti:** panelde menüden "Deneyler"e tıklanınca sayfa çizilir; aynı adres yenilenince ya da yeni sekmede açılınca 404. Bileşen testi (bellek yönlendiricisi) ve API testleri yeşil.
**Sebep:** React yönlendiricisi istemcide `/panel/deneyler`'i tanır, ama ilk HTML'i sunan Symfony rotası (`PanelController::uygulama` üzerindeki `#[Route]` listesi) her sayfa için ayrıca yazılmalı. Bellek yönlendiricisiyle koşan bileşen testi sunucuya hiç gitmez.
**Ölçüm (2026-11-09, `m4a-deneyler.md`):** `curl localhost:8081/panel/deneyler` → 404, komşu `/panel/planlar` → 302 (girişe). Rota eklendi; kırma K24 (rota yok) 🔴.
**Doğrusu:** panele sayfa eklerken sunucu rotasını da ekle ve doğrudan adresi ölçen bir test yaz (girişsiz → `/panel/giris`, girişli → 200 + `#panel-uygulama`).

## §52 — Stripe kartlı denemeyi `payment_status = paid` sayar (tutar 0)
**Belirti:** "ödedi" sayan rapor ve dönüşüm olayı denemeyle abone olanı da ödemiş gösterir; reklam ağına değeri 0 olan `Purchase` gider. Hata yok, testler yeşil (sahte olaylar denemede `no_payment_required` varsayıyordu).
**Sebep:** kart toplanan (kartlı deneme) Checkout aboneliğinde Stripe `checkout.session.completed` olayında `payment_status = paid`, `amount_total = 0` döndürür. `no_payment_required` yalnız kart toplanmayan oturumda beklenir. "paid" para çekildi demek değildir.
**Ölçüm (2026-11-09, `m4b-deney-sonuclari.md`):** Stripe API'de tamamlanmış hiç denemeli oturum yoktu (6'sı denemesiz, `paid`, 999); yerel huniden 7 günlük denemeli oturum test kartıyla tamamlandı → `status=complete payment_status=paid amount_total=0`; gerçek olay yerel webhook'a verildi.
**Doğrusu:** "ödedi"yi oturumun tutarından (`amount_total > 0`) say; Stripe davranışını sahte olay biçiminden değil gerçek test oturumundan oku. Belgeden hatırlanan alan değeri de ölçülmemiş iddiadır.
**Uygulandı (2026-11-09, `c118-deneme-rapor-reklam.md`):** Rapor ve deneyler "ödedi"yi tutardan sayar, deneme ayrı; reklam ağlarına tutar 0'da `StartTrial` (sunucu + piksel aynı ad — ağ ad + event ID ile tekilleştirir).

## §53 — Sıra numarasıyla (`id >`) okunan akış bitmemiş işlemin olayını atlar
**Belirti:** olay akışını `id > son_imlec` ile sayfalayan okuyucu bazı olayları hiç görmez; hata yok, sayfalama testleri yeşil (testte işlemler tek tek ve sırayla biter).
**Sebep:** `id` (IDENTITY/sequence) ekleme anında, satırın görünmesi ise commit anında. Önce başlayıp geç biten işlemin küçük id'li olayı, okuyucunun imleci onu geçtikten SONRA görünür — imleç geri gelmez.
**Ölçüm (2026-11-09, `m5a-olay-deposu.md`):** ikinci bağlantıda açık işlemde olay eklendi, ilk bağlantıda arkasına bir olay daha eklenip bitti; bekçisiz akış büyük id'yi verdi (kırma E15).
**⚠️ İlk "doğrusu" da yanlıştı (M7a, 2026-11-09, `m7a-giden-webhook.md`):** `id` imleci + `islem < xmin` bekçisi yetmez. İşlem kimliği DAHA ESKİ olan A, olayını yarım T'den SONRA ekleyip T'den önce biterse A'nın işlemi xmin'den küçüktür, bekçiyi geçer, id'si T'ninkinden büyüktür; imleç A'ya gelir, T bitince küçük id'li olayı kalıcı atlanır. Ölçüldü: test A'yı iki kez, T'yi tesadüfen okudu (son sayfada imleç yoktu, Ç119); düzeltmeden sonra kırma G1 / G1b / G5 ile ölçüldü.
**Doğrusu:** satırı yazan işlemin kimliğini sakla (`xid8 DEFAULT pg_current_xact_id()`); yalnız `islem < pg_snapshot_xmin(pg_current_snapshot())` olanları **`(islem, id)` sırasında** oku ve imleci de `(islem, id)` tut. xmin'den küçük işlemlerin hepsi bitmiştir; sonradan görünecek her satırın işlemi ≥ xmin, yani imlecin ötesindedir. Bedeli: uzun açık bir işlem akışı geciktirir (kaybettirmez). Zaman damgasıyla sıralamak da çözmez (`now()` işlem başlangıcı). Testte iki sıra da kurulur: yarım işlem önce başlar / eski işlem sonra ekleyip önce biter.

## §54 — Yeni Stripe API'de faturanın `subscription` alanı yok; okuyan kod sessizce "aboneliksiz" der
**Belirti:** `invoice.paid` işlenir, 200 döner, ama hiçbir fatura aboneliğe / uygulamaya bağlanmaz — kohort geliri 0. Belgelerden ve eski örneklerden hatırlanan `invoice.subscription` ve `invoice.metadata.app` boş gelir; hata yok.
**Sebep:** API 2026-09-30'da (hesabın sürümü) abonelik ve aboneliğin metadata'sı `invoice.parent.subscription_details` altında; faturanın kendi `metadata`'sı boş. Webhook gövdesi uç noktanın API sürümüyle gelir.
**Ölçüm (2026-11-09, `m5b-faturalar.md`):** test hesabındaki gerçek 3 fatura: üst düzeyde `subscription` yok, `metadata: []`, `parent.subscription_details.{subscription, metadata.app}` dolu. Gerçek `invoice.paid` olayları imzalı olarak yerel webhook'a verildi → 3 fatura `keepchat` ile yazıldı.
**Doğrusu:** alanı belgeden değil gerçek nesneden oku; üretim webhook uç noktasının API sürümü hesabınkiyle aynı olsun (Ç120). Kırma F2: üst metadata'dan okumak 7 testi düşürdü.
**Aynı aile (M6a, 2026-11-09, `m6a-iptal-iade.md`):** dönem sonu (`current_period_end`) aboneliğin kendisinde değil kaleminde (`items.data[].current_period_end`). Faturada `payment_intent` / `charge`, ödemede de `invoice` yok; bağ ayrı `InvoicePayment` nesnesinde (`invoice_payment.paid`). İade ödemeye gelir, faturayı bu bağ olmadan bulamazsın.

## §55 — `x IN (…)` NULL'da NULL döner; boolean ifade eşleşme anahtarı olunca satır sessizce düşer
**Belirti:** organik (ortamsız, tıklamasız) grubun geliri boş; ziyaret sayısı doğru, hata yok.
**Sebep:** `REKLAM = (fbclid IS NOT NULL OR ttclid IS NOT NULL OR lower(utm_medium) IN (…))` — ortam NULL ve tıklama yoksa sonuç `false` değil **NULL**. Gelir alt sorgusu grubu `v2.reklam = v.reklam` ile eşliyordu; `NULL = NULL` NULL → hiçbir satır eşleşmedi. `GROUP BY` NULL'ları bir grupta topladığı ve PHP `true === null` false verdiği için sayımlar ve çıktı doğru göründü.
**Ölçüm (2026-11-09, `m5c-raporlar.md`):** organik gelirli ziyaret teste eklenince beklenen EUR 4,50 yerine `[]`.
**Doğrusu:** eşleşmede `IS NOT DISTINCT FROM` ya da `coalesce(…, false)`; daha iyisi eşleşmeyi tek sorgunun `GROUP BY`'ına bırakmak (M5c bunu yaptı — iki sorgu aynı CTE'den, anahtar PHP'de). Test verisine her NULL kolu için bir satır koy.

## §56 — PHP 8.4'te `round()` ön yuvarlama yapmaz: ondalık metni `(float) × 100` ile çevirmek yarım sınırında 1 kuruş eksik verir
**Belirti:** "1.005" EUR harcama 100 küçük birim yazılır (beklenen 101); hata yok, 2 basamaklı tutarlarda her şey doğru görünür.
**Sebep:** `1.005 * 100 = 100.49999…`. PHP 8.4'ten önce `round()` 15 anlamlı basamağa ön yuvarlayıp 101 veriyordu; 8.4 bunu kaldırdı. Ezberdeki "PHP round bunu düzeltir" bilgisi artık yanlış.
**Ölçüm (2026-11-09, `m5d-meta-harcama.md`):** PHP 8.4.26 `round(1.005*100)` → 100. Önce "ön yuvarlama 101 verir" diye yazdım ve özel çeviriciyi gereksiz sanıp kaldırdım (kırma D4 yeşil kalmıştı). Ama test 1.005'i servisten hiç geçirmiyordu. Servis düzeyi test eklenince 100 çıktı, çevirici geri geldi.
**Doğrusu:** dış sistemin ondalık metnini metin olarak çevir (`ParaBirimi::metindenKucuk`). Kayan noktalı yol yalnız 2 basamaklı panel girdisinde güvenli. Bir savunmayı "gereksiz" diye kaldırmadan önce onu ölçen testin o yoldan geçtiğini doğrula.

## §57 — Postmark köprüsü pasif alıcıda hata atmaz: e-posta gitmez, `send()` başarılı döner
**Belirti:** giriş kodu / bağlantısı "gönderildi", uç 202, `giris_epostasi_at` dolu — kişiye hiçbir şey gelmez.
**Sebep:** Postmark bastırılmış (hard bounce, şikâyet, abonelikten çıkma) adrese HTTP 422 + `ErrorCode` 406 döner;
Symfony `PostmarkApiTransport` olay dağıtıcısı varsa (çatıda hep var) bunu `PostmarkDeliveryEvent` olarak yayar ve
**dönüşü başarılı** sayar.
**Ölçüm (2026-11-10, `m8a1-postmark.md`):** köprü kaynağı (`CODE_INACTIVE_RECIPIENT`) + MockHttpClient ile 422/406
yanıtında `send()` atmadı; dinleyici kaldırılınca kayıt da yok (kırma K1).
**Doğrusu:** olayı dinle, adresi bastırma listesine yaz; kuyruktan göndermeden önce listeye bak.

