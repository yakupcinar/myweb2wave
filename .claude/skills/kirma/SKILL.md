---
name: kirma
description: Kırma denemesi — bir testin gerçekten ölçtüğünü kanıtlama ritüeli. Testler yeşil olduğu için değil, ölçtüklerini kanıtladıkları için güvenilir. Bir blok bitmeden önce her kararı tek tek bozup testin düştüğünü gör. Deneme tutmazsa testi suçla, kodu değil.
allowed-tools: Bash, Read, Edit
---

# Kırma denemesi

Testler yeşil olduğu için değil, **ölçtüklerini kanıtladıkları için**
güvenilir. Bloğun her kararı tek tek bozulur, testin düştüğü görülür,
geri alınır.

## Sıra

**1 · Yedekle**

```bash
cp <dosya> /tmp/x.bak
```

⚠️ `git checkout` KULLANMA — hook zaten engelliyor. İzlenmeyen dosyada
**hiçbir şey yapmıyor** (kırık kod beş deneme boyunca yerinde kaldı),
izlenen dosyada o oturumun commit'lenmemiş kodunu da geri alıyor.

**2 · Boz — ve DEĞİŞİKLİĞİN UYGULANDIĞINI doğrula**

```bash
grep -n "<beklenen yeni hâl>" <dosya>
```

⚠️ Bu adım atlanamaz. Aynı kalıp birden çok yerde geçiyorsa `replace(…, 1)`
**yanlış yeri kırar** ve test "geçer" — yani deneme, ölçtüğü şey hakkında
yanlış güven verir. Bu oturumda iki kez oldu.

**3 · Hedefli testi koştur** — tüm süiti değil, o kararı ölçen dosyayı.

**4 · Geri al ve GERİ ALMANIN uygulandığını doğrula**

```bash
cp /tmp/x.bak <dosya> && grep -c "<kırık hâl>" <dosya>   # 0 olmalı
```

## Deneme tutmadıysa: TESTİ SUÇLA, KODU DEĞİL

Denemenin hiçbir testi düşürmemesi *"kod fazladan korunuyor"* demek
değil; bu projede **her seferinde** iddia başka bir şeyi ölçüyordu.
Bu oturumdaki 27 denemenin 6'sı böyleydi.

| Belirti | Gerçek sebep | Çözüm |
|---|---|---|
| İddia kaynak dosyayı okuyor, deneme tutmuyor | Aranan metin **yorumda** da geçiyor; yorum kuralı anlatıyor | Yorumları ayıkla |
| Aynısı, ama sayfa HTML'i | Metin `<script>` bloğunda; betik kancaları adı zaten yazıyor | `<script>` bloklarını ayıkla |
| İki formül aynı sonucu veriyor | **Fixture ikisini ayırt edemiyor** (bütün varyantlar aynı fiyatta) | Fixture'ı farklılaştır |
| Koruma kaldırıldı, test yeşil | Koruyan şey **başka bir kontrol**du (`status`, `$hidden`) | Gerçek senaryoyu kur |
| Olumsuz iddia hiç düşmüyor | `->not->toContain(a, b)` **çok argümanlı**: biri eksikse geçer. İkinci argüman mesaj DEĞİL, ikinci aranan değer | Tek argüman; mesaj gerekiyorsa değeri dizgeye katıp `toBe()` |
| İddia her koşulda aynı sonucu veriyor | Birleştirme karşılaştırmadan **önce** bağlanıyor: `$a.' n: '.count($x) > 0 ? 'var' : 'YOK'` her zaman `'var'` | Ternary'yi parantez içine al |
| Ayar doğru ama davranış ölçülmemiş | Test nesneyi **elle kuruyor**; uygulamanın çözdüğü yolu kullanmıyor (`Log::build()` `tap`'i uygulamıyor) | `Log::channel(...)` gibi uygulamanın kendi yolundan al |
| İzin/dosya kontrolü hiç düşmüyor | `is_executable()` konteynerde **root** olarak yalan söylüyor | `fileperms($yol) & 0111` |

⚠️ Testi düzelttikten sonra **denemeyi tekrarla**. Düştüğünü görmeden
"tamam" deme.

## Kaydet

Tutmayan her deneme `PLAN.md`'ye yazılır: hangi deneme, neden tutmadı,
iddia neyi ölçüyormuş. Bu dosyadaki tablonun satırları böyle doğdu.
