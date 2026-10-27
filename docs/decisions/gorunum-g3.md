# G3 — zemin: geçiş ve görsel (2026-10-27)

Kullanıcı: "G3 ile devam et". G1 ölçümü: görüntünün çoğu zemin, en büyük görsel farkı zemin veriyor (`gorunum-g1.md`).

## Karar
- **Sözleşme:** `HuniTemasi.zemin` isteğe bağlı, `oneOf`: `ZeminGecisi {tur: gecis, ikinciRenk}` (yukarıdan aşağı arkaPlan → ikinciRenk) · `ZeminGorseli {tur: gorsel, gorsel, karartma 0–95}` (görsel kaplar, üstünde arkaPlan renginde %karartma katman). Eski tanımlar aynen geçerli; AI araç şeması sözleşmeden yeniden üretildi.
- **Okunurluk çekirdekte (`Tema.php`):** geçişte metin alt renkte de ≥ 4,5:1. Görselin içeriği bilinmez → karartma, görsel **en kötü durumda** (tam siyah ya da tam beyaz) olsa da metni okutmalı; karışım sRGB'de (CSS `color-mix(in srgb)` ile aynı). Yetersizse hata **en az gereken karartmayı** söyler (AI ve insan tek adımda düzeltir). Zemin görseli de "bu uygulamada yüklü mü" kuralına girer (`Tanim::gorseller`).
- **Çizim (`tema.ts` → `huni.css`):** `--zemin` (geçiş ya da katman + `url(<medya>) center / cover`), `--alt-renk` (alta yapışık düğme çubuğunun solduğu renk: geçişte alt renk, görselde katman rengi). CSS `var(--zemin, var(--arka))` — zemin yoksa değişken hiç yazılmaz, eski görünüm aynen. Oynatıcı medya kökünü verir; Puck göreli adres.
- **Puck:** kök alanları "Zemin" (düz / geçiş / görsel), geçişin alt rengi, zemin görseli (var olan görsel seçici), karartma %. Dış kutu `--zemin`'i çizer.
- **AI:** `Tema::aiYonergesi` "görünümü en çok zemin değiştirir" + iki alanın biçimi; örneklerden ikisi geçişli (teknoloji, eğlence) — `TemaTest` hepsinin yayından geçtiğini ölçer.
- Reddedilen: görselin gerçek parlaklığını sunucuda ölçmek (görsel değişince eşik kayar, yüklemeye bağımlılık; en kötü durum kuralı içerikten bağımsız ve öngörülebilir) · yön/açı seçimi (G1'de ölçülmedi, kural "serbest piksel değil ön ayar") · animasyonlu zemin (web2wave tasarım kuralı gradyan/efektten kaçınıyor; dikkat dağıtır).

## Yan bulgu — tema ilk boyamadan sonra uygulanıyordu (düzeltildi)
G3 ekran panosunda aynı temada düğme bazen varsayılan mavi, bazen tema rengindeydi. Sebep: oynatıcı temayı `useEffect`'te (boyamadan **sonra**) yazıyor, düğmede 120 ms `background-color` geçişi var → düğme önce varsayılanla çizilip temaya geçiyor. Ziyaretçi her açılışta kısa bir parlama görüyordu; ekran görüntüsü ara rengi yakalıyordu.
- **Ölçüm (ekran kabı, düğme ortası pikseli, hedef `#22d3ee`):** önce 6 çekim `#21c2ee, #23b2ee, #22ccef, #21d3ed, #21c2ee, #21d3ed` (4/6 ara renk) → `useLayoutEffect` sonrası 6/6 `#21d3ed` (JPEG yuvarlaması). Önceki ölçüm bu düzeltmenin kırma denemesidir; **otomatik testi yok** — jsdom boyama yapmaz (tuzak §40).

## Ölçüm
- Testler: `TemaTest` (geçiş alt renk 3,67:1 reddi; görsel eşikleri %51 / %64 — Python'da bağımsız hesaplandı, §3; 8 biçim hatası; AI örneklerinde ≥ 2 zemin) · `MedyaTest` (zemin görseli sahipliği) · `tema.test.ts` (değişken dizgeleri, CSS yedeği) · `donustur.test.ts` (gidiş-dönüş, "yok" → alan yok) · `onizleme.test.tsx` (Puck dış kutusu) · `Huni.test.tsx` (medya kökü).
- Kırma 11/11 kırmızı: K1 `zemin` alan denetiminden düşmez · K2 geçiş kontrastı yok · K3 yalnız siyah görsel denetlenir (açık metinde beyaz görsel bağlayıcı) · K4 karartma sınırı yok · K5 zemin görseli sahiplik dışı · K6 medya kökü yok · K7 CSS yedeği yok · K8 alt çubuk eski renge solar · K9 Puck'tan zemin çıkmaz · **K10 Puck dış kutusu zemini çizmez — ilk denemede tutmadı (test yoktu) → `onizleme.test.tsx`'e test eklendi, K10b kırmızı** · K11 AI örneklerinde zemin yok.
- Gerçek sistem (konsol aracı, KeepChat `genel` taslağı; önce yedeklendi, sonra **aynen geri yüklendi** — tanım ve imza yedekle eşit): karartma %30 → "en az %64 olmalı (şu an %30)"; geçiş (#0b1120 → #1e3a8a) ve görsel (%70) kaydedildi, üç adımın ekran görüntüsü okunur.
- `bin/kontrol` yeşil (283 çekirdek testi).
- **Ölçülmedi:** Puck düzenleyicide yeni alanların görsel kontrolü (tarayıcıda açılmadı); iOS Safari'de `background` + yapışık çubuk davranışı.
