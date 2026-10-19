---
name: blok
description: Bir geliştirme bloğunun baştan sona akışı — ölç, karar ver, uygula, test yaz, kır, gerçek istekle doğrula, kontrol et, belgele, commit et. KeepChat'te ~36 blokta oturan ritüelin yazılı hâli. Yeni bir iş parçasına başlarken kullan.
allowed-tools: Bash, Read, Edit, Write
---

# Blok akışı

Dokuz adım. Sıra önemli: her adım öncekinin çıktısını kullanıyor.

## 1 · ÖLÇ — karar vermeden önce

Mevcut durumu **say**. Varsayımla başlayan blok yanlış problemi çözüyor.

KeepChat'ten örnekler: *"sayfa yavaş"* sanılıyordu, ölçüm 57 KB / 0,5 sn
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

Süitin göremediği şeyler var; KeepChat'te **defalarca** yaşandı:

| Testin göremediği | Neden |
|---|---|
| Eksik başlıklı istemcinin 500 alması | Test istemcisi başlığı otomatik ekliyor |
| Formda eksik alan | Testler **tam veri** gönderiyor |
| Ekranda ham çeviri anahtarı | Veriyi ölçen test doğru bulur, ekranı görmez |

```bash
curl "<uygulamanın-adresi>/…"        # gerçek HTTP
```

Ekranda görmek gerekiyorsa tarayıcı aracıyla yürü.

## 7 · KONTROL — `/kontrol`

Biçim + statik analiz + tam süit. Uzun sürdüğü için `sinayici` ajanına
devredilebilir.

## 8 · BELGELE — `/belge`

Yerler CLAUDE.md §6'da; özetle:

| Ne | Nereye |
|---|---|
| Karar + gerekçe + kırma denemeleri + ölçüm | `docs/decisions/<konu>.md` |
| Durum satırı | `PLAN.md` §0-§1 |
| Hata vermeden yanlış sonuç üreten şey | `docs/tuzaklar.md` + CLAUDE.md §4 tek satır |
| Fark ettim ama karar vermedim | `docs/decisions/acik-uclar.md` |

## 9 · COMMIT + CI

Mesaj kararı ve ölçümü anlatır, değişikliği listelemez. İmza satırı yok.

```bash
git push && curl -s ".../commits/main/check-runs"
```

⚠️ **Otorite CI.** Yerel yeşil ≠ CI yeşil.

## Durdurma koşulu

Blok "testler yeşil" olunca değil, **kırma denemeleri kırmızı olunca**
biter. Yeşil bir süit, ölçtüğünü kanıtlamamış olabilir.
