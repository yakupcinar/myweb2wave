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
**hiçbir şey yapmıyor** (KeepChat'te kırık kod beş deneme boyunca yerinde
kaldı), izlenen dosyada o oturumun commit'lenmemiş kodunu da geri alıyor.

**2 · Boz — ve DEĞİŞİKLİĞİN UYGULANDIĞINI doğrula**

```bash
grep -n "<beklenen yeni hâl>" <dosya>
```

⚠️ Bu adım atlanamaz. Aynı kalıp birden çok yerde geçiyorsa ilk eşleşmeyi
değiştirmek **yanlış yeri kırar** ve test "geçer" — yani deneme, ölçtüğü
şey hakkında yanlış güven verir. KeepChat'te iki kez oldu. Yama tutmadıysa
sonuç "yeşil" değil **KOŞULMADI**'dır; derleme hatasıyla gelen kırmızı da
sahtedir (tuzak §8).

**3 · Hedefli testi koştur** — tüm süiti değil, o kararı ölçen dosyayı.
`passed` mı `skipped` mi oku.

**4 · Geri al ve GERİ ALMANIN uygulandığını doğrula**

```bash
cp /tmp/x.bak <dosya> && grep -c "<kırık hâl>" <dosya>   # 0 olmalı
```

## Deneme tutmadıysa: TESTİ SUÇLA, KODU DEĞİL

Denemenin hiçbir testi düşürmemesi *"kod fazladan korunuyor"* demek
değil; KeepChat'te **her seferinde** iddia başka bir şeyi ölçüyordu
(27 denemenin 6'sı).

| Belirti | Gerçek sebep | Çözüm |
|---|---|---|
| İddia kaynak dosyayı okuyor, deneme tutmuyor | Aranan metin **yorumda** da geçiyor | Yorumları ayıkla |
| Aynısı, ama sayfa HTML'i | Metin gömülü `<script>` bloğunda da geçiyor | `<script>` bloklarını ayıkla |
| İki formül aynı sonucu veriyor | **Fixture ikisini ayırt edemiyor** (bütün varyantlar aynı fiyatta) | Fixture'ı farklılaştır |
| Koruma kaldırıldı, test yeşil | Koruyan şey **başka bir kontrol**dü | Gerçek senaryoyu kur |
| Olumsuz iddia hiç düşmüyor | İddia fonksiyonunun imzası yanlış okunmuş: "mesaj" sanılan argüman ikinci aranan değer — biri eksikse geçer | İmzayı belgeden oku; tek değerle iddia et |
| İddia her koşulda aynı sonucu veriyor | İşleç önceliği: birleştirme karşılaştırmadan **önce** bağlanıyor | Koşulu parantez içine al |
| Ayar doğru ama davranış ölçülmemiş | Test nesneyi **elle kuruyor**; uygulamanın kendi kurulum yolunu kullanmıyor | Nesneyi uygulamanın yolundan al |
| İzin/dosya kontrolü hiç düşmüyor | "Çalıştırılabilir mi" sorusu **root** olarak her zaman evet der | İzin bitlerini doğrudan oku |

⚠️ Testi düzelttikten sonra **denemeyi tekrarla**. Düştüğünü görmeden
"tamam" deme.

## Kaydet

Her deneme bloğun kaydına (`docs/decisions/<konu>.md`) yazılır; tutmayan
deneme sebebiyle: hangi deneme, neden tutmadı, iddia neyi ölçüyormuş. Yeni
bir kalıpsa bu tabloya da satır olarak eklenir.
