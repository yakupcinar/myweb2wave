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
