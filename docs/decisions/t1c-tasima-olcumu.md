# T1c — eski adım türleri bloklara taşınabilir mi (ölçüm)

Kullanıcı 2026-11-02: "T1c ile devam et". T1c = Ç83 (1) eski türlerin bloklara taşınması + (4) Puck'ın tarayıcıda ölçümü.

## Kullanım (yerel veritabanı, 2026-11-02)

| Tür | Taslaklarda | Yayın sürümlerinde |
|---|---|---|
| soru | 18 | 46 |
| tanitim | 8 | 26 |
| paywall | 5 | 14 |
| yorum | 1 | 1 |
| yukleme | 0 | 0 |

## Eski adım ↔ eşdeğer ekran (aynı tanımdan iki çizim, `renderToStaticMarkup`)

Hiçbiri birebir aynı DOM vermedi:

| Eski | Ekran karşılığı | Fark |
|---|---|---|
| soru (tekli/çoklu) | baslik = soru, soru bloğu, dugme = devamDugmesi | yalnız sarmalayıcılar (`section.ekran`, `div.bloklar`) + `.bloklar` 16 px üst boşluk |
| soru + aciklama | + metin bloğu | açıklama `p.yardim-metni` stilini kaybeder |
| tanitim görselsiz | metin bloğu | sarmalayıcılar + boşluk |
| tanitim görselli | görsel bloğu | **görsel başlığın ALTINA iner** (ekranda h1 önce, Ç82); `kahraman` / `tamEkran` hiç ifade edilemez |
| yorum | yorum bloğu | farklı kart (blockquote.yorum-alinti ↔ figure.blok-yorum) |
| yukleme | — | ifade edilemez (kendiliğinden geçiş) |
| paywall | — | ifade edilemez (ödeme) |

## Sonuç

Kayıpsız taşıma yok: yayındaki hunileri dönüştürmek görünümlerini değiştirir, yükleme ve ödeme bloklara sığmaz. Yapılmadı — karar kullanıcıda (Ç86). Seçenekler Ç86'da.

## Puck düzenleyicisi tarayıcıda (2026-11-02, Ç83 (4))

Kullanıcının Chrome'u, `keepchat/ai-ornek` düzenleyicisi; hiçbir şey KAYDEDİLMEDİ.

- Ekran sürüklenince kimliğe ek geldi (`ekran-248d`), varsayılan paragraf bloğu yuvaya Puck kimliğiyle girdi (`MetinBlogu-<uuid>`), yuva `div.bloklar` sınıfını taşıyor. ✅
- **Kusur bulundu:** Puck düzenleyicide her bloğu ayrıca `[data-puck-component]` kutusuna sarıyor → `.bloklar > p` kuralı ulaşmıyor: paragraf metin renginde ve 17 px (hunide soluk, 20 px). `Render` testleri bunu göremezdi (o kutu yalnız düzenleyicide). Düzeltme: kurallar o kutuyu da sayar; ölçüm sonrası soluk renk + 20 px. Kırma: kutu seçicisi bozulunca bileşen testi 🔴.
- Soru bloğu yanlışlıkla kök bölgeye bırakıldı → "Ekran bloğu bir Ekran adımının içine konmalı: SoruBlogu" uyarısı göründü ✅; sürükleyip yuvaya taşıyınca uyarı kalktı, yuvada [MetinBlogu, SoruBlogu] ✅.
- Soru bloğunun seçenek hedef listesi: "Sıradaki adım" + ekrandan SONRAKİ 8 adım (ekranın kendisi ve öncekiler yok) ✅.
- Görülen: varsayılan ekranın düğmesi "Devam" — tek seçimli soru bloğu eklenince düğme alanını boşaltmak gerekiyor, yoksa kayıtta çekirdek "dugme olmaz" der (Ç87).

## Adım 1 — ekran kapağı (taşıma için tanıtımla eşitlik, 2026-11-02)

Kullanıcı kararı (Ç86): "eski türler önemli değil, eski quiz kaybolabilir" → ekran tek içerik türü olacak. Önce ekran tanıtımın yapabildiğini yapmalı: `gorsel` (+ `gorselMetni`) başlığın ÜSTÜNDE ve `duzen` ust / kahraman / tamEkran — tanıtımla aynı alan adları ve aynı CSS (`img.kapak`, `section[data-duzen]`), taşıma alanı alana kopyalar.

**Yeni kural:** tam ekranda yalnız `baslik`, `metin`, `maddeler`, `istatistik` blokları — metin beyaz ve karartmanın üstünde; yüzeyli bloklar (kutu, yorum kartı, seçenek, görsel) beyaz metni açık zeminde bırakırdı. Blok paragrafları tam ekranda beyaz (%90).

Kırma 5/5 kırmızı (iki deneme ilk koşuda geçersizdi: biri çıktıyı değiştirmeyen yama, biri tanıtımı da tutan yama → düzeltildi):

| # | Bozulan | Sonuç |
|---|---|---|
| C1 | ekran kapağı tanınmaz | 🔴 testEkranKapagi |
| C2 | tam ekran blok sınırı yok | 🔴 2 test |
| C3 | ekran kapağı çizilmez | 🔴 bileşen |
| C4 | tam ekranda blok metni beyaz değil | 🔴 CSS |
| C5 | Puck kapak düzenini yazmaz | 🔴 donustur |

## Adım 2 — veritabanı göçü (2026-11-02)

`Version20261102120000`: taslaklar (`huniler.taslak`) ve yayın sürümleri (`huni_surumleri.tanim`) dönüştürülür — sürümlerin değişmezliği kullanıcı kararıyla (Ç86) bu kez bilerek bozuldu; `down` geri dönüşü reddeder. Dönüştürücü göçün İÇİNDE (Tanim sonra değişse de göç aynı kalır).

| Eski | Ekran |
|---|---|
| tanitim | baslik; gorsel/gorselMetni/duzen aynen; metin → paragraf bloğu (boşsa bloksuz); dugme, hedef, ilerlemeGoster |
| soru | soru → baslik; aciklama → paragraf bloğu; soru bloğu (secimTuru, duzen, yanitZorunlu, secenekler + hedefleri); tek seçimde düğme yok, değilse devamDugmesi ?? "Devam" |
| yorum | baslik; yorum bloğu (boş unvan yazılmaz; boş metinde bloksuz); dugme |
| yukleme, paywall, ekran | aynen |

Bu yüzden ekran artık **0–12 blok** alır (başlık + düğme: "Teşekkürler!"; boş metinli tanıtım böyle taşınır).

Ölçüm (yerel geliştirme veritabanı; önce iki tablonun verisi yedeklendi, 33 524 bayt):
- Öncesi taslak: soru 18, tanıtım 8, yorum 1, paywall 5 · sürümler: soru 46, tanıtım 26, yorum 1, paywall 14.
- Sonrası taslak: ekran 27, paywall 5 · sürümler: ekran 73, paywall 14 (sayılar tutuyor).
- 19 tanımın (5 taslak + 14 sürüm) hepsi `Tanim::hatalar` ile geçerli.
- `genel` taslağının taşınmış "kiminle" adımı ekran görüntüsünde doğru çizildi (soru bloğu, düğmesiz).

Kırma 5/5 kırmızı: D1 tek seçimli soruya düğme · D2 boş metin boş blok · D3 açıklama taşınmaz · D4 kapak taşınmaz · D5 bloksuz ekran geçersiz.

Göç sınıfları testten çağrılabilsin diye `DoctrineMigrations\` geliştirme otomatik yüklemesine eklendi (`composer.json` autoload-dev).

## Adım 3 — eski türler kaldırıldı (2026-11-02)

Tanıtım, soru ve yorum adım türleri sözleşmeden (`TanitimAdimi`, `SoruAdimi`, `YorumAdimi`), çekirdekten (`Tanim::TURLER`, soru adımı kuralları), ortak bileşenlerden (`Tanitim`, `Soru`, `Yorum`; ölü CSS `.yorum-alinti`, `.yardim-metni`), oynatıcıdan, Puck'tan (`Tanitim`, `TekSecim`, `CokluSecim`, `SerbestYanit`, `Yorum` bileşenleri; menüde adım olarak yalnız Ekran, Yükleme, Ödeme) ve AI istemlerinden çıktı. Seçenek şeması (`SoruSecenegi`) ve yanıt alanı (`Yanit`) soru bloğunda yaşıyor. Yeni huni boş taslağı ekranla başlar.

Testler taşındı, kural kaybı yok: soru adımı kuralları (seçenek sayısı/kimliği/hedefi/simgesi, düzen, yanıt biçimi, zorunluluk) soru bloğu üzerinden aynı testlerle; tanıtım düzeni ekran kapağıyla; yorum yorum bloğuyla; önizleme = huni testi artık her adımı Puck'ın `Render`'ıyla çizer (bileşen render'ını elle çağırmak yuvayı açamaz). Silinen testler yalnız artık olmayan şeyi ölçenler (soru açıklaması alanı, "hazır soru türleri" menüsü, kayıttan gelen soru türü → Puck bileşeni).

Ölçüm (gerçek sistem): taşınmış `genel` hunisi düzenleyicide açıldı (5 ekran + ödeme, "Yayındakiyle aynı", uyarı yok); karşılama ve soru ekranları ekran görüntüsünde doğru.

Kırma 5/5 kırmızı: E1 tanıtım türü yeniden kabul · E2 yeni huni taslağı eski türle (ilk koşuda YEŞİL — test yoktu; `testBosTaslakMetinleriDoldurulunca` eklendi) · E3 adım menüsünde fazladan tür · E4 akış ekran hedefini bilmez · E5 önizleme Render'sız.

Yan iş: PHPStan iç içe dizi yazımını tipleyemedi → test yardımcısı `blok(array $blokEk, array $ekranEk)` tüm değişikliği argümanla alır (null siler); eski `+` birleşimi testteki değeri ezmiyordu (`array_merge`).
