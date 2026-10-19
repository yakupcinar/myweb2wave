---
name: blok
description: Bir geliştirme bloğunun baştan sona akışı — ölç, karar ver, uygula, test yaz, kır, gerçek istekle doğrula, kontrol et, belgele, commit et. 36 blokta uygulanan ritüelin yazılı hâli. Yeni bir iş parçasına başlarken kullan.
allowed-tools: Bash, Read, Edit, Write
---

# Blok akışı

Dokuz adım. Sıra önemli: her adım öncekinin çıktısını kullanıyor.

## 1 · ÖLÇ — karar vermeden önce

Mevcut durumu **say**. Varsayımla başlayan blok yanlış problemi çözüyor.

Bu oturumdan örnekler: *"sayfa yavaş"* sanılıyordu, ölçüm 57 KB / 0,5 sn
gösterdi — asıl kusur listenin 24'te sessizce kesilmesiydi. *"Günlük
gidiyor"* sanılıyordu, sayaç **0** gösterdi.

## 2 · KARAR VER — gerekçesiyle

Her karar bir **alternatifi eler**. Neyi elediğini ve niçin elediğini yaz;
altı ay sonra o alternatifi yeniden öneren kişi sen olacaksın.

## 3 · UYGULA — cerrahi

Yalnızca ilgili satırlar. "İyileştirme" bahanesiyle alakasız kodu ya da
biçimi kurcalama; her değişiklik isteğe doğrudan izlenebilir olmalı.

⚠️ **Bir kuralı tek yola yazmak yetmez — ailenin tamamına yaz.** `ekle()`
düzeltilip `guncelle()` unutulduğunda aynı ham hata oradan çıkmaya devam
etti.

## 4 · TEST YAZ — kararı ölçen

Test **kararı** ölçer, kodu değil. Yorumunda kararın gerekçesi durur.

## 5 · KIR — `/kirma`

Her kararı tek tek boz, testin düştüğünü gör, `cp` ile geri al.
Tutmayan deneme varsa **testi suçla**, ayrıntı `/kirma` skill'inde.

## 6 · GERÇEK İSTEKLE DOĞRULA

Süitin göremediği şeyler var ve bu projede **defalarca** yaşandı:

| Testin göremediği | Neden |
|---|---|
| `Accept` başlığı olmayan istemcinin 500 alması | `postJson` başlığı otomatik ekliyor — 425 testin hiçbiri yakalamadı |
| CSRF hatası | Testler `postJson` kullanıyor |
| Formda eksik alan | Testler `ornekAdres()` ile **tam veri** gönderiyor |
| Ekranda ham çeviri anahtarı | Prop'ları ölçen test doğru bulur |

```bash
curl -k "https://<uygulamanın-adresi>/…"        # gerçek HTTP
# tarayıcıda görmek gerekiyorsa erişilebilir bir adres aç
```

⚠️ Tarayıcı aracı `.localhost` adresine **ulaşamıyor**; tünel şart. İş
bitince kapat.

## 7 · KONTROL — `/kontrol`

Biçim + statik analiz + tam süit. `make kontrol` bu işi eksik yapıyor.
Uzun sürdüğü için `sinayici` ajanına devredilebilir.

## 8 · BELGELE — bilgi sohbette değil depoda durur

| Ne | Nereye |
|---|---|
| Karar + gerekçe + kırma denemeleri | `PLAN.md` |
| Blok özeti | `docs/summary.md` |
| Hata vermeden yanlış sonuç üreten şey | `CLAUDE.md` ya da ilgili `.claude/rules/*.md` |
| Kullanıcıya bakan kusur/çözüm | `README.md` |

⚠️ Tuzak eklersen `TuzakSayimiTest`'teki sayıyı da artır — **bilerek**.
Düşürmek bir tuzağın sessizce kaybolması demek.

⚠️ **Süit koşarken bu dosyalara dokunma**: testler onları okuyor.

## 9 · COMMIT + CI

Mesaj kararı ve ölçümü anlatır, değişikliği listelemez. İmza satırı yok.

```bash
git push && curl -s ".../commits/main/check-runs"
```

⚠️ **Otorite CI.** Yerel yeşil ≠ CI yeşil; bu bir kez biçimlendiricide, bir kez
tuzak sayımı yüzünden yaşandı.

## Durdurma koşulu

Blok "testler yeşil" olunca değil, **kırma denemeleri kırmızı olunca**
biter. Yeşil bir süit, ölçtüğünü kanıtlamamış olabilir.
