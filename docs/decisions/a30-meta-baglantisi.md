# A30 — Meta bağlantısı panelden (2026-11-14)

Karar `pre-setup.md` A30 (kullanıcı: elle gir + dene · dönüşüm + harcama · şifreli). İki blok: **MB1** çekirdek,
**MB2** panel ekranı.

## Ölçüm (önce)

- Meta ayarı üç yerdeydi, hepsi ortam değişkeni: CAPI (`META_PIXEL_ID`, `META_ACCESS_TOKEN`, `META_TEST_EVENT_CODE`),
  harcama (`META_AD_ACCOUNT_ID`, `META_INSIGHTS_ACCESS_TOKEN`), huni pikseli derlemede (`PUBLIC_META_PIXEL_ID`).
  Yerel `.env.local`'de hepsi boştu; `.env`'de `META_GRAPH_API_VERSION` de boş → özellik her yerde kapalıydı.
- Kapta `sodium` var (PHP 8.4.26).
- Graph API sürümü anahtarsız ölçülemedi: v22…v99 hepsine aynı yanıt (`OAuthException` 190, "Cannot parse access
  token") — Meta sürümden önce anahtarı denetliyor. Varsayılan `v24.0` yazıldı; doğrulaması Ç145.
- Dönüşüm kuyruğu (`reklam_donusumleri`) satırın uygulamasını tutmuyordu — anahtar uygulama başına olunca gerekli.

## MB1 — Ne yapıldı (çekirdek)

- **`Sifreleyici`**: sodium secretbox; anahtar `AYAR_SIFRELEME_ANAHTARI` (32 bayt base64). Yoksa anahtar kaydedilmez
  (422, açık mesaj); değişmişse kayıtlı anahtar okunamaz → panelde "okunamadı", gönderim kapanır.
- **Göç** `Version20261114090000`: `meta_baglantilari` (uygulama başına; biçimler DB kısıtında da) +
  `reklam_donusumleri.app`.
- **`MetaBaglantisi`**: `oku` (çözülmüş, gönderim için), `panel` (son 4 hane), `kaydet` (anahtar alanı yok / `null`
  = değişmez, `''` = kaldır), `sil`, `dene` (Pixel'i CAPI anahtarıyla, reklam hesabını Insights anahtarıyla okur;
  Meta'nın hata mesajından anahtar silinir), `harcamaHesaplari`.
- **Tüketiciler panelden okur:** `DonusumAktarimi` (kuyruk satırı uygulamayı taşır; gönderim **o anki** bağlantıyla —
  panelde kaldırıldıysa gitmez), `MetaHarcamalari` (bağlı her hesabı çeker; biri hata verirse hiçbiri yazılmaz,
  hata uygulama adıyla), `CanliyaGecis` (Meta adımı), `GirisBaglantisiController` (yanıtta `metaPixelId`).
- **Huni:** başarı sayfası Pixel'i yanıttan alır; `PUBLIC_META_PIXEL_ID` ve web imajı derleme argümanı kalktı
  (Pixel değişince imaj yeniden kurulmaz).
- **Uçlar** (sözleşmede): `GET|PUT|DELETE /panel/api/uygulamalar/{app}/meta`, `POST …/meta/dene`.
- **Ortam:** `META_*` hesap değişkenleri `.env`, `.env.test`, `deploy/` şablonları ve README'den kalktı; kalan
  `META_GRAPH_API_VERSION=v24.0` (dağıtım ayarı) ve `AYAR_SIFRELEME_ANAHTARI`. Yerel `.env.local`'e rastgele anahtar
  üretildi. TikTok değişmedi (ortamda).
- **Panel metni:** canlıya geçiş Meta adımı artık "Entegrasyonlar › Meta" der ve oraya bağlanır.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Gönderim anında bağlantı okunur | kuyruğa alırken Pixel/anahtar yükle yazmak | Anahtar kuyrukta düz durmasın; panelde kaldırılan bağlantıyla gitmesin |
| 2 | Boş anahtar alanı "değiştirme" | her kayıtta anahtar istemek | Panel anahtarı göstermez; Pixel'i düzeltmek için anahtarı yeniden yapıştırmak gerekmesin |
| 3 | Harcama tablosuna `app` eklenmedi | tabloyu uygulama başına bölmek | Kampanya kimlikleri hesaplar arası benzersiz; rapor kampanya adıyla eşliyor (M5d) — gerekirse Ç145 |
| 4 | Sürüm ortamda | panelde alan | Hesaba değil dağıtıma bağlı; Meta her sürümü ~2 yıl destekler |

## MB1 — Kırma denemeleri — 17/17 kırmızı (M12 ilk koşuda tutmadı)

M1 anahtar düz saklanır · M2 panelde tam anahtar · M3b `null` anahtarı siler · M4 `''` kaldırmaz · M5 Pixel
biçimi denetlenmez · M6 hesap biçimi denetlenmez · M7 şifreleme hazır denetimi yok (istisna) · M8 Meta mesajında
anahtar · M9 Pixel Insights anahtarıyla okunur · M10 gönderimde sabit uygulama · M11 kuyrukta uygulama yok ·
**M12 test olay kodu eklenmez — ilk koşuda YEŞİL** (kodu hiçbir test okumuyordu; dönüşüm testine eklendi) → 🔴 ·
M13 yalnız ilk hesap çekilir · M14 canlıya geçiş hep tamam · M15 yanıtta Pixel yok · M17 dönüşüm CAPI anahtarına
bakmaz — hepsi 🔴.

**M3 notu:** ilk bozma `null`'ı silme yerine hataya çevirdi (yanlış yeri bozdu); doğru bozma M3b ile ölçüldü.

## MB1 — Ölçüm

- **Testler:** yeni `PanelMetaTest` (7: maskeli + şifreli, değişmez / kaldır, doğrulama + DB kısıtı, şifreleme yok /
  değişti, dene, kaldır, gönderimdeki bağlantı); `MetaHarcamalariTest` +1 (iki uygulamanın hesabı);
  `GirisBaglantisiTest` +1; mevcut üç test ortam yerine panel kaydıyla. Huni `basarili.ts` testsiz (modül yüklenince
  çalışır): alan sözleşmeden üretilen tiple ve çekirdeğin sözleşme testiyle bağlı.
- **Tam kontrol:** YEŞİL (780 çekirdek testi, `UYARI` yok), yerel, 2026-11-14.
- **Gerçek sistem** (dev DB, `.env.local` anahtarı, gerçek Graph API v24.0, sahte anahtar): kaydet `[]`; DB'de düz
  anahtar yok; panel `kayitli · 6789`; dene iki parçada "Meta reddetti: Invalid OAuth access token - Cannot parse
  access token" (mesajda anahtar yok); kayıt silindi.

## MB2 — Ne yapıldı (panel)

- **Entegrasyonlar › Meta** (`#meta`, [`Meta.tsx`](../../core/panel/src/uygulama/sayfalar/Meta.tsx)), Webhook'ların altında, iki
  kart: **Dönüşüm** (Pixel ID, Conversions API anahtarı, test olay kodu) ve **Harcama** (reklam hesabı, Insights
  anahtarı). Her alanın altında Meta'da nereden alınacağı yazar.
- **Anahtar alanları** `password`; kayıtlıysa yer tutucu "Kayıtlı (…6789) — değiştirmek için yenisini yapıştır". Boş
  bırakılan alan gövdede `null` (değişmez); "Kaldır" `''` gönderir, alan kilitlenir, "Silinecek — geri al" ile döner.
  Okunamayan anahtarda "Okunamadı — yeniden girin" rozeti.
- **Bağlantıyı dene** yalnız kaydedilmemiş değişiklik yokken (sunucu kayıtlı değerleri dener); sonuç parça başına
  rozet + Meta'nın mesajı. **Bağlantıyı kaldır** onay ister.
- Sunucuda şifreleme anahtarı ya da Graph sürümü yoksa uyarı notu.
- Canlıya geçiş Meta adımının bağlantısı (`/panel/entegrasyonlar#meta`) artık bu bölüme düşer.

## MB2 — Kırma denemeleri — 8/8 kırmızı

N1 boş anahtar alanı `''` gider (kayıtlı anahtarı sessizce silerdi) · N2 "Kaldır" yok sayılır · N3 son 4 hane yok ·
N4 kaydedilmemişken dene açık · N5 onaysız kaldır · N6 anahtar düz metin alanı · N7 şifreleme uyarısı yok · N8 deneme
mesajı yok — hepsi 🔴.

## MB2 — Ölçüm

- **Panel:** `Meta.test.tsx` 5 test; bütün sayfa testleri 96/96 (Entegrasyonlar'ın ortak sahtesine boş Meta yanıtı).
- **Tam kontrol:** YEŞİL (780 çekirdek testi, `UYARI` yok), yerel, 2026-11-14.
- **Ekran döngüsü** (`entegrasyonlar`, açık/koyu, 1440/390): bölüm çizildi, taşma ve yatay kayma yok; telefondaki
  küçük hedefler menü ve tema seçicisi (Ç141, önceden de vardı).
