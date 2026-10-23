# Huni serbest metin sorusu — kapsam değerlendirmesi — 2026-10-23

## Bugünkü akışın ölçümü

- `POST /huni/ziyaretler/{ziyaret}/ilerleme` yalnız `cevap` (tek seçenek kimliği)
  veya `cevaplar` (en çok 8 seçenek kimliği) kabul ediyor. Çekirdek kimlik biçimini
  doğruluyor; serbest içerik kabul edilmiyor.
- `huni_cevaplari` satırı ziyaret + soru + seçenek kimliği başına tutuluyor.
  Panel raporu bu kimlikleri sayıyor; tek tek ziyaret/cevap dökümü sunmuyor.
- Huni oynatıcısı cevap seçilince ilerleme uç noktasına hemen yazıyor. Bu nedenle
  serbest metin türü eklemek, sadece yeni bir görsel alan değil; yeni bir veri
  saklama ve panel erişimi anlamına gelir.
- İzin şeridindeki Kabul/Reddet, mevcut kodda tıklama kimliklerini ve reklam
  piksellerini yönetiyor. Serbest yanıtı bu reklam iznine bağlamak doğru kapsam
  değil; anket cevabının hangi temelde ve ne zaman saklanacağı ayrıca
  belirlenmeli.

## Rakiplerden çıkan ürün yönü

2026-10-23 tarihli küçük araştırmada Web2Wave ve FunnelFox serbest cevapları
kişi/oturum düzeyinde API veya webhook ile erişilebilir yapıyor; Typeform ham
cevapları tek tek gösteriyor, ancak açık metni toplu Response Summary
dışa aktarımından ayrı tutuyor. İncelenen kaynaklarda bu ürünler için doğrulanmış
genel saklama süresi bulunamadı. Kaynaklar ve ayrıntılar:
`huni-serbest-metin-rakipleri.md`.

Bu bulgulara göre öneriyi daraltıyorum: cevap ayrı tabloda tutulmalı, seçenek
analitiğine karışmamalı ve yanıt panelde ayrı, erişimi korunan görünümde
sunulmalı. Dışa aktarma ilk sürüme girmesin.

## Önerilen ilk sürüm

1. Yeni soru türü `metin` olsun; isteğe bağlı bir ziyaretçi yanıtı alanı olarak
   tasarlansın. Mevcut tekli/çoklu seçenek türleri değişmesin.
2. Cevabı mevcut `huni_cevaplari` tablosuna koymayalım. Bu tablo kategorik,
   toplu sayım için tasarlanmış. Ayrı `huni_metin_cevaplari` tablosunda ziyaret,
   soru kimliği, yanıt metni ve zaman damgası tutulsun; aynı soruya geri dönüp
   yeniden yanıt verilince son yanıt öncekinin yerini alsın.
3. Girdi için 1–500 karakter sınırı, metin olarak gösterim/kaçış ve istek boyutu
   sınırı uygulansın. HTML kabul edilmesin. Huni tanımında soru metni ve
   açıklama alanındaki mevcut 500 karakter üst sınırıyla uyumlu olsun.
4. Yönetim panelinde ham yanıtlar seçenek sayımlarından ayrı, erişimi korunan
   bir görünümde tek tek gösterilsin. Dışa aktarım ve arama ilk sürümün dışında
   kalsın. Yanıt görünümüne erişim belirlenmeden saklama özelliği yayımlanmasın.
5. Saklama süresi, silme/anonimleştirme, aydınlatma metni ve hassas veri
   toplamama talimatı ayrıca kararlaştırılsın. Reklam çerezi izni bu kararların
   yerine geçmesin. Hukuki uygunluk iddiası bu teknik değerlendirmeden
   çıkarılamaz.

## Karar bekleyenler

Bu değerlendirme araştırma ve ürün önerisidir; saklama süresi ile yanıt
görünümüne erişim kararı hâlâ ürün sahibi tarafından verilmelidir:

- Ham yanıtları varsayılan olarak ne kadar süre tutacağız? Öneri: süre
  belirlenmeden veri toplamayı açmamak.
- Yanıtları hangi panel kullanıcıları görebilecek? Bugünkü panel tek yönetici
  hesabı kullanıyor ve bir erişim/silme ekranı yok.

## Sonuç

Serbest metin sorusu; editör/anket alanı, ayrı ham yanıt tablosu ve ayrı panel
görünümü olarak tasarlanmalı. Kodlamaya geçmeden önce saklama süresi ve yanıt
görünümüne kimlerin erişeceği karara bağlanmalı.
