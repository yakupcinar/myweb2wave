# P7b — Rapor sayfası taslakla bire bir

**Tarih:** 2026-11-11 · **İstek (kullanıcı):** her sayfa taslağa olabildiğince benzesin. Sıra: Genel +
Aboneler → **Rapor** → Uygulama → diğerleri. Günlük grafikler altta kalsın.

## Ne yapıldı

Sayfa bölümlere ayrıldı. Her bölümde 20 px başlık, kısa açıklama ve kart var; aralıklar taslaktaki gibi
28 px. Sırası:

1. **Özet:** dört kart, önceki aynı uzunluktaki döneme göre.
   - Gelir.
   - Yeni abone (deneme dahil).
   - Ziyaretten aboneliğe: değişim **yüzde puanı** olarak.
   - Ziyaretçi başına gelir.

   Önceki dönem boşsa "Önceki dönemde veri yok" yazar. Para birimi tek değilse ya da iki dönemde
   farklıysa "Karşılaştırma yok" yazar; farklı birimler karşılaştırılmaz.
2. **Ziyaretten aboneliğe:** adım × Reklamdan / Organik / Toplam tablosu. Eski iki sayım kartı ve özet
   şeridi yerine geldi.
3. **Anket:** huni başına şeritler. Şerit ilk adıma göre, düşüş bir önceki adıma göre ("−%33").
   **Cevaplar** ayrı bölüm.
4. **Kaynaklar:** Kaynak (ortam ve reklam rozetiyle), Kampanya, Ziyaret, Ödeyen, Dönüşüm, Gelir;
   sayfalı.
5. **Reklam harcaması:** veri varsa Meta tablosu. Yoksa boş kart ("Bu dönemde Meta harcaması yok") ve
   "Meta hesabını bağla" → Uygulama.
6. **Altta:** Günlük grafikler, Kohort geliri, Reklam ölçümü izni (üç kart), Ziyaretler, Serbest
   yanıtlar.

Diğer değişiklikler:
- **Arka uç:** `PanelRaporu.gelir` ve `PanelRaporu.onceki` (önceki dönemin sayımları ve geliri) eklendi.
  `Rapor::sayimlar`'a `kaydir`, `Rapor::gelir` eklendi; pencere tek sabitte (`PENCERE`).
- **Biçim:**
  - Yüzde panelin tamamında Türkçe yazımla: "%33,3" (önceden "33,3 %").
  - Binlik ayraç: `sayi()` → "2.950".
  - Tablo hücre dolgusu 12 × 18 px; sayılar sağa yaslı.
- **Dönem seçici:** "Son 30 gün" yerine "30 gün" (taslak).

## Neden böyle

- **Oran değişimi puan.** Taslak "↓ %0,6" yazıyordu; %11,8 → %11,2 ise "%5 düştü" de "0,6 puan" da
  doğru, ama `%` iki anlama gelir. "puan" yazıldı.
- **Gelirin tanımı Kaynaklar'la aynı:** penceredeki ziyaretlerin bugüne kadar tahsil edilmiş faturaları,
  iadeler düşülmüş. Böylece Kaynaklar'ın gelir sütunu toplanınca Özet'teki gelir çıkar. Reddedilen:
  dönemde ödenen faturalar. Ziyaret kohortuyla karışır; önceki dönemin geliri bugün de artmaya devam
  eder, bu kabul edildi.
- **Meta boş kartı "bağlı değil" demiyor.** Panel, hesabın bağlı olup olmadığını bilmiyor (Ç121).
  Bağlı hesapta harcamasız bir dönem de boş görünür. Taslağın cümlesi yanlış olabilirdi.
- **Kaynaklar'da ortam ve dönüşüm kaldı.** Taslakta yok, ama bilgi kaybı olmasın; az yer tutuyorlar.
- **Ayrıntı bölümleri (kohort, izin, ziyaretler, serbest yanıtlar) taslakta yok.** Mevcut özellikler,
  bu yüzden altta ve aynı bölüm diliyle duruyorlar.

## Kırma denemeleri

| # | Bozulan karar | Sonuç |
|---|---|---|
| R1 | Değişim oku ters | Rapor.test 2/12 |
| R2 | Oran değişimi puan değil yüzde | 2/12 |
| R3 | Para birimi denetimi yok | 1/12 |
| R4 | Anket düşüşü ilk adıma göre | 1/12 |
| R5 | Toplam sütunu yalnız reklam | 1/12 |
| R6 | Meta bağlama bağlantısı yanlış yere | 1/12 |
| R7 | Önceki pencerenin üst sınırı yok | testOncekiDonemVeGelir |
| R8 | Gelirden iade düşülmez | testOncekiDonemVeGelir |
| R9 | Önceki dönem kaydırılmaz | testOncekiDonemVeGelir |

9/9 kırmızı.

## Ölçüm

- `bin/kontrol` YEŞİL. Yolda §58 yine görüldü (`Rapor::kohort` #L347 → #L353, `--duzelt` ile).
- Testler: çekirdek +1 (testOncekiDonemVeGelir); panelde Rapor 12. Özet beklentileri elle hesaplandı
  (§3).
- **Tarayıcıda bakılmadı (KOŞULMADI):** Chrome eklentisinin bağlantısı koptu. Panel girişi kullanıcıda
  olduğu için başsız tarayıcıyla da açılamadı. Görsel karşılaştırma kullanıcıya bırakıldı ya da
  eklenti dönünce yapılacak.
