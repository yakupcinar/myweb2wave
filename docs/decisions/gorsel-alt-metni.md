# L9 — Görsel alt metni (Ç44) + logo da medya kontrolüne girer (L8 açığı)

2026-10-26 · karar `pre-setup.md` A13 ("alt metin alanı; silme yok").

## Ölçüm (önce)
- Tanıtım kapağı ve seçenek görselleri hep `alt=""` (dekoratif sayılıyordu);
  anlam taşıyan görselde ekran okuyucu hiçbir şey okumuyordu (Ç44).
- L8'in logo testi **sahte bir görsel kimliğiyle** geçmişti:
  `Huniler::hatalar` görselin bu uygulamaya yüklü olduğunu
  `Tanim::gorseller` ile toplar, o da yalnız adımlara bakıyordu — logo
  başka uygulamanın ya da var olmayan bir görsel olabiliyordu.

## Karar
- Tanıtıma ve seçeneğe isteğe bağlı `gorselMetni` (1–200). Yalnız `gorsel`
  ile: görselsiz alt metin ekranda hiçbir yere çıkmaz, yazanı yanıltır →
  çekirdek reddeder; Puck görsel kaldırılınca alt metni tanıma yazmaz.
  Boşsa `alt=""` (dekoratif). Sözleşme `GorselMetni`.
- `Tanim::gorseller` logoyu da toplar (kural 7: aynı "bu uygulamada var mı"
  kuralı bütün görsellere).
- **Reddedilen:** zorunlu alt metin (dekoratif görsel meşru) · logo alt
  metni (başlık ürünü adlandırıyor, L8).

## Test
Çekirdek 73 HuniTest (+3 bozuk: görselsiz alt metin tanıtım/seçenek, 201
karakter; +1 geçerli + yayından sözleşmeye uyar; logo testi: önce "bu
uygulamada yok", medya satırı eklenince geçerli). Bileşen 30 (+1). Düzenleyici
44 (+2: gidiş-dönüş, görsel kaldırılınca yazılmaz); önizleme = huni (H3)
verisinde iki alt metin.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | görsel şartı yok | 🔴 `görselsiz görsel metni` |
| K2 | uzunluk denetimi yok | 🔴 `uzun görsel metni` |
| K3 | seçenekte alan kabul edilmez | 🔴 geçerli alt metin testi |
| K4 | logo `gorseller`'de yok | 🔴 logo testi — **ilk koşu sahteydi**: `medya` satırı testler arasında kalıyordu, logo testi geri alınmış kodda da kırmızıydı (tuzak §8). `setUp` `TRUNCATE`'ine `medya` eklendi; iki ardışık yeşil, sonra K4 kırmızı, geri yeşil |
| K5 | bileşen `alt` yok sayar | 🔴 bileşen testi |
| K6 | görselsiz alt metin yazılır | 🔴 "görsel kaldırılınca" |
| K7 | Puck'a alt metin taşınmaz | 🔴 gidiş-dönüş + önizleme |
| K8 | Puck Tanıtım `gorselMetni` geçmez | 🔴 önizleme = huni |

## Tarayıcı (Chrome, yerel; `b1deneme` taslağı yedekle)
Kapak görselli tanıtıma alt metin eklendi, önizlemede `img.kapak`
`alt="Uygulamanın sohbet ekranı"`, yüklendi. Taslak geri yüklendi, JSON
**AYNI**.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 223 çekirdek testi / 1.386 doğrulama.
