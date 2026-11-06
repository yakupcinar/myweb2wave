# E2.4b — Huni serbest metin sorusu — 2026-10-23

## Karar

- Serbest yanıt, `metin` soru türü olarak seçilebilir; isteğe bağlıdır varsayılan
  olarak, düzenleyici zorunlu da yapabilir. Yanıt sınırı 500 karakter.
- Ham metin ayrı `huni_metin_cevaplari` tablosunda tutulur; seçenek cevapları ve
  toplu sayımlara karışmaz. Aynı ziyaret/soru için son yanıt öncekinin yerini alır.
- Yönetici panelinde, seçilen rapor aralığı için ayrı bölümde en yeni 200 yanıt
  görünür. Yanıt metni HTML olarak işlenmez. Dışa aktarma ve arama ilk sürümde yok.
- Ham yanıtlar ziyaretten 90 gün sonra günlük görevle silinir. Yedekler 14 günlük
  döngüde yenilendiğinden silinen veri yedeklerde en çok yaklaşık 105 güne kadar
  kalabilir. Ziyaret kimliği eskiyse yanıt okuma/silme sınırı yine 90 gündür.
- Alan düzenleyicide isteğe bağlı/zorunlu ayarı görünür. Serbest yanıt reklam
  çerezi iznine bağlanmaz; reklam izni yalnız mevcut piksel ve tıklama ölçümünü
  yönetir.

Bu teknik saklama tercihi tek başına hukuki uygunluk veya aydınlatma metni
anlamına gelmez. Ürün sahibi, yayına almadan önce uygun bilgilendirmeyi ve
hassas bilgi istememe metnini ürünün kullanıcı akışında belirlemelidir.

## Uygulama

- OpenAPI ve üretilen tipler `cevapMetni` alanını; soru tanımı `secimTuru=metin`
  ve `yanitZorunlu` alanını taşır.
- Çekirdek 500 karakteri, NUL karakterini ve seçenek yanıtıyla çakışmayı doğrular.
  Metin ayrı tabloda saklanır; ziyaret silinince yabancı anahtar metni de siler.
- Puck editörü, önizleme ve React oynatıcı serbest yanıtı destekler. Oynatıcı
  yanıtı ancak Devam et'e basıldığında kaydeder; zorunlu boş yanıtla ilerlemez.
- Panel raporu ayrı ham yanıt tablosunu okur ve Twig kaçışlamasıyla gösterir.
  Temizleme komutu `app:huni-metin-cevaplari-sil` günlük cron'a eklenir.

## Doğrulama

- Çekirdek: hedefli `HuniTest`, `AtifTest`, `PanelTest` ve temizleme komutu testi.
- Editör, ortak bileşen ve oynatıcı: serbest metin dönüşümü, ayar, önizleme,
  uzunluk, isteğe bağlı/zorunlu ilerleme ve istekte ayrı `cevapMetni` alanı.
- Göç: `Version20261023120000` test veritabanına uygulandı.
- `bin/kontrol`: yeşil; 198 çekirdek testi / 1.239 kontrol, editör 19,
  ortak bileşen 11, oynatıcı 37; tip, biçim, derleme ve boyut kontrolleri geçti.
- Gerçek yerel HTTP: sağlık ve giriş sayfası 200, yönetici paneli oturumsuzken
  302; serbest yanıt POST 204 ve yanıt ayrı tabloda görüldü. Deneme ziyareti ve
  cevap satırı sonrasında silindi. Yerel geliştirme veritabanına göç uygulandı.
- Kırma 3/3: 500 karakter sınırı 600'e çıkarılınca doğrulama testi 422 yerine
  500 gördü (ayrı DB kısıtı da korudu); saklama 90'dan 900 güne çıkarılınca
  temizleme testi eski yanıtı buldu; isteğe bağlı metin düğmesinden zorunluluk
  kontrolü kaldırılınca bileşen testi düştü. Her mutasyon geri alındı.
- CI henüz push sonrasında doğrulanacak.
