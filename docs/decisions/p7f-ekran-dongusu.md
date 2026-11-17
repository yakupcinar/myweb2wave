# P7f — Panel ekran döngüsü ve ilk bulguları (2026-11-11)

## Ne yapıldı

`bin/panel-ekran [sayfa…]` komutu panelin her sayfasını başsız Chrome'da çizer. Her sayfa iki temada (açık, koyu)
ve iki genişlikte (1440 masaüstü, 390 telefon) çekilir; 8 sayfa için 32 tam sayfa JPEG çıkar. Her görüntünün yanına
yerleşim ölçümü yazılır (`core/var/ekran/panel/olcum.json`):

- sayfa genişliği ve yatay kayma;
- içeriği kesilen öğeler;
- telefonda 36 px'ten kısa dokunma hedefleri.

Nasıl kurulu:

- **Kap:** `panel-ekran` (compose profili `ekran`). Çekirdekle aynı imaj, aynı kod, aynı geliştirme veritabanı.
- **Kullanıcı:** panel kullanıcısı yalnız bu iş için üretilmiş bir test kullanıcısı (`ekran`). Şifresi ilk koşuda
  rastgele üretilir ve `core/var/ekran/panel.env`'e yazılır (git dışı, izin 600, sohbete basılmaz). Bu kapta ortam
  değişkeni `.env.local`'i ezer.
- **Erişim:** host'a port açılmaz; yalnız `ekran` kabındaki Chrome ulaşır.
- **Sürücü:** [`core/bin/panel-ekran.php`](../../core/bin/panel-ekran.php), chrome-php ile CDP üzerinden. Girer,
  temayı `localStorage`'a yazar, sayfa çizilene kadar bekler ("Yükleniyor…" kalmadı, yazı tipleri hazır), çeker ve
  ölçer.

## Neden böyle

- **Kullanıcı kararı (A26):** ekran görüntüsü için ayrı bir test kullanıcısı. Gerçek panel şifresi kullanılmaz ve
  okunmaz.
- **Ayrı kap ama aynı veritabanı.** Ayrı bir veritabanı kopyası gerçek veriyle eskirdi; ekran gerçek geliştirme
  verisini göstermeli. Oturum tablosu ortak, ama `ekran` kullanıcısı 8081'deki panelde tanımlı değil (`refreshUser`
  düşer). Yani bu kapta açılan oturum asıl panele geçmez.
- **Reddedilenler:**
  - Chrome eklentisi: senin girişine bağlı ve bu oturumda iki kez koptu.
  - Geliştirme ortamında girişi atlayan bir yol: bir güvenlik deliği koda girerdi.
  - Taslakla piksel farkı: taslak yalnız üç sayfa için var. Yine de yöntem hazır (p7-taslak-farki.md).
- **Ölçüm `innerWidth` ile değil istenen genişlikle.** İlk koşuda Aboneler telefonda 435 px'e açıldı, ama ölçüm
  "kayma yok" dedi: telefon kipinde tarayıcı görünür alanı taşan içeriğe göre büyütüyor, `innerWidth` de onunla
  büyüyor. Görüntüye bakınca fark edildi ve ölçüt düzeltildi. 1 px'lik ekran okuyucu etiketleri de "taşan"
  sayılıyordu; onlar çıkarıldı.

## Döngünün bulduğu ve düzeltilenler

| Bulgu | Ölçüm | Düzeltme |
|---|---|---|
| Telefonda menü altı satıra sarılıyor, ekranın üçte biri; bağlantılar 31 px | görüntü + `kucukHedefler` | Menü telefonda tek satırda kayar; bağlantılar en az 36 px (`Duzen.tsx`) |
| Aboneler telefonda 435 px (Durum seçicisinin 6 seçeneği sığmıyor); tablo ve "Bitti" kesik | `sayfaGenisligi: 435` | Bölmeli seçici en fazla kabı kadar, sığmazsa kendi içinde kayar (`ui/bolmeli.ts`; tüm seçicilere) |
| Uygulama: `keepchat` ile `://giris` arasında boşluk (sabit 140 px) | görüntü | Alan içerik kadar (`field-sizing: content`, desteklemeyen tarayıcıda `size`) |
| Uygulama: "Otomatik görevler" teknik komut adlarıyla taşıyor | görüntü | Okunur Türkçe adlar ("webhook gönderimi", "hatırlatma e-postaları"…) |
| Planlar: "Yeni plan" ile "Ek ürünler" kartı yapışık | görüntü | İki liste arasında kart aralığı |

**Bulgu değil:** tam sayfa çekimde sol menünün zemini ekran yüksekliğinde bitiyor. Menü yapışkan, tarayıcıda
kaydırınca yerinde kalıyor; bu yalnız tam sayfa çekimin yan etkisi. Koyu temada onay kutusu Chrome'un kendi koyu
çizimi (`color-scheme: dark` bildirili).

## Kırma denemeleri — 3/3 kırmızı

| Deneme | Nasıl ölçüldü | Sonuç |
|---|---|---|
| F1 bölmeli seçici sığmayınca kaymaz | derle + `bin/panel-ekran kullanicilar` | `sayfaGenisligi: 435, yatayKayma: true` |
| F2 menü eski hâli (sarılır, kısa) | derle + `bin/panel-ekran ayarlar` | menünün 7 bağlantısı `kucukHedefler`'e döndü |
| F3 görev adları teknik | `Ayarlar.test.tsx` | 1 test düştü |

Geri alınca ölçüm temiz döndü: iki sayfa 390 px, menü bağlantıları kısa hedef listesinde yok. Bölmeli seçicinin ve
menünün davranışı Vitest'te ölçülemez (CSS yok, §32), bu yüzden ekran döngüsüyle ölçüldü. Döngü CI'da koşmuyor,
çünkü `ekran` kabı ve geliştirme verisi gerekiyor. Betik: `$TMPDIR/…/scratchpad/kir-p7f.py`.

## Ölçüm

- **Döngü:** 32 görüntü. Son koşuda telefonda hiçbir sayfada yatay kayma yok. Telefonda kalan "kısa" hedefler
  bölmeli seçiciler (28–32 px, Apple'ın kendi ölçüsü, bilerek) ve satır içi metin bağlantıları.
- **Tam kontrol:**
  - İlk koşu panel testinde KIRMIZI: Huniler'deki bir `waitFor` yük altında 1 saniyede bitmedi.
  - Panel süiti yalnız iki kez koşuldu, ikisinde de 183/183.
  - İkinci tam kontrol YEŞİL (748 çekirdek testi). Aralıklı düşüş Ç134'te.
- **Karar bekleyen:** Huniler satırında 5 düğme (Ç135); Aboneler tablosu telefonda yatay kayıyor (Ç136).
