# G1 — görünüm ayarları: envanter + hangisi ekranda en çok fark yaratır (2026-10-27)

Kullanıcı: "G1 ile devam et" (PLAN "Huni görünüm yüzeyi", Ç71 (b)). Ürün kodu yok; kod alınmadı (kural 12) — belgeler okundu, ölçüm bizim hunimizde yapıldı.

## 1. Envanter (belgeden, üretici beyanı)

| Araç | Görünüm ayarları |
|---|---|
| **web2wave** | Quiz/paywall başına **tema CSS değişkenleri** (belgede örnek: `--primary`, `--text-color`, `--radius`; MCP şeması 1700+ değişken diyor) · **blok başına Styles sekmesi** (renk, tipografi, boşluk, kenarlık, kap; "global temanın üstüne") · özel CSS · yazı tipi yalnız özel CSS ile (Google Fonts `@import` ya da yüklenen dosya + `@font-face`; hazır liste yok). Tasarım kuralları: 375 px mobil, gövde 16 px, başlık 22–26 px, renk değişkenle, **özel gradyan / gelişmiş gölge / bulanıklık kaçınılır**, sabit alt çubuktan kaçınılır |
| **Formbricks** (AGPL) | açık/koyu · başlık/açıklama/üst etiket rengi-boyutu-ağırlığı · girdi (renk, kenarlık, köşe, yükseklik, dolgu, gölge) · düğme (arka plan, yazı, köşe, yükseklik, boyut, ağırlık, dolgu) · seçenek (arka plan, yazı rengi, köşe, dolgu, boyut) · **kart** (yuvarlaklık, arka plan, kenarlık, düzen: Simple/Straight/Casual) · ilerleme çubuğu (iz, gösterge, kalınlık) · **arka plan: renk, animasyon, yükleme, Unsplash görseli, karartma** · logo · anket başına tema ezme · özel CSS |
| SurveyJS, HeyForm | belge sayfaları 404 / ayar listesi yok — **okunamadı** |
| **MyWeb2Wave** | huni başına 5 alan (`anaRenk`, `arkaPlan`, `metinRengi`, `yaziTipi` 4 yığın, `kose`) + logo |

## 2. Ölçüm — yöntem
- KeepChat `genel` taslağı, 3 adım (tanıtım `karsilama`, soru `kiminle`, ödeme `paywall`), ekran kabı (Ç72) 390×844 @2×, **PNG**. Her varyant CSS olarak sayfaya enjekte edildi (ürün ayarı değil, **simülasyon**); önizleme şeridi kaldırıldı. 19 varyant × 3 adım = 57 görüntü, 115 sn.
- Varyantlar: mevcut alanlar (E1 ana renk, E2 koyu, E3 açık renkli zemin, E4 serif, E5 köşe 24/0) · adaylar (C1 zemin geçişi, C3 büyük başlık, C4 ortalı, C5 hap düğme, C6 çerçeve düğme, C7 renkli seçenek, C8 ızgara seçenek, C9 içerik kartı, C10 geniş boşluk, C11 kalın ilerleme) · P1 hazır tema (koyu + renk + hap + büyük başlık).
- **İki ölçüt** (ikisi de vekil): *renk* = değişen piksel % (bir kanalda fark > 32) · *düzen* = kenar haritası farkı (kenar piksellerinin simetrik farkı / birleşim). Gürültü: temel iki kez çekildi → **0,0 / 0,0**.
- Üçüncü: tüm varyantların panosuna bakıp **nitel yargı** (benim; ölçüm değil).
- Betikler ve ham sonuç: `docs/decisions/g1-olcum/` (PHP betiği `core/var/` altına kopyalanıp `docker compose exec core php var/g1-olcum.php <huni-adresi> <önizleme-anahtarı>`; Python `python3 g1_fark.py core/var`).

## 3. Sonuç (3 adım ortalaması)

| Varyant | Renk % | Düzen % | Not |
|---|---|---|---|
| E2 koyu zemin (mevcut alan) | 97,4 | 97,8 | kimliği tek başına değiştiriyor |
| P1 hazır tema | 97,7 | 95,9 | |
| C1 zemin geçişi | 81,8 | 12,0 | renk alanı büyük, düzen aynı |
| C9 içerik kartı | 17,4 | 89,1 | hem zemin hem çerçeve — en "farklı ürün" görünümü |
| C3 büyük/ağır başlık | 4,6 | 76,4 | düzen ölçütü kaymayı da sayar (altı kayıyor) |
| C10 geniş boşluk | 4,2 | 75,8 | aynı |
| E4 serif (mevcut alan) | 1,8 | 52,1 | kapta DejaVu Serif — iPhone'da Georgia/New York |
| C4 ortalı | 2,1 | 46,2 | |
| C8 ızgara seçenek | 0,6 | 21,3 | yalnız soru adımında: 63,9 |
| C6 çerçeve düğme | 4,0 | 10,4 | |
| C11 kalın ilerleme | 0,6 | 9,7 | |
| E5 köşe 24 / 0 (mevcut alan) | 0,2 / 0,1 | 8,0 / 3,0 | en küçük etki |
| C5 hap düğme | 0,1 | 2,3 | |
| C7 renkli seçenek | 0,3 | 1,2 | eşik açık tonu kaçırıyor; panoda fark görünüyor |
| E3 açık renkli zemin (mevcut) | 0,0 | 0,3 | eşik altı (ort. fark 3,2) — panoda sıcak ton belirgin |
| E1 ana renk (mevcut) | 4,7 | 0,0 | yalnız çubuk, işaret, düğme |

**Ölçüt sınırları:** renk ölçütü büyük alanı abartır, açık ton değişimini (E3, C7) kaçırır; düzen ölçütü bir öğe büyüyünce altındaki her şeyin kaymasını da sayar (C3, C10). Piksel farkı **dönüşüm demek değil** — yalnız "aynı görünüyor" şikâyetinin ölçüsü.

## 4. Bulgular
1. **Zemin baskın:** adımlar seyrek (ödeme adımı ekranın ~⅔'ü boş, görsel yok) → görüntünün çoğu zemin. Zemin rengi/koyu/geçiş/kart en büyük farkı veriyor; bugünkü 5 alandan da en etkilisi `arkaPlan` (koyu). AI temada zemini değiştirmezse (yalnız ana renk + köşe) huniler "aynı" kalır — Ç71'in ölçülmüş açıklaması.
2. **Yapısal ayarlar ikinci sırada:** içerik kartı, tipografi ölçeği (başlık boyutu/ağırlığı), boşluk yoğunluğu, hizalama — renk değiştirmeden "başka ürün" hissi veriyor. Planda yoktular.
3. **Düğme biçimi, köşe, ilerleme kalınlığı, seçenek tonu küçük:** G4'ün düğme kısmı düşük öncelik; ızgara seçenek yalnız soru adımında belirgin.
4. **Boşluk sorunu:** görselsiz adım ekranı dolduramıyor → adım başına görsel düzeni (G5, "görsel üstte") ve zemin görseli (G3) aynı zamanda boşluğu kapatıyor.
5. **AI'ın gözü için iki kusur** (Ç72 aracı): (a) önizlemede ödeme kapalı → düğme **soluk/devre dışı** çiziliyor, koyu temada neredeyse görünmüyor; AI bunu "okunmuyor" diye yanlış düzeltebilir (Ç75). (b) ekran kabında yalnız **DejaVu** yazı tipleri; `yuvarlak` (`ui-rounded`) yalnız Apple/Safari'de var, başka yerde sistem yazı tipine düşüyor — AI ve Android ziyaretçisi farkı görmüyor (Ç76).

## 5. Öneri — G sırası (PLAN'a işlendi)
1. **G3 zemin**: geçiş + görsel (+ karartma), kontrast ölçümü korunur — en büyük etki, boşluğu da doldurur.
2. **G6 içerik kartı** (yeni): zemin üstünde kart (arka plan, gölge/kenarlık) — Formbricks "card styling" davranışı.
3. **G7 tipografi ölçeği + yoğunluk** (yeni): başlık boyutu/ağırlığı, hizalama (sol/orta), sıkı/ferah — birkaç ön ayar olarak (serbest piksel değil), AI'ın seçmesi kolay.
4. **G2 yazı tipi**: etkisi orta ama cihaz/KVKK bağımlı — kendi sunucumuzdan barındırılan font kararı (kullanıcı) + ekran kabına aynı fontlar (Ç76).
5. **G5 adım başına çeşit** (görsel üstte / tam ekran) — boşluk sorununu çözer; T1 ile sıralanır.
6. **G4 düğme biçimi + seçenek düzeni**: düşük etki; ızgara soru adımında değerli, düğme biçimi son.
- Her yeni alan `Tema::aiYonergesi` örneklerine girer; bulgu 1 gereği yönergeye "zemini de değiştir" vurgusu eklenebilir (küçük iş, ayrı karar).
