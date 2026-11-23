# P8b — Planlar ve Yasal'ın uzun sayfaları (2026-11-23)

Kullanıcı (2026-11-23): "Planlar ve Yasal uzun sayfalarını da yap." Ölçüm `bin/panel-ekran` (`bolumYukseklikleri`).

## Ölçüm (önce)

| Sayfa | 1440 | 390 | Uzunluğu yapan |
|---|---|---|---|
| Yasal | 2.499 px | 3.008 px | "Metinler" 1.885 px: dört belge kartı alt alta, her biri 14 satırlık metin kutusu |
| Planlar | 1.247 px | 1.962 px | uzun değil (≈1,4 ekran); asıl kusur dağınıklık: para birimleri ve teklif ayrı kartlarda, ikisi de ayrı "Plan: seç" |

## Kararlar

- **Yasal:** belgeler sekme (P7i Rapor gibi, adreste `?belge=`; bilinmeyen değer ilk belge). Kartlar **gizlenir,
  sökülmez** — sekme değişince yazılan kaydedilmemiş metin kalır (Rapor'da form yok, burada var). Sekme sınıfı
  Rapor'dan `ui/sekme.ts`'e taşındı (iki sayfada aynı biçim, tek yer — kural 7). Reddedilen: kartları katlanır yapmak
  (dört başlık yine alt alta, açık olan uzatır) · sekmede kartı sökmek (metin kaybolur; Y1 ölçtü).
- **Planlar:** para birimleri ve teklif tek "Plan ayarları" kartında, **tek plan seçimi** ikisini de kurar.
  Yükseklik ≈ aynı (1.247 → 1.270 px): kart başlığı gitti ama ara başlık + açıklama geldi — bu bir düzen kararı,
  kısaltma değil. Reddedilen: ayarları plan satırının içine almak (965 satırlık sayfanın çoğunu yeniden yazmak) ·
  Planlar'da sekme (sayfa uzun değil; bölmek bir şey kazandırmaz).

## Test ve kırma (5/5 kırmızı)

`Yasal.test` +1: iki belgeli veride yalnız seçili belge görünür, sekme `aria-current`, yazılan metin geri dönünce
yerinde. Y1 kart sekme değişince yeniden kurulur → metin "TR taslak" · Y2 `hidden` yok → iki belge birden ·
Y3 `?belge=` yok sayılır → sekme açılmaz (tip düzeltmesinden sonra yeniden koşuldu, yine kırmızı).
`Planlar.test` +1, iki test ortak seçime taşındı: sayfada tek "Plan" seçimi, seçim teklif düğmesini açar ve para
birimi alanlarını getirir. P1 seçim teklifin planını kurmaz → 2 test düşer · P2 para biriminin planını kurmaz → 2 test.

## Sonuç ölçümü

Yasal 1.159 px (1440) / 1.425 px (390); Planlar 1.270 / 2.015; Rapor değişmedi (1.473). Üçünde eşitsizlik, taşma
(Rapor ekseni hariç, Ç163), kırılan düğme, yatay kayma yok. `bin/kontrol` EXIT=0 (2026-11-23, yerel macOS + Docker;
833 çekirdek testi) — önce iki koşu `core-kur` kırmızı: tuzak §37 iCloud kopyaları tekrarladı, `de~` silindi.
İlk koşuda `panel-tip` kırmızıydı (`?belge=` dizgesi belge adı birleşimine atanıyordu) — `find` ile düzeldi.

## Canlıda (2026-11-23)

`/health` sürümü `c3297cbbb973` (CI success, `dagit.sh` + dış ölçüm). P8 ilk kısmı (`9ec69ab652ee`) aynı gün dağıtıldı. Canlı panelde ekrana bakılmadı.
