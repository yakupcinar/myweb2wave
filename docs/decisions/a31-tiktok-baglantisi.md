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
  Üçüncü koşuda huni `Huni.test.tsx` Ç34 testi bir kez düştü (tek başına 95/95) — ikisi Ç155. **Dördüncü koşu:
  YEŞİL** (788 çekirdek testi; `UYARI dev-goc` — ortak geliştirme DB'sine bu göç bilerek uygulanmadı, tuzak §60).
- **Gerçek sistem** (ayrı geçici DB `app_ttdene`, gerçek TikTok Business API v1.3, sahte anahtar): kaydet `[]`; DB'de
  düz anahtar yok; panel `kayitli · …`; dene iki parçada "TikTok reddetti: Access token is incorrect or has been
  revoked. (kod 40105)" — uç yolları, `Access-Token` başlığı ve `{code, message}` biçimi doğru; yanıtta anahtar yok.
  DB silindi.
- **Ortam:** yerel, macOS + Docker, ayrı worktree (`tiktok-baglantisi`) ve test DB'si `app_testtiktok` (kural 15 —
  aynı dalda paralel dağıtım oturumu vardı).

## TB2 — Ne yapıldı (panel)

- **Entegrasyonlar › TikTok** (`#tiktok`, [`Tiktok.tsx`](../../core/panel/src/uygulama/sayfalar/Tiktok.tsx)), Meta'nın
  altında, iki kart: **Dönüşüm** (Pixel kodu, Events API anahtarı, test olay kodu) ve **Harcama** (reklamveren kimliği,
  Marketing API anahtarı). Her alanın altında TikTok'ta nereden alınacağı yazar; test olay kodu yardımı "dene"nin
  bununla çalıştığını söyler.
- Davranış Meta bölümünün aynısı (A30 MB2): anahtar alanları `password`, yer tutucuda son 4 hane; boş alan `null`,
  "Kaldır" `''`; okunamayan anahtar rozeti; dene yalnız kaydedilmemiş değişiklik yokken; kaldırmak onaylı. Graph
  sürümü uyarısının karşılığı yok (TikTok sürümü uçta sabit, `v1.3`).
- `Meta.test.tsx` düğmeleri artık Meta bölümünde arar — sayfada aynı adlı TikTok düğmeleri var (yalnız bu satır).

## TB2 — Kırma denemeleri — 10/10 kırmızı

N1 boş anahtar alanı `''` gider · N2 "Kaldır" yok sayılır · N3 son 4 hane yok · N4 kaydedilmemişken dene açık · N5
onaysız kaldır · N6 anahtar düz metin alanı · N7 şifreleme uyarısı yok · N8 deneme mesajı yok · N9 bölüm sayfada yok ·
N10 TikTok yerine Meta ucuna yazar — hepsi 🔴 (`Tiktok.test.tsx`, `--testTimeout=30000`, Ç155).

## TB2 — Ölçüm

- **Panel:** `Tiktok.test.tsx` 5 test; `Meta.test.tsx` 5 test yeşil; tip denetimi temiz.
- **Ekran döngüsü KOŞULMADI:** `panel-ekran` kabı ortak compose projesinde ana klasörün kodunu bağlıyor ve ortak
  geliştirme DB'sinde `tiktok_baglantilari` yok — worktree'den koşmak paralel oturumun kabını ve DB'sini değiştirirdi.
  Dal birleşince `bin/panel-ekran entegrasyonlar` (açık/koyu, 1440/390) koşulacak (Ç154 (5)).

## TB3 — Ne yapıldı (harcama + ROAS)

- **Göç** `Version20261117140000`: `reklam_harcamalari.saglayici` kısıtı `('meta', 'tiktok')`.
- **`TiktokHarcamalari`** (`MetaHarcamalari`'nın karşılığı): reklamveren başına önce `advertiser/info` (para birimi —
  rapor satırında yok), sonra `report/integrated/get` (`BASIC`, `AUCTION_CAMPAIGN`, boyut `campaign_id` +
  `stat_time_day`, ölçü `spend` + `campaign_name`, sayfa 1000). Gün boyutuyla bir istekte en çok 30 gün → aralık 30
  günlük pencerelere bölünür; sayfalar `page_info.total_page`. Biri hata verirse hiçbiri yazılmaz; mesaj anahtarsız,
  uygulama adıyla.
- **Komut** `app:reklam-harcamalari:cek` bağlı her ağı çeker (Meta, TikTok); biri hata verirse öteki yine yazılır,
  çıkış hata; hiçbiri bağlı değilse hata.
- **Rapor:** `Rapor::kampanyalar` iki ağı tek listede döner, her satırda `ag`; ziyaret ve gelir ağın kendi tanımıyla
  (`Rapor::META` / yeni `Rapor::TIKTOK` — `ttclid` ya da `utm_source` tiktok/tt). Sözleşmede `ag` zorunlu.
- **Panel:** Rapor › Reklam harcaması tablosu "Reklam kampanyaları", ilk sütun **Ağ**; boş kart "Bu dönemde reklam
  harcaması yok", bağlantı `/panel/entegrasyonlar` (önceden `/panel/ayarlar` — A30'dan beri Meta orada değil).
  Ayarlar'daki zamanlı görev adı "reklam harcaması (Meta, TikTok)". MCP `rapor_kampanyalar` açıklaması güncellendi
  (veri aynı uçtan, `ag` kendiliğinden gelir).

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 6 | Tek liste + `ag` alanı, panelde Ağ sütunu | ayrı `tiktokKampanyalari` alanı ve ikinci tablo | Aynı tablo iki kez çizilirdi; MCP aracı da TikTok'u ek iş olmadan görür |
| 7 | Gelir ağın kendi tanımıyla eşlenir | yalnız kampanya adıyla | Aynı adlı Meta ve TikTok kampanyası birbirinin gelirini almasın (M5d'deki Meta kuralının aynısı) |
| 8 | Komut ağ başına bağımsız | ilk hatada durmak | TikTok hatası Meta harcamasını (ya da tersi) bekletmesin; cron yine hata görür |
| 9 | Para birimi `advertiser/info`'dan | rapor ölçüsü olarak istemek | Rapor ölçüsünde para birimi alanı olduğu doğrulanamadı; reklamveren bilgisinde var |

## TB3 — Kırma denemeleri — 15/15 kırmızı

H1 harcama `meta` olarak yazılır · H2 para birimi sabit · H3 30 günlük pencere yok · H4 yalnız ilk sayfa · H5 TikTok
hata kodu yok sayılır · H6 mesajda anahtar · H7 tarih biçimi denetlenmez · H8 yarım çekiş yazılır · H9 komut hatada
başarılı döner · H10 bağlı olmayan ağ denenir · H11 TikTok ziyareti Meta koşuluyla · H12 gelir ağdan bağımsız · H13
yanıtta ağ sabit · H14 panelde ağ sütunu boş · H15 boş durum eski adrese — hepsi 🔴.

## TB3 — Ölçüm

- **Testler:** yeni `TiktokHarcamalariTest` (6); `PanelTest` +1 (`testTiktokKampanyaRoas`: aynı adlı iki ağ) ve
  Meta ROAS beklenenine `ag`; `MetaHarcamalariTest` kurulumu TikTok bağlantısını da siler (tuzak §13); panel
  `Rapor.test.tsx` Ağ sütunu ve boş kart.
- **Gerçek sistem** (geçici DB `app_ttdene`, gerçek TikTok Business API v1.3, sahte anahtar): `cek(3)` →
  "keepchat: TikTok reddetti: Access token is incorrect or has been revoked. (kod 40105)", satır yazılmadı, mesajda
  anahtar yok; `report/integrated/get` ucu ayrıca: HTTP 200, `code` 40105 — uç yolu ve `{code, message}` biçimi doğru.
  Geçerli anahtarla satır biçimi ölçülmedi (Ç154 (6)). DB silindi.
- **Kontrol:** ilk koşuda belge bağlantısı kaydı (`Rapor::kohort` satırı, `--duzelt`) ve panel Huniler zaman aşımı;
  ikinci koşuda huni Ç34 testi yine düştü → Ç155 kapatıldı: huniye panelin Ç134 kurulumu (`waitFor` 3 sn), panele
  test başına 15 sn; kırma 1 ms'de huni 40 / panel 5 test düşürdü (ayar gerçekten uygulanıyor).
