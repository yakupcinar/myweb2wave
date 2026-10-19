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
