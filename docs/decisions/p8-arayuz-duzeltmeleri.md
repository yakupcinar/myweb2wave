# P8 — Satır içi form yükseklikleri + düzenleyici (Puck) panel dilinde (2026-11-23)

Kullanıcı (2026-11-23): "P8 arayüz düzeltmelerini yap; Puck'a yani huni toolunda da arayüz sıkıntısı var, o da dahil."
Yöntem A26: her değişiklik `bin/panel-ekran` ile çekilip ölçüldü; kırma da ekran ölçümüyle.

## Ölçüm (önce)

- Satır içi formlar: yerel `<select>` **38 px**, alan ve düğme **36 px** (Huniler, Planlar; yazı boyu eşit, 15 px).
  Meta/TikTok alt satırı: "Bağlantıyı dene" 36 ≠ "Bağlantıyı kaydet" 44 px. Deneyler'de fark yoktu.
- Düzenleyici (döngüde yoktu, eklendi): İngilizce arayüz 7 yazı + "Switch to … viewport" ("Page", "Blocks",
  "Outline"…); ham denetim 8 (geri bağlantısı tarayıcı mavisi, görsel `select`, "Choose File" dosya seçicisi, stok arama,
  "Ara", Pexels bağlantısı); koyu temada düzenleyici açık kalıyordu; sağ panel 15 px kesik (sayfa 937 px > 900 görünür
  alan: üst şerit Puck'ın `100dvh`'sine ekleniyor, dikey kaydırma çubuğu); hazır tema ızgarası ve iki adım adı taşıyor.

## Kararlar

- **Seçim kutusu:** ortak alan sınıfında (`ui/Alan.tsx`) yalnız `select` `h-9` (`[&:is(select)]:h-9`). Reddedilen:
  her alana sabit yükseklik (textarea da bu sınıfı alabilir) · `appearance: none` + kendi oku (gereksiz yeniden çizim).
- **Meta/TikTok alt satırı:** büyük "kaydet" (44 px, Ayarlar/Yasal/Bağlam/Meta/TikTok'ta ortak sayfa kalıbı) kalır;
  satırdaki diğer düğmeler (`[&_button]:min-h-11`) ona eşitlenir. Reddedilen: kaydeti küçültmek (beş sayfadaki kalıpla çelişir).
- **Puck Türkçe:** `dictionary` (0.23'ün bütün anahtarları, `sozluk.ts`) + Türkçe adlı `viewports` (genişlikler
  varsayılanla aynı). "%{zoom} (oto)": Puck'ın kutusu "(Auto)" genişliğinde, "(otomatik)" kesiliyordu.
- **Puck renkleri:** Puck'ın bütün renkleri gri/mavi ölçeklerinden türüyor; ölçekler `duzenleyici.css`'te panelin
  renkleriyle yeniden tanımlandı, koyu tema `html:root[data-tema="koyu"]`. **Yalnız `--puck-*` / `--dz-*` adları** —
  panelin `--metin` gibi adları `huni.css`'le aynı ve Puck stilleri önizleme çerçevesine kopyalıyor (P3), huniyi ezerdi.
  Önizleme zemini her temada beyaz (`--puck-canvas-preview-color-bg`). `accent-color` yalnız `.puck-kok`'ta.
  Reddedilen: panelin `stil.css`'ini düzenleyicide açmak (P3 yalıtımı bilerek kapatıyor) · Puck bileşen sınıflarını
  (`._PuckHeader_…` gibi karma adlar) ezmek (sürümde değişir).
- **Üst çubuk ve kendi öğelerimiz:** düğmeler panel düğmesi gibi (hap, 13 px, ana = vurgu mavisi; eski yeşil
  `#1a7f37` gitti), "Sürümler" özeti düğme, geri bağlantısı soluk; AI paneli, bildirim ve rozetler değişkenlerle
  (koyu temada beyaz kutu kalmadı). Yazı `nowrap` — 1280 px'te "Yayındakiyle aynı" ve "Önizle ↗" kırılıyordu (ölçüldü).
- **Yükseklik:** Puck `height="calc(100dvh - var(--dz-ust-yukseklik))"`, üst şerit 40 px sabit (telefonda geri
  bağlantısı 36 px'lik hedef). Sayfa 900 px, kaydırma yok, sağ panel kesilmiyor.
- **Özel alanlar** (`yapilandirma.tsx`): `dz-alan` / `dz-dugme` / `dz-baglanti`; dosya seçici "Görsel yükle"
  düğmesinin içinde, gerçek `<input type=file>` yerinde ve görünmez (`dz-gizli`, klavye ve ekran okuyucu aynı).
  Hazır tema ızgarası `repeat(auto-fill, minmax(88px, 1fr))` (sabit 3 sütun çekmece açıkken dar sağ panelde taşıyordu).
- **Adım adları:** "Ödeme (son adım)", "Ödeme sonrası (en çok bir)" — 27 karakterlik ad sığıyor, bunlar kesiliyordu.
  Reddedilen: kenar çubuğunu genişletmek (Puck'ta duyarlı 186–320 px ve kullanıcı sürükleyebiliyor) · çekmece
  yazısını 14 px yapmak (zaten 14 px'ti — eklenip ölçülünce etkisiz çıktı, geri alındı).

## Ekran döngüsüne eklenenler (`core/bin/panel-ekran.php`)

`duzenleyici` sayfası + `duzenleyici-bloklar` durumu (çekmece açık; Puck kimi zaman kapalı açıyor — ilk "taşma yok"
sonucu kapalı çekmeceden geliyordu). Ölçüm: `esitsizSatirlar` (aynı satırda kutulu denetim yüksekliği / yazı boyu),
`ingilizce`, `hamDenetimler`, `zeminler` (düzenleyicide üst, sağ panel, önizleme), `kirilanDugmeler`. Kök `main`
yoksa `body`. Yanlış pozitifler ayıklandı: kenarı 0 olan `outset` · kapalı `<details>` içeriği (`checkVisibility`) ·
çerçevesiz simge düğmesi (Puck'ın geri al 29 / panel düğmesi 32 px) · simge/renk noktası satır sayılmaz · alanın içinde
kayan uzun değer taşma değil.

## Kırma (ekran ölçümüyle; 12 deneme — K1–K11 + K10b —, hepsi sonunda kırmızı)

K1 `select` `h-9` kaldır → Huniler 38≠36 · K2 Meta satır eşitlemesi kaldır → 36≠44 · K3 `dictionary` kaldır →
11 İngilizce yazı · K4 `height` kaldır → sayfa 940 px · K5 koyu blok bozuk → zeminler beyaz · K6 `select` sınıfsız →
ham `select` · K7 `dz-gizli` kaldır → ham `input[file]` · K8 ızgara 3 sütun → "Okyanus" taşar · K9 uzun ad → taşar ·
K11 önizleme zemini kaldır → koyu temada önizleme `rgb(28,28,30)`.
**K10 (`nowrap`) ilk iki denemede TUTMADI** (1440 ve 1280'de eşitsizlik 0): ekranda 1280 px'te kırılma vardı — iki
satır 13 px yazı 32 px'lik `min-height`'ın içinde kaldığı için yükseklik ölçümü göremiyordu (testi suçla). Ölçüme
`kirilanDugmeler` eklendi → K10 (düğme) "Önizle ↗", K10b (rozet) "Yayındakiyle aynı" ile kırmızı. Bu sırada tuzak §63
(geri alınan kaynak, derlenmemiş `dist`) bulundu; kırma betiği geri almadan sonra derliyor.
TikTok'taki satır eşitlemesi ekran döngüsünde çizilmiyor — yalnız Meta'daki eşi (K2) kırıldı.

## Sonuç ölçümü

`bin/panel-ekran` tam döngü (2026-11-23, yerel macOS + Docker, 15 sayfa + 4 durum × 2 tema × 2 genişlik = 60 ekran):
eşitsizlik 0, İngilizce 0, ham denetim 0, yatay kayma 0; kalan: Rapor ekseni (Ç163), Puck kategori başlığı
"Ekran blokları (Ekran adımının içine)" iki satır (başlık, kabul), telefonda Puck'ın kendi küçük hedefleri (Ç164).
`bin/kontrol` EXIT=0 (çekirdek 833 test, panel testleri).
