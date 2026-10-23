# H6a.1 — reklam ölçümü iznini geri alma

## Ne yapıldı

Huni altındaki **Gizlilik ayarları** bağlantısı daha önce verilmiş tercihi
yeniden açar. Kabul edilmiş izin Reddet ile geri alındığında tarayıcı tercih
değerini ancak çekirdek silme isteği başarılı olunca `hayir` yapar; hata olursa
şerit açık kalır ve yeniden denemeye izin verir. Çekirdek bu ziyaretin iznini,
`fbclid` / `ttclid` değerlerini, IP ve User-Agent bağlamını temizler ve henüz
gönderilmemiş reklam dönüşüm payload'larını kuyruktan çıkarır. Stripe webhook'u
ziyaret satırını kilitleyerek geri alma ve yeni dönüşüm kuyruğa alma işlemlerini
sıralar.

## Karar ve sınır

- Geri alma için `DELETE /huni/ziyaretler/{ziyaret}/izin` kullanılır; yeni bir
  kalıcı tarayıcı kimliği veya ziyaret listesi saklanmaz. H6a kararı
  `localStorage`'da yalnız tercih tutuyordu.
- Geri alma aktif huni ziyaretini kapsar; daha önce başka sekmelerde açılmış
  ziyaretleri bu tarayıcıyla birleştirmez. Daha geniş geçmiş eşlemesi, yeni bir
  tarayıcı tanımlayıcısı gerektireceğinden ayrı ürün/mahremiyet kararıdır.
- Sağlayıcıya gönderilmiş veya gönderilmek üzere ağ isteğine çıkmış olay geri
  çağrılamaz. Bu uç, henüz kuyrukta olan paylaşımı iptal eder ve bu ziyaretten
  sonraki ölçümü kapatır.

## Doğrulama

- Hunide tercih akışı **39 test**; geri alma isteği ve ağ hatasında yeniden
  deneme dahil.
- Çekirdek atıf uçları **26 test / 127 doğrulama**; DELETE yanıtı OpenAPI
  sözleşmesi ve CORS ön kontrolü dahil.
- Kırma: kuyruk payload temizliğini etkisizleştirince geri alma testi kırmızı
  oldu; kaynak geri alındı.
- Tam `bin/kontrol`: **yeşil**, çekirdek **205 test / 1.294 doğrulama**;
  OpenAPI tipleri, CORS ve tüm derlemeler dahil.
- Görsel tarayıcı kontrolü bu ortamda kapalı; kontrol gerçek uç yanıtlarını
  Symfony istemcisi ve CORS ön kontrol istekleriyle ölçüyor.

## Açık kalan Ç56

Hukukça doğrulanmış bildirim/gizlilik politikası, reklam sınıflamasının
iyileştirilmesi, izin oranı raporu ve farklı sekmelerdeki eski ziyaretleri
birlikte geri alma açık kalır. Bunlar canlı reklam öncesi ayrıca ele alınır.
