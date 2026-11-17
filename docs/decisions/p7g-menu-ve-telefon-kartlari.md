# P7g — Huniler "…" menüsü, Aboneler telefonda kart (Ç135, Ç136; 2026-11-11)

Kullanıcı kararı: "İkisini de önerdiğin gibi yap".

## Ne yapıldı

- **Huniler (Ç135).** Satırda yalnız Düzenle, Aç ve "…" kaldı. Yeni hunilere örnek yap, Varsayılan yap ve
  Sil/Arşivle menüye taşındı; her öğenin altında kısa açıklama var. Sil/Arşivle seçilince satırda iki adımlı onay
  açılır, eskisi gibi. Menü yeni ortak bileşen [`ui/EylemMenusu`](../../core/panel/src/uygulama/ui/EylemMenusu.tsx):
  - `role="menu"`, öğeleri `menuitem`;
  - açılınca odak ilk öğede; ↑/↓ sarmalı gezinir;
  - Esc kapatır ve odağı düğmeye verir; Tab, dışarı tıklama, kaydırma ve boyut değişimi kapatır.
- **Aboneler (Ç136).** Telefonda (`max-md`) aynı tablo satırı karta döner:
  - e-posta solda, durum noktası sağda;
  - altında geldiği yer, son ödeme ("Son ödeme henüz yok") ve işlem düğmeleri;
  - işlemi olmayan abonede işlem satırı gizli.

  Masaüstü tablo aynen kaldı.
- **Ekran döngüsü (`bin/panel-ekran`) büyüdü:**
  - Açılır öğe durumu eklendi (`huniler-menu`: sayfa çizilince tıklar, görünür alanı çeker).
  - İki yeni ölçüm: `kaydirilanKaplar` (içeriği yatay kaydırmanın arkasında kalan kaplar) ve `menuEkranDisi`.

## Neden böyle

- **Menü satırın içinde çiziliyor ama konumu sabit.** Tablonun kabı `overflow-x-auto`; mutlak konumlu menü son
  satırlarda kesilirdi. Gövdeye taşınan (portal) bir menüyü ise testler ve ekran okuyucu satırla birlikte bulamazdı.
  Konum düğmeden hesaplanır ve görünür alana sıkıştırılır. Telefonda Huniler tablosu kayınca düğme ekran dışında
  kalıyordu ve menü kenardan taşıyordu (ekran döngüsü gösterdi).
- **Kaydırma ve boyut değişimi menüyü kapatır.** Sabit konumlu menü kaydırılan sayfada düğmeden kopardı. Bunun bir
  yan etkisi oldu: tam sayfa çekim görünür alanı yeniden boyutladığı için açık menü görüntüde kayboluyordu
  (ölçüldü: DOM'da menü var, görüntüde yok). Açılır durumlar bu yüzden yalnız görünür alanla çekiliyor.
- **Kart için tek DOM.** Telefon için ikinci bir kart listesi çizilseydi testler ve ekran okuyucu her aboneyi iki kez
  görürdü, iki kopya da zamanla ayrışırdı. Matchmedia kancası da reddedildi: happy-dom'da genişlik varsayımı testleri
  telefon görünümüne düşürebilirdi. CSS'te `max-md:` ile aynı satır grid'e döner. Bedeli: telefonda sütun başlıkları
  gizli, yani sıralama düğmeleri telefonda yok.
- **Menü öğesinin adı yalnız eylem** (`aria-label`); açıklama görünür yardım metni. Yoksa erişilebilir ad
  "Yeni hunilere örnek yapTeması, logosu…" olurdu.

## Kırma denemeleri — 7/7 kırmızı

| Deneme | Nasıl ölçüldü | Sonuç |
|---|---|---|
| G1 Esc kapatmaz | Huniler.test | 1 düştü |
| G2 ok tuşu sarmalamaz | Huniler.test | 1 |
| G3 açılınca odak yok | Huniler.test | 1 |
| G4 dışarı tıklama kapatmaz | Huniler.test | 1 |
| G5 varsayılan hunide de Sil / Varsayılan yap | Huniler.test | 1 |
| G6 Aboneler telefonda yine tablo | derle + `bin/panel-ekran kullanicilar` | `kaydirilanKaplar` "Abonelikler"i içerdi |
| G7 menü konumu sıkıştırılmaz | derle + `bin/panel-ekran huniler-menu` | `menuEkranDisi: true` |

Geri alınca: Aboneler yalnız "Durum" (bilerek kayan bölmeli seçici), menü ekran içinde. Betik:
`$TMPDIR/…/scratchpad/kir-p7g.py`.

## Ölçüm

- **Panel:** 184 test (Huniler 11; yeni "P7g" testi menü düzenini ve klavyeyi ölçüyor).
- **Tam kontrol:** YEŞİL, 748 çekirdek testi.
- **Ekran döngüsü** (son koşu, telefon, `kaydirilanKaplar`):
  - Aboneler: yalnız "Durum".
  - Uygulama, Yasal: yok.
  - Huniler 2, Rapor 6, Deneyler 2, Entegrasyonlar 2, Planlar 1 tablo kendi içinde kayıyor → Ç137.
- Açık menü masaüstünde ve telefonda, iki temada görüldü.
