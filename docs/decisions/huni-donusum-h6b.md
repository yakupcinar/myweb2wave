# Blok: H6b — sunucu satın alma dönüşümleri — 2026-10-23

## Karar

Stripe `checkout.session.completed` olayı `payment_status=paid` ise, aynı
ödeme oturumunun huni ziyaretiyle bağlı olduğunu ve `izin_at` değerinin dolu
olduğunu doğrula. Meta Conversions API ve TikTok Events API için ayrı kuyruk
kayıtları üret. Olay kimliği Stripe Checkout Session ID'dir; iki ağ aynı
kimliği kullanır, böylece H6c tarayıcı olayı da aynı değerle tekilleştirilebilir.

Kuyrukta yalnız ilgili ağın pixel ID ve erişim anahtarı yapılandırılmışsa kayıt
oluşturulur. Ağlar yapılandırılmamışsa ödeme akışı ve çekirdek kayıtları devam
eder; olay gönderilmez. Meta Graph API sürümü biçimce geçerli olmalıdır.
`META_TEST_EVENT_CODE` ve `TIKTOK_TEST_EVENT_CODE` verilirse yalnız o ağa giden
test olayı kodu eklenir.

## Veri akışı ve saklama

Checkout başlatılırken istemci IP'si ve User-Agent yalnız izinli ziyaret için
ödeme oturumuna yazılır. Tamamlanmış ve ücretli webhook'ta izinli ziyaret
kontrolünden sonra ağ yükü hazırlanır. Ödeme oturumundaki IP/UA hemen temizlenir;
gönderilmeyi bekleyen JSON gövdesinden ise başarılı teslimde, 10 başarısız
denemede veya yedi günlük sürenin dolmasından sonra çıkarılır. Yeniden deneme
aralığı 30 saniyeden başlayıp bir saate kadar artar. Hata kayıtları sağlayıcı,
Stripe oturum kimliği ve sınırlı hata sınıfını tutar; token veya tam yanıt gövdesi
tutmaz.

Meta'ya `Purchase`, TikTok'a standart `CompletePayment` gönderilir. Değer Stripe'ın küçük para
biriminden para biriminin ondalık kuralına göre çevrilir (USD `1299` → `12.99`,
JPY `1299` → `1299`). IP, User-Agent ve varsa `fbclid` / `ttclid` eşleşme verisi
yalnız izinli oturumun payload'ına alınır. Ağ yanıtı ödeme webhook'unu
durdurmaz; kuyruk cron'u başarısız gönderimleri tekrar dener.

## Elenen seçenekler

- **Webhook içinde doğrudan tek sefer gönderim:** geçici ağ kesintisiyle olayı
  kaybettirirdi. Kalıcı kuyruk ve artan bekleme aralıklı yeniden deneme seçildi.
- **İzin durumuna bakmadan IP/tıklama kimliği aktarmak:** H6a onayıyla çelişirdi.
  Hem checkout bağlama hem gönderim sorgusu izin şartı uygular.
- **Ham kullanıcı eşleme verisini uzun süre saklamak:** yeniden deneme için
  gereken payload ile sınırlandı; başarılı/son/yaşlı kayıt yükleri silinir.

## Doğrulama

- Ödenmiş, izinli ziyarette iki sağlayıcıya aynı `event_id` gider; IP ve TikTok
  tıklama kimliği payload'da bulunur, teslimden sonra DB payload'ı boşalır.
- İzin vermeyen ziyaret için payload veya ağ isteği oluşmaz.
- Tam kontrol (Docker, 2026-10-23): `bin/kontrol` EXIT=0; çekirdek 202 test / 1278 assertion, PHPStan, editör/bileşen/huni testleri ve derlemeler yeşil.
- Kırma 1: ziyaret sorgusundan `izin_at IS NOT NULL` çıkarıldı; `testIzinsizZiyaretReklamAglarinaGonderilmez` kırmızı oldu (2 istek, 0 bekleniyordu); kaynak yedekten geri alındı ve izin koşulu doğrulandı.
- Kırma 2: paid koşulu geçici olarak `true` yapıldı; `testOdenmemisOturumReklamAglarinaGitmezVeEslemeBilgisiSilinir` kırmızı oldu (2 istek, 0 bekleniyordu); kaynak yedekten geri alındı ve `payment_status=paid` koşulu doğrulandı.
- Gerçek Meta/TikTok hesapları, tokenlar ve test olay kodları erişilebilir
  olmadığı için gerçek ağ alımı doğrulanmamıştır. Bu anahtarlar depoya konmaz;
  üretim `deploy/.env.uretim` dosyasından yapılandırılır.

## Açık sınırlar

- H6a hukuk metni ve gizlilik politikası bağlantısı hâlâ açık (Ç56). İzin
  geri alma aktif ziyarette var; önceki sekmelerdeki ziyaretleri birleştirmez
  (`huni-izin-geri-alma.md`). H6b tamamlanması canlı reklam için hukuki onay değildir.
- H6c tarayıcı pikselleri eklendi (`huni-donusum-h6c.md`). Aynı event ID ile
  tarayıcı ve sunucu tekilleştirmesi gerçek hesapta doğrulanmalı.
- Başarılı test modu alımı ve Meta/TikTok panelindeki olay kalitesi anahtarlar
  girildikten sonra doğrulanmalı.

## Kaynaklar

- [Meta Conversions API](https://developers.facebook.com/docs/marketing-api/conversions-api/)
- [TikTok Events API](https://business-api.tiktok.com/gateway/docs/index?doc_id=1771100865818625)
- [Stripe desteklenen para birimleri ve küçük birimler](https://docs.stripe.com/currencies)


**2026-10-26 (L3, Ç59):** ödenmeyen oturumun IP/UA'sı `checkout.session.expired`'da ve 48 saatlik cron'da silinir — `odeme-ip-temizligi.md`.
