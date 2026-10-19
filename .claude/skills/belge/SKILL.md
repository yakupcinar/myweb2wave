---
name: belge
description: Bir blok bitmeden önce bilgiyi depoya yazma ritüeli — PLAN.md kaydı (karar + gerekçe + kırma denemeleri), docs/summary.md özeti, yeni tuzak varsa CLAUDE.md ya da .claude/rules/, sonra commit mesajı. Oturum kapandığında kaybolan hiçbir şey kalmasın diye. Commit'ten hemen önce kullan.
---

# Bilgiyi depoya yaz

Bu proje **tek bir sohbete bağlı değil.** Devralan kişi ya da ajan yalnızca
`PLAN.md` ve `CLAUDE.md` ile tam bağlamı kurabilmeli. Oturum kapandığında
kaybolan bir şey varsa blok bitmemiştir.

## Sıra

**1 · PLAN.md kaydı.** Bloğun kendi başlığı altına, şu dördü birden:
- **Ne yapıldı** — bir paragraf, ekranda görünen sonuçla.
- **Neden böyle** — reddedilen seçenek ve reddedilme sebebi. Karar
  gerekçesizse gelecekte biri onu "gereksiz" diye kaldırır.
- **Kırma denemeleri tablosu** — deneme · sonuç (kaç test düştü).
  Tutmayan deneme varsa **onu da yaz**, sebebiyle: bu projede tuzakların
  çoğu tutmayan denemeden çıktı.
- **Ölçüm** — sayı ver. "Test yeşil" değil, "1079 test · şu uçta gerçek
  curl 200".

Sonra en üstteki yol haritası kutusunu güncelle (`şu an: … BİTTİ`).

**2 · `docs/mimari.md` hâlâ doğru mu?** Blok yeni bir paket, servis, port
ya da akış getirdiyse şema bayatlamıştır. Ölç, tahmin etme:
`go list ./...` çıktısını şemadaki kutularla karşılaştır; `compose.yaml`
ve `Makefile` hedeflerine bak. Şemayı **ve dosyanın başındaki doğrulama
satırını** (tarih + commit) birlikte güncelle — satır güncellenmezse
devralan bayat şemaya taze sanıp güvenir.

**3 · docs/summary.md.** Aynı bloğun kısa hâli. Ayrıntı değil, **devralan
kişinin bir dakikada bağlam kurmasını** sağlayan özet.

**4 · Yeni tuzak var mı?** Ölçüt: *hata vermeden yanlış sonuç* üretti mi?
Ürettiyse yazılır — yeri şuna göre:

| Her dosyada geçerli | `CLAUDE.md` |
| Yalnızca bir klasörde | `.claude/rules/<alan>.md` |

Tuzak **belirtiyle** yazılır ("500 dönüyor" değil, "sayfa 200 dönüyor ama
düğme hiç kimseye çıkmıyor"), sonra sebep, sonra çözüm. Denenip
**tutmayan** çözümler de yazılır — yoksa bir sonraki oturum onları
yeniden dener.

⚠️ `CLAUDE.md`'ye tuzak eklediysen `TuzakSayimiTest`'teki sayıyı da artır.
Sayıyı **düşürmek** kayıp demektir; artırmak bilerek yapılır.

**5 · Commit mesajı.** Başlık `<blok> — <ne yapıldı>`. Gövde kararı ve
**ölçümü** anlatır; dosya listesi değil. Son satır test sayısı.

## Durdurma koşulu

Şu üçü de doğruysa belgeleme bitmiştir:

- Bu oturumda öğrenilen her şey **bir dosyada** yazılı.
- Bir karara katılmayan biri gerekçesini **arayarak bulabilir**.
- `git log` mesajı tek başına okunduğunda ne ölçüldüğü anlaşılıyor.

## ⚠️ Süit koşarken belge dosyasına dokunma

Bu projede belgeleri **okuyan testler** var (`TuzakSayimiTest`,
`AjanKurulumuTest`, `HookKurulumuTest`). Arka planda süit koşarken
`CLAUDE.md` düzenlendi ve koşu **eski sayıyı** yeşil gördü; gerçek durumu
CI gösterdi (A2). Belgeyi yaz, **sonra** koştur.
