# G7 — yerleşim ön ayarları: başlık boyu, yoğunluk, hizalama (2026-10-30)

Kullanıcı: "Start G7". G1 ölçümü: büyük başlık (C3) ve geniş boşluk (C10) düzen farkında ~%76, ortalı (C4) %46 — renk değiştirmeden huninin karakterini değiştiriyor (`gorunum-g1.md`). Plan kararı: "birkaç ön ayar, serbest piksel değil".

## Karar
- **Sözleşme:** `HuniTemasi`'ne üç isteğe bağlı enum: `baslikBoyu` (kucuk 24px/700 · normal 28px/750 · buyuk 36px/850), `yogunluk` (sik ×0,75 · normal ×1 · ferah ×1,4), `hizalama` (sol · orta). Yoksa bugünkü görünüm aynen. Okunurluğu etkilemez → kontrast kuralı yok, yalnız değer denetimi (`Tema::ON_AYARLAR`).
- **Çizim:** `tema.ts` yalnız tanımlı ön ayarı değişkene çevirir (`--baslik-boyu`, `--baslik-agirlik`, `--ara`, `--hiza`); `huni.css` yedekleri bugünkü değerler. `--ara` çarpanı: başlık üst boşluğu, açıklama alt boşluğu, seçenek aralığı ve alt boşluğu, seçenek yüksekliği ve dikey dolgusu. Hiza: başlık + açıklama (seçenekler sola dayalı kalır — okuma kolaylığı).
- **Sık alt sınırı:** seçenek yüksekliği 60 × 0,75 = 45 px — dokunma hedefi önerisi 44 px'in üstünde kalsın diye daha sık değer yok.
- **Puck:** üç radyo alanı; "normal"/"sol" tanıma yazılmaz (`kokOnAyarlari`, dönüştürücü ve önizleme aynı yardımcıyı kullanır — kural 7). Açık "normal" yazılmış bir tanım Puck'tan geçince alan düşer (anlam aynı).
- **AI:** yönerge üç alanı ve ne zaman seçileceğini anlatır (sakin → ferah, enerjik → büyük + orta, yoğun içerik → sık); "sağlık" örneği ferah, "eğlence" büyük + orta.
- Reddedilen: serbest piksel/ölçek alanları (AI ve insan için seçim zor, sınır denetimi gerekir) · tek "yerleşim" ön ayarı (üç eksen bağımsız, G1'de ayrı ölçüldü) · seçenekleri de ortalamak (uzun seçenek metni ortada zor okunur).

## Ölçüm
- Testler: `TemaTest` (geçerli değerler, 3 biçim hatası, AI'da ≥ 2 ön ayarlı örnek, yönergede alan adları) · `tema.test.ts` (değişkenler, ön ayarsızken değişken yok, CSS yedekleri) · `donustur.test.ts` (gidiş-dönüş, varsayılan yazılmaz) · `onizleme.test.tsx` (Puck dış kutusu).
- Kırma 9/9 kırmızı: K1 alanlar alan denetimine takılır · K2 değer denetimi yok · K3 "büyük" eşlemesi bozuk · K4 hiza değişkeni yazılmaz · K5 CSS başlık boyunu sabit tutar · K6 yoğunluk seçenek yüksekliğine uygulanmaz · K7 Puck yoğunluğu tanıma yazmaz · K8 Puck varsayılanı da yazar · K9 AI örneklerinde ön ayar yok.
- Gerçek sistem (konsol aracı, taslak yedeklendi → **aynen geri yüklendi**, tanım + imza eşit): temel / büyük+ferah+orta / küçük+sık çekildi; düzen farkı (G1 kenar ölçütü) tanıtım/soru: büyük **%74,6 / %88,2**, küçük **%71,1 / %86,7** — G1 simülasyonuyla tutarlı. `yogunluk: genis` → "tema yogunluk: sik, normal, ferah olmalı".
- `bin/kontrol` yeşil (289 çekirdek testi). Bir ara PHPStan sabit dizide `isset`'i reddetti → `array_intersect_key($t, Tema::ON_AYARLAR)`.
- **Ölçülmedi:** Puck'ta yeni alanların görsel kontrolü; gerçek cihazda (iOS) başlık kırılımları.
