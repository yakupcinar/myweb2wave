# V5 — yeni bloklar: grafik, ölçek, önce/sonra (2026-11-03)

**İstek:** kullanıcı 2026-11-03: "V5'e devam". Ç97 (2)(3)(5): ölçek/kaydırıcı,
hesaplanan sonuç, önce/sonra; rakip: FunnelFox Chart, Lottie.

## Ölçüm (önce) ve elenenler

- Huni JS bütçesi `funnel-butce`: gzip ≤ 30 720 B; kullanılan 18 142 B.
- **Lottie ELENDİ:** oynatıcılar bütçeyi tek başına aşar (npm: lottie-web
  5.13.0 açılmış 25,4 MB, @lottiefiles/dotlottie-web 0.82.1 7,3 MB + WASM).
  Tembel yükleme ayrı bir blok olarak açılabilir → Ç106.
- **Sayısal kaydırıcı (kilo/yaş) ERTELENDİ:** cevap seçenek kimliği değil
  sayı — yeni cevap türü (ilerleme ucu, koşul işlemleri büyüktür/küçüktür,
  rapor) ister → Ç106.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | `grafik`: projeksiyon eğrisi, yalnız `baslangic`/`hedef` etiketi (≤ 40) + `egri` artan/azalan; satır içi SVG, ana renk; etiketler HTML | sayı/yüzde ekseni | Uydurma sonuç yazılamasın (yönerge: sayı yok); etiket koşullu değerle kişiye özel olabilir |
| 2 | Ölçek = soru `secimTuru: olcek` + `olcekSol`/`olcekSag`; seçenekler kendiliğinden, cevap kimlikleri "1"…"5" (çekirdek `OLCEK_KIMLIKLERI` ↔ bileşen `OLCEK`) | ayrı blok türü | Tekli seçim hattı aynen çalışır: ilerleme ucu, koşul (`icerir "5"`), yönlendirme, rapor |
| 3 | Ölçekte seçenek, düzen ve düğme yazılamaz (tek dokunuş geçer) | — | Kendiliğinden seçenekle çakışırdı |
| 4 | `onceSonra`: iki yüklenmiş görsel + etiket (alt metin de); tam ekranda yasak; görseller "yüklenmiş mi" denetimine girer | — | Stok kişiyle kullanımı yönerge yasaklıyor (V3b: onay ima) |
| 5 | Canlı harekette grafik eğrisi çizilerek gelir (`stroke-dashoffset`, `pathLength=1`) | — | V4 katmanına bağlı, aynı bekçi |
| 6 | Ölçek ekranı telefonda dikey ortada (`section:has(.olcek)`, yükleme gibi) — **ölçülen**: içerik zenginleştirildikten sonra bile %37 boşluk; ortalayınca %28, uyarı yok | AI'a "daha çok blok" dedirtmek | İfade ekranı tek dokunuşluk; boşluk içerik değil düzen sorunu |

## Gerçek ölçümler

- Ekran: ölçek ekranı önce %57 boş (uyarı) → kutu + metin eklendi %37 (uyarı) → ortalama %28 (uyarısız); özet ekranında grafik + uyarısız (%13).
- Chrome `getAnimations` (canlı, e-posta → özet geçişi): `huni-giris` H1@60, grafik@120, **`huni-ciz` grafik-egri@250**, …, eylem@300.
- Oynatıcı testi: ölçekte "4" → ilerleme `{adim, cevap: "4"}`, sonraki adım.
- Önizleme = huni: grafik + önce/sonra + ölçek aynı DOM.
- Önce/sonra gerçek görselle ekranda GÖRÜLMEDİ (medyada gerçek görsel yok — yalnız DOM ölçüldü).
- Bütçe: 18 142 → 18 625 B.
- `bin/kontrol` YEŞİL: 495 çekirdek testi.

## Kırma (13/13 kırmızı; ikisi önce yeşil, test eklendi)

Y1 ölçek cevap kimlikleri (koşul) · Y2 ölçek tek dokunuş (düğme yasağı) · Y3
uç etiketleri zorunlu · Y4 seçenek yasağı · Y5 önce/sonra görsel envanteri ·
Y6 sonra görseli denetimi · Y7 eğri değeri · Y8 azalan eğri çizimi · Y9 uç
düğme adı · Y10 oynatıcıda ölçek seçenekleri · Y11 düzenleyici eğri eşlemesi ·
Y12 düzenleyicide ölçek düğmesi — **önce yeşil** (akis testi yoktu) → test ·
Y13 ölçek ortalama CSS'i — **önce yeşil** → bekçi testi.
