# BR2 — "Yapay zekâ için kopyala" (2026-11-20)

Kullanıcı (2026-11-20): yeni uygulamayı doğru ve hızlı bağlamak için, uygulamanın kod tabanında çalışan yapay zekâ
panelden istenenleri görsün.

## Karar

- Talimat **çekirdekte** üretilir (`App\Uygulama\EntegrasyonRehberi::metin`), uç `GET /panel/api/uygulamalar/{app}/rehber`
  → `{metin}` (Markdown). Panel › Uygulama › "Uygulama bağlantısı" altında **Yapay zekâ için kopyala** panoya yazar.
  BR3'teki MCP aracı aynı sınıfı çağıracak — tek kaynak.
- İçerik: uygulamaya özel uçlar (`/apps/<app>/…`), çekirdek adresi (`DEFAULT_URI`), şema (panel › Ayarlar; boşsa
  "önce girilmeli" uyarısı), akış sırası, önbellek, RevenueCat (`Purchases.logIn(kisi)`, aktarım kapalıysa `/hak`),
  App Store kuralları, test ve "bitti" ölçüsü.
- **Gizli değer yok:** anahtar `<UYGULAMA_ANAHTARI>` yer tutucusu — metin başka depoya, sohbete yapıştırılır.
- **"Şu an eksik" ölçülür:** BR1'in `BaglantiDurumu` sonucundan; elle yazılmaz.
- Kaynak metin `docs/uygulama-entegrasyonu.md` — biri değişince öteki de (kural 7).
- Reddedilen: talimatı `/baglanti` yanıtına alan olarak eklemek (BR1 sözleşmesini ve testlerini değiştirirdi; metin
  yalnız düğmeye basınca gerekir) · belgeyi olduğu gibi kopyalamak (yerel yollar, karar numaraları, uygulamaya özel
  değil) · panelde metni ön yüzde kurmak (MCP aracı aynı metni üretemezdi).

## Test ve kırma

`EntegrasyonRehberiTest` 4 test (sözleşme yanıt doğrulamalı) · `Ayarlar.test` +1. Kırma 4/4 kırmızı: K1 gerçek test
anahtarı metne girer · K2 bağlama hep "eksik" · K3 şema ayardan okunmaz · K4 düğme panoya yazmaz.

İlk kontrol KIRMIZI (24 test, 2026-11-20): yeni test `uygulama_ayarlari`'nı TRUNCATE ediyordu — o tablo göç
tohumudur (keepchat şeması), giriş bağlantısı ve e-posta testleri 500 aldı. Düzeltme: test satırı yedekleyip
`tearDown`'da geri yazar; test DB'sine tohum satırı elle geri kondu.
