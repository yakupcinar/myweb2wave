# BR1 — Panel › Uygulama › "Uygulama bağlantısı" (2026-11-20)

Kullanıcı (2026-11-20): yeni uygulamalar doğru ve hızlı bağlansın. Ders KeepChat'ten: bağlama kodu 19 Ekim'de
yazılmıştı (`7bdc46a`) ama üretimde kapalıydı (Ç158) ve bunu gösteren hiçbir yer yoktu.

## Karar

- "Canlıya geçiş" gibi (P7c): her adım **çalışan sistemden ölçülür**, elle işaretlenmez. Uç
  `GET /panel/api/uygulamalar/{app}/baglanti` (`BaglantiDurumu`); anahtarların kendisi yanıtta yok.
- Adımlar: uygulama anahtarı sunucuda · **uygulamanın sunucusu geçerli anahtarla en az bir istek attı** · ilk bağlama
  (sayı, son) · giriş bağlantısı kullanıldı · RevenueCat aktarımı açık ve gönderilmiş (bekleyen sayısıyla) ·
  mağaza okuma (G2) — **isteğe bağlı**, sayaçta yok (yalnız App Store'da da satan uygulama).
- Yeni ölçüm: `uygulama_istekleri (app, son_istek_at)` — `UygulamaYetkisi::izinli` geçen istekte yazar, **dakikada
  en çok bir kez** (`ON CONFLICT … WHERE son_istek_at < now() - 1 dk`). Tek yer: uygulama anahtarlı bütün uçlar oradan
  geçer. Yanlış anahtar yazmaz.
- Reddedilen: hak ucunun çağrı günlüğü (her istekte satır — gereksiz büyür; panel yalnız "geldi mi, en son ne zaman"
  ister) · elle işaretlenen liste (KeepChat'te tam bu yanılgı oldu).
- Panelde aynı `Adim` bileşeni (tip parametreli oldu; Canlıya geçiş değişmedi).

## Test ve kırma

`BaglantiDurumuTest` 4 test (sözleşme yanıt doğrulamalı) · `Ayarlar.test` +2. Kırma 10/10 kırmızı: K1 yanlış anahtar
da yazar · K2 dakika sınırı yok · K3 hiç güncellemez · K4 başka uygulamanın bağlantısı sayılır · K5 kullanılmamış
giriş jetonu sayılır · K6 bekleyen gönderilmiş sayılır · K7 anahtar hep tamam (**ilk denemede tutmadı** — test ortamında
anahtar hep dolu; boş anahtar testi eklendi, sonra kırmızı) · K8 isteğe bağlı sayılır · K9 son istek gösterilmez ·
K10 bölüm yok.
