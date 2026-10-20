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
