# M9d1 — Yönlendirme kuralında ülke ve dil (2026-11-11)

Kullanıcı kararı (M9d kapsamı): **yönlendirme + huni dili** (önerilen). Bölünme: M9d1 kural (bu kayıt) → M9d2 huninin
dili (oynatıcı sabit metinleri, sayfa dili, yasal bağlantılar).

## Ne yapıldı

Yönlendirme kuralları adres parametrelerine (`k`, `utm_*`) ek olarak **ülke** ve **dile** bakabiliyor. Örnek:
"dil = en ise `keepchat-en` hunisi", "ülke = DE ise `de-hunisi`".

- **Ülke:** fiyattaki ülkeyle aynı kaynak ([`UlkeBulucu`](../../core/src/Billing/UlkeBulucu.php): IP; test için
  `?ulke=DE`).
- **Dil:** önce `?dil=en` (test; ileride dil değiştirici), yoksa tarayıcının `Accept-Language` başlığındaki **ilk**
  dil, iki harf. Huni sayfası `?dil=`'i yalnız iki küçük harfse iletir (`erken-istek.ts`).
- **Yanıt:** `/huni/{app}` artık `Vary: Accept-Language` döner.
- **Değer denetimi:** ülke ve dil değeri iki harf. Uygulamada açık hata ("ülke iki harfli kod olmalı (ör. TR, DE)"),
  veritabanında `huni_kurallari_bolge_check`. Parametre listesi göçle genişledi.
- **Panel:** kural formunda okunur adlar ("ülke (IP)", "dil (tarayıcı)"); değere göre örnek (DE / en); tabloda ülke
  büyük harfle. Açıklama, adresle nasıl deneneceğini ve "çeviri = ayrı huni"yi söylüyor.

## Neden böyle

- **Web2wave modeli:** çeviri ayrı bir kopya, yönlendirme kurala göre (`web2wave-platform-yapisi.md` § Dil / ülke).
  Eşleşme yoksa bizim kararımızla varsayılan huni açılır.
- **Kurallar aynı sıralı listede.** Ayrı bir "dil haritası" reddedildi. Tek liste "kampanya kuralı dil kuralından
  önce" gibi önceliği kullanıcıya bırakıyor ve ilk tutan kuralı kazanır yapıyor (test ölçüyor).
- **Yalnız ilk dil.** `tr-TR, en` diyen tarayıcıda ikinci dile bakılsaydı Türk ziyaretçi İngilizce huniye gidebilirdi.
- **Değer biçimi iki katmanda.** "Türkiye" yazılmış bir kural hata vermeden hiç tutmazdı (tuzak ailesi §4). DB kısıtı
  ayrıca ölçüldü.
- **`Vary`:** yanıt zaten yalnız tarayıcıda önbelleğe giriyor (M1c), ama dil değişince aynı adresin eski hunisi
  60 sn kalmasın.

## Kırma denemeleri — 9/9 kırmızı

| Deneme | Sonuç |
|---|---|
| D1 Accept-Language okunmaz | HuniSecimTest 1 düştü |
| D2 başlık `?dil=`'in önünde | 1 |
| D3 son dil alınır (ilk değil) | 1 |
| D4 `Vary` yok | 1 |
| D5 ülke kurala girmez | 1 |
| D6 uygulamada iki harf denetimi yok | 1 hata (DB kısıtı yakaladı, test açık hata bekliyor) |
| D7 DB kısıtı kaldırıldı (test DB'de elle) | 1 düştü |
| D8 oynatıcı biçimsiz `?dil=`'i iletir | erken-istek.test 1 |
| D9 panel ham parametre adı | Huniler.test 1 |

D7 notu: kısıtsız koşu geçersiz satırı test DB'de bıraktı ve kısıt geri eklenemedi; tablo boşaltılıp kısıt geri
kondu, sonra 12/12 yeşil. Betik: `$TMPDIR/…/scratchpad/kir-m9d1.py`.

## Ölçüm

- **Testler:**
  - Çekirdek: HuniSecimTest +2 (ülke/dil kuralı; iki harf + DB kısıtı); PanelHuniTest'te parametre listesi açıkça.
    218 test birlikte yeşil.
  - Huni: erken-istek +1. Panel: Huniler +1.
- **Testin kendi kusuru (tuzak §59):** Symfony test istemcisi her isteğe `Accept-Language: en-us` ekliyor. İlk
  koşuda "başlıksız" sanılan istek İngilizce huniyi aldı; dile bakan satırlara başlık açıkça verildi.
- **Gerçek HTTP (dev, geçici `dil=en → fitness` kuralı):**
  - `Accept-Language: en-GB` → `fitness`;
  - `tr-TR` → varsayılan `b1deneme`;
  - `tr` + `?dil=en` → `fitness`;
  - her yanıtta `Vary: Accept-Language`;
  - DB `ulke=Turkey` satırını kısıtla reddetti.

  Kural sonra silindi.
- **Tam kontrol:** üç kez KIRMIZI, sonra YEŞİL.
  1. `belge-baglanti`: Huniler.php değişince `#L` kaydı (§58); `--duzelt`.
  2. `panel-test`: Deneyler'in P7e testi yük altında 1 sn'yi aştı (Ç134 ailesi, ikinci kez). Panel testlerine ortak
     kurulum eklendi (`src/test-kurulum.ts`: bekleme üst sınırı 3 sn). Kırma: üst sınır 1 ms'ye inince 79 test düştü,
     yani ayar uygulanıyor.
  3. `sozlesme-guncel`: sözleşme değiştikten sonra `npm run uret` atlanmıştı; üretilince yeşil.

  Son durum: 750 çekirdek testi, 187 panel testi.
