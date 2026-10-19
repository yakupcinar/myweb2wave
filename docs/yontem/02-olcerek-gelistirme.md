# 2 · Ölçerek geliştirme

İlke: **"çalışıyor" bir iddia, ölçüm değil.** Bir iş, testler yeşil
olduğunda değil, testlerin gerçekten ölçtüğü kanıtlandığında biter.

## Blok: bir iş parçasının dokuz adımı (`/blok`)

1. **Ölç** — karar vermeden önce mevcut durumu say (disk, sürümler, portlar,
   var olan veri).
2. **Karar ver** — ne seçildi, neyi eledi, neden (bölüm 3).
3. **Uygula** — cerrahi; yalnız istenen satırlar. Bir kural tek yola değil
   ailenin tamamına yazılır.
4. **Test yaz** — kodu değil **kararı** ölçen test; beklenen değer koddan
   değil bağımsız hesaplanır (aşağıda tuzak §3).
5. **Kır** — her kararı tek tek boz, testin düştüğünü gör, geri al.
6. **Gerçek istekle doğrula** — süitin göremediğini gör (bölüm 6).
7. **Kontrol** — `bin/kontrol`.
8. **Belgele** — karar kaydı, PLAN, tuzak, açık uç, bu kitap.
9. **Commit + CI** — CI kırmızıysa iş bitmemiştir.

## Kırma denemesi (`/kirma`) — testin ölçtüğünü kanıtlama

Her denemede üç şey ölçülür ve yazılır:

| Soru | Neden |
|---|---|
| **Bozma uygulandı mı?** (`grep -F`, "tam bir kez geçiyor mu") | Uygulanmayan deneme "yeşil" değil **KOŞULMADI**'dır. Bu projede 5+ kez yaşandı: desen bulunamadı, kabuk tırnaklaması bozuldu, komut geçersiz seçenek aldı. |
| **Doğru sebepten mi düştü?** | Sözdizimi hatasıyla düşen deneme sahtedir: 11 test hata, 0 doğrulama koştu (Stripe S8 ilk deneme). |
| **Geri alma uygulandı mı?** | DB kısıtını geri koyma ilk denemede sessizce başarısız oldu (yinelenen satır vardı); sayım gösterdi (S10). |

**Deneme tutmazsa (hiçbir test düşmezse) testi suçla, kodu değil.** Örnek:
"ödeme olayı durum yazmaz" kararını bozdum, test düşmedi; çünkü test
olayları "iyi" sırada gönderiyordu ve tehlikeli yol hiç koşmuyordu. Eksik
test yazıldı, deneme tekrarlandı, düştü — ve bir saat sonra gerçek
Stripe olayları tam o "kötü" sırayla geldi (`stripe-odeme.md`, `huni-paywall.md` E1).

Bu projedeki sayılar: iskelet 7/7, Stripe çekirdeği 10/10, huni 8/8,
uygulama boyutu 7/7, CI 1/1, kontrol günlüğü 1/1 kırmızı.

## Çıkış kodu borusuz alınır

`komut | tail` → `$?` `tail`'indir; kırmızı süit yeşil görünür.
Doğrusu: `komut > log 2>&1; echo EXIT=$?`. `bin/kontrol` her adımı böyle
koşar; kırmızı adımın günlüğünün **son** 40 satırını basar (hata sonda —
ilk 40 satır CI'da asıl hatayı gizlemişti).

## Tuzak kataloğu: hata vermeden YANLIŞ sonuç üretenler

`docs/tuzaklar.md` (tam metin) + CLAUDE.md §4 (tek satır). Ölçüt: hata
mesajı **yoktu** ama sonuç yanlıştı. Bu projede doğanlar:

| § | Kısaca |
|---|---|
| 16 | macOS araçları farklı ve sessiz (BSD `grep` düzenli ifadeyle sabit metni bulamadı) |
| 19 | PHPUnit `<server>` gerçek ortam değişkenine yenildi; beklenti düz sabit olduğu için yakalandı |
| 20 | renk kodlu çıktıda `grep '^OK'` satırı kaybetti — atlanan test sayısı görünmez oldu |
| 21 | push olmadıysa CI'da ESKİ koşunun sonucu yeni sanıldı |
| 22 | `docker compose run -d` 0 döndü, içindeki süreç ölmüştü |
| 23 | `curl -s` köşeli parantezli adreste sessizce boş döndü |

Ders: her biri bir **yanlış güven** anıydı; yakalayan şey hep bağımsız
bir ikinci ölçümdü (sayım, düz sabit beklenti, "uygulandı mı" satırı).

## Kendi hatalarını da yaz

Karar kayıtları ajanın kendi yanlışlarını da tutar: yalan yorum ("hata
günlüğe yazılıyor" — yazılmıyordu), yanlış sürüm filtresi (bakımlı
kütüphaneyi bakımsız sandı), tutmayan tahmin (E5: Stripe ilk ödemede
`incomplete` üretmedi). Gizlenen yanlış bir sonraki oturumda tekrarlanır.
