# A31 — TikTok bağlantısı panelden (2026-11-17)

Karar `pre-setup.md` A31 (kullanıcı: "Meta'daki gibi bağlansın"). A30'un TikTok karşılığı, üç blok: **TB1** çekirdek
(bağlantı + dönüşüm + huni pikseli), **TB2** panel ekranı, **TB3** harcama + ROAS.

## Ölçüm (önce)

- TikTok ayarı ortam değişkenindeydi: çekirdek `TIKTOK_PIXEL_ID`, `TIKTOK_ACCESS_TOKEN`, `TIKTOK_TEST_EVENT_CODE`;
  huni derleme anında `PUBLIC_TIKTOK_PIXEL_ID` (web imajı argümanı) → tek Pixel tüm uygulamalara, değişince imaj
  yeniden kurulur. Panel sayfası, "dene", harcama çekişi ve TikTok ROAS'ı yoktu; yalnız dönüşüm gönderimi vardı.
- Dönüşüm yükü kuyruğa alınırken Pixel kodunu taşıyordu (`event_source_id`); webhook testi Pixel'i, test kodunu ve
  `Access-Token` başlığını **hiç** denetlemiyordu (A30 M12'nin karşılığı).
- TikTok belgesi (üçüncü taraf kaynaklar; resmi sayfa okunamadı): Events API anahtarı Pixel ayarlarında üretilir,
  Pixel'e özeldir; Events API anahtarını yan etkisiz okuyan bir uç bulunamadı. Marketing API (harcama) ayrı
  geliştirici uygulaması anahtarı ister.

## TB1 — Ne yapıldı (çekirdek)

- **Göç** `Version20261117120000`: `tiktok_baglantilari` (uygulama başına; Pixel kodu `^[A-Z0-9]{10,40}$`, reklamveren
  `^[0-9]{5,25}$`, test kodu — biçimler DB kısıtında da). Dönüşüm kuyruğu `app`'i A30'dan beri taşıyor.
- **`TiktokBaglantisi`** (`MetaBaglantisi`'nin karşılığı, aynı `Sifreleyici`): `oku`, `panel` (son 4 hane),
  `kaydet` (anahtar alanı yok / `null` = değişmez, `''` = kaldır), `sil`, `dene`, `harcamaHesaplari`.
- **Dene:** dönüşüm — test olay kodu varsa `event/track`'e `ViewContent` deneme olayı (TikTok › Test events'te
  görünür), yoksa istek atmaz ("ayarsiz" + nereden alınacağı); harcama — `advertiser/info` reklamveren adı ve para
  birimi (Marketing API anahtarıyla). TikTok'un mesajından anahtar silinir, sonuna `(kod N)`.
- **Tüketiciler panelden okur:** `DonusumAktarimi` (kuyruğa alma `donusumAcik`; gönderimde **o anki** bağlantı —
  Pixel kodu yükteki eski değerin üstüne yazılır, anahtar başlıkta), `GirisBaglantisiController` (yanıtta
  `tiktokPixelId`), huni `basarili.ts` (Pixel yanıttan).
- **Uçlar** (sözleşmede): `GET|PUT|DELETE /panel/api/uygulamalar/{app}/tiktok`, `POST …/tiktok/dene`.
- **Ortam:** `TIKTOK_*` `.env`, `.env.test`, `deploy/compose.yaml`, `env.uretim.ornek`, `web.Dockerfile` argümanı,
  `deploy/README.md` ve huni `env.d.ts`'den kalktı.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Ayrı tablo + sınıf (A30 kalıbı) | ortak "reklam bağlantıları" tablosu / soyut sınıf | Alanlar ve biçimler farklı; iki kullanıcı için soyutlama istenmedi (kural 6a) |
| 2 | Dönüşüm denemesi yalnız test koduyla | test kodsuz canlı olay / boş `data` ile istek | Anahtarı yan etkisiz okuyan uç yok; canlı veriye sahte olay düşmesin |
| 3 | Gönderimde Pixel kodu bağlantıdan | kuyruktaki yükün Pixel'i | Panelde değişen / kaldırılan bağlantıyla gitmesin (A30 karar 1) |
| 4 | Şema `MetaAnahtari` / `MetaDenemeSonucu` yeniden kullanılır | TikTok'a özel kopya şemalar | Biçim aynı; üretilen tiplerde kopya olmasın |
| 5 | `TIKTOK_*` ortamı kalkar | ikisini birlikte tutmak | Tek kaynak panel (A30 karar 2'nin aynısı) |

## TB1 — Kırma denemeleri — 16/16 kırmızı (K15 ilk koşuda tutmadı)

K1 anahtar düz saklanır · K2 panelde tam anahtar · K3 `null` anahtarı siler · K4 `''` kaldırmaz · K5 Pixel biçimi
yok · K6 reklamveren biçimi yok · K7 şifreleme hazır denetimi yok (istisna) · K8 mesajda anahtar · K9 test kodsuz
dene olay atar · K10 harcama Events anahtarıyla · K11 gönderimde yükteki eski Pixel · K12 bağlantısız da gider ·
K13 test kodu eklenmez · K14 yanıtta TikTok Pixel yok · **K15 bağlantısız kuyruğa alınır — ilk koşuda YEŞİL**
(bağlantısı olmayan uygulamanın kuyruğa girmediğini hiçbir test ölçmüyordu; `testTiktokBaglantisiYoksaKuyrugaGirmez`
eklendi) → 🔴 · K16 gönderimde başka anahtar — hepsi 🔴. Betik yamaları tek eşleşmeyle uyguladı (tutmayan yok).

## TB1 — Ölçüm

- **Testler:** yeni `PanelTiktokTest` (7); `StripeWebhookControllerTest` +1 test ve TikTok yükü denetimi (Pixel, test
  kodu, başlık); `GirisBaglantisiTest` +2 denetim. Etkilenen dört dosya 51/51 yeşil.
- **Tam kontrol:** çekirdek 788 test yeşil, biçim / statik analiz / sözleşme yeşil; ilk koşuda biçim kırmızısı
  (docblock hizası, düzeltildi). İkinci koşuda `panel-test` 5 zaman aşımı (Anahtarlar, Huniler, Planlar — bu blokta
  dokunulmayan sayfalar; yük ortalaması 11, paralel oturum) — aynı üç dosya `--testTimeout=30000` ile 26/26 yeşil.
  Üçüncü koşuda huni `Huni.test.tsx` Ç34 testi bir kez düştü (tek başına 95/95) — ikisi Ç151. **Dördüncü koşu:
  YEŞİL** (788 çekirdek testi; `UYARI dev-goc` — ortak geliştirme DB'sine bu göç bilerek uygulanmadı, tuzak §60).
- **Gerçek sistem** (ayrı geçici DB `app_ttdene`, gerçek TikTok Business API v1.3, sahte anahtar): kaydet `[]`; DB'de
  düz anahtar yok; panel `kayitli · …`; dene iki parçada "TikTok reddetti: Access token is incorrect or has been
  revoked. (kod 40105)" — uç yolları, `Access-Token` başlığı ve `{code, message}` biçimi doğru; yanıtta anahtar yok.
  DB silindi.
- **Ortam:** yerel, macOS + Docker, ayrı worktree (`tiktok-baglantisi`) ve test DB'si `app_testtiktok` (kural 15 —
  aynı dalda paralel dağıtım oturumu vardı).
