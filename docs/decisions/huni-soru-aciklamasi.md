# E2.4c — soru ekranında isteğe bağlı açıklama — 2026-10-22

## Ne yapıldı

Puck soru ayarına boş bırakılabilen `aciklama` alanı eklendi. Doluysa ortak
Soru bileşeni bunu soru başlığının altında gösterir; boşsa ek paragraf
oluşturmaz. Huni tanımı, OpenAPI sözleşmesi ve çekirdek doğrulaması alanı
uyumlu biçimde taşır; en çok 500 karakter kabul edilir. Bu, ziyaretçinin
yanıt yazdığı serbest metin alanı değildir.

## Neden böyle

Bu küçük içerik seçeneği soru ekranını ek bir adım eklemeden zenginleştirir
ve kullanıcı her ankette kullanıp kullanmamayı seçer. Yanıt verisi
toplamadığından cevap uçlarında, raporlarda veya veritabanı şemasında
değişiklik gerekmez. Serbest yanıtı şimdi eklemek, kişisel bilgi içerebilen
metnin saklanması ve raporlanması kararlarını da gerektirirdi; bu kapsamdan
ayrı tutuldu.

## Kırma denemeleri

| Bozulan | Sonuç |
|---|---|
| Çekirdek açıklama sınırı 500'den 1000'e çıkarıldı | 🔴 `testSoruAciklamasiIstegeBagliVeUzunluguSinirli` — 501 karakter reddedilmedi |
| Ortak bileşende açıklama paragrafı kaldırıldı | 🔴 bileşen testleri — görünürlük, XSS kaçış sayısı ve CSS sınıfı iddiaları düştü |
| Puck → tanım dönüşümünde açıklama alanı kaldırıldı | 🔴 kayıpsız dönüşüm testi — açıklama kayboldu |

Her kırma öncesi dosyanın yedeği alındı, değişen satır doğrulandı, ilgili test
kırmızı görüldü ve yedekten geri alındı.

## Ölçüm

- `bin/kontrol > /tmp/log5 2>&1; echo EXIT=$?` — yerel Docker ortamında
  **EXIT=0**. Çekirdek 193 test / 1199 assertion; editör 22, ortak bileşenler
  22, huni 38 test. OpenAPI tipleri güncel, PHPStan, tip kontrolleri,
  derlemeler ve 90 KB gzip bütçesi geçti.
- Çekirdek testinde açıklamasız eski tanım, geçerli açıklama, 501 karakter ve
  sayı türündeki geçersiz değer ayrı ayrı ölçüldü.
- Puck ↔ tanım dönüşümü ve Puck önizlemesi = huni DOM testleri açıklamalı
  soruyu kapsıyor. Oynatıcı testinde açıklama çoklu seçimli soruyla birlikte
  görüntülendi.
- Yerel `GET /panel/giris` ve `GET /keepchat/` istekleri HTTP 200 döndürdü.
  Work ayarlarında yerel tarayıcı otomasyonu kapalı olduğundan Puck paneli
  elle görsel olarak kontrol edilmedi.

## Açık

Bu alan yalnızca editörün verdiği açıklama metnidir. Serbest metin yanıtı,
cevap verisi saklama ve raporlama tasarımı ayrı iş olarak kalır.
