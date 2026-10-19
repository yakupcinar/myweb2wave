---
name: belge
description: Bir blok bitmeden önce bilgiyi depoya yazma ritüeli — blok kaydı (karar + gerekçe + kırma denemeleri + ölçüm) docs/decisions/'a, PLAN.md durumu, yeni tuzak varsa docs/tuzaklar.md + CLAUDE.md §4, açık uçlar, kapanış grep denetimi, sonra commit mesajı. Oturum kapandığında kaybolan hiçbir şey kalmasın diye. Commit'ten hemen önce kullan.
---

# Bilgiyi depoya yaz

Bu proje **tek bir sohbete bağlı değil.** Devralan kişi ya da ajan yalnızca
depodan tam bağlamı kurabilmeli. Oturum kapandığında kaybolan bir şey varsa
blok bitmemiştir. Yerlerin tablosu: CLAUDE.md §6.

## Sıra

**1 · Blok kaydı → `docs/decisions/<konu>.md`.** Şu dördü birden:
- **Ne yapıldı** — bir paragraf, ekranda görünen sonuçla.
- **Neden böyle** — reddedilen seçenek ve reddedilme sebebi. Karar
  gerekçesizse gelecekte biri onu "gereksiz" diye kaldırır.
- **Kırma denemeleri tablosu** — deneme · sonuç (kaç test düştü).
  Tutmayan deneme varsa **onu da yaz**, sebebiyle: tuzakların çoğu
  tutmayan denemeden çıkar.
- **Ölçüm** — sayı ver. "Test yeşil" değil, "N test · şu uçta gerçek
  curl 200".

**2 · `PLAN.md`.** §0 durum tablosu ve §1 görevi güncelle; bitmiş işin
metni PLAN'da durmaz, 1. adımdaki dosyaya bağlantı verilir.

**3 · Yeni tuzak var mı?** Ölçüt: *hata vermeden yanlış sonuç* üretti mi?
Ürettiyse tam metin `docs/tuzaklar.md`'ye, **tek satırlık iddia** CLAUDE.md
§4'e — ikisi birden (kural 7).

Tuzak **belirtiyle** yazılır ("500 dönüyor" değil, "sayfa 200 dönüyor ama
düğme hiç kimseye çıkmıyor"), sonra sebep, ölçüm, doğrusu. Denenip
**tutmayan** çözümler de yazılır — yoksa bir sonraki oturum onları
yeniden dener.

**4 · Açık uçlar.** "Fark ettim ama karar vermedim" her cümle
`docs/decisions/acik-uclar.md`'ye bir Ç-no ile.

**4b · Yöntem el kitabı.** Blokta yeni bir yöntem ya da ders çıktıysa ilgili
`docs/yontem/<bölüm>.md`'ye 1–5 satır, `07-yolculuk.md`'ye bloğun tek
satırı. Ölçümü kopyalama, kayda bağlantı ver. Bölüm ~150 satırı geçerse böl.

**5 · Kapanış denetimi (kural 4).** Kapanış mesajındaki her "fark ettim /
karar vermedim / istersen geri alırım" cümlesi için `grep` ile depodaki
karşılığını göster. Bulamadığını **yaz**. Türkçe ararken `grep -i` yok
(tuzak §2).

**6 · Commit mesajı.** Başlık `<blok> — <ne yapıldı>`. Gövde kararı ve
**ölçümü** anlatır; dosya listesi değil. Son satır test sayısı. Ters tırnak
yok (tuzak §9): `git commit -F -` + tırnaksız heredoc.

## Durdurma koşulu

Şu üçü de doğruysa belgeleme bitmiştir:

- Bu oturumda öğrenilen her şey **bir dosyada** yazılı.
- Bir karara katılmayan biri gerekçesini **arayarak bulabilir**.
- `git log` mesajı tek başına okunduğunda ne ölçüldüğü anlaşılıyor.
