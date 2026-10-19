# Blok: Uygulama boyutu (A4) — 2026-10-19

## Ne yapıldı
- `config/packages/uygulamalar.yaml`: uygulama → plan → Stripe fiyatı
  (`STRIPE_PRICE_KEEPCHAT_MONTHLY`). Yeni uygulama = bir blok + sözleşmede
  `app` enum'una bir değer.
- `POST /checkout/sessions` artık `{app, plan}`; bilinmeyen uygulama
  `422 unknown_app`. Oturuma **ve** aboneliğe `metadata.app` yazılır;
  dönüş adresleri `/{app}/basarili`, `/{app}/iptal`.
- Webhook uygulamayı `metadata.app`'ten okur; yoksa olay "işlendi" kaydedilir
  ama **uygulanmaz** + uyarı günlüğü.
- Göç `Version20261019150000`: `subscriptions.app` — ekle, var olanları
  `keepchat` yap, `NOT NULL`.
- Huni sayfaları `/keepchat/` altına taşındı; sözleşme + üretilen tipler güncel.

## Neden böyle
- **Kodda yapılandırma, DB tablosu değil:** birkaç kendi uygulamamız için
  yönetim ekranı gerekmez; fiyat kimliği ortama göre değiştiği için env.
- **Uygulama metadata'dan, fiyattan tahmin değil:** tek kaynak — çekirdek
  oturumu açarken yazar, Stripe geri getirir.
- **Uygulamasız olay uygulanmaz:** CLI/elle tetiklenen abonelik hiçbir
  uygulamayı açmasın. Reddedilen: varsayılan `keepchat` (U4 tam bunu ölçüyor).
- **Göç üç adım:** doğrudan `NOT NULL` dolu tabloda düşer.

## Yolda ölçülenler
- **Taşınan sayfada ölü bağlantı:** iptal sayfasının "Plana geri dön"
  bağlantısı `/`'u gösteriyordu (kök artık 404). Değiştirme betiği deseni
  bulamadı (prettier satırı bölmüştü) → **uygulanmadı** diye görüldü,
  düzeltildi. Ne tip denetimi ne derleme bunu yakalar → Ç23.
- **NOT NULL kısıtının bekçisi yoktu:** elle ölçüldü, sonra doğrudan kısıt
  testi yazıldı (tuzak §4).
- Tarayıcıda ilk "Abone ol" tıklaması istek göndermedi (Stripe'ta 2 oturum:
  curl + ikinci tık) — sayfa betiği yüklenmeden tıklama; uygulama hatası değil.

## Kırma denemeleri

| # | Bozulan karar | Sonuç |
|---|---|---|
| U1 | oturumda `metadata.app` yok | 🔴 `testAylikPlan…` |
| U2 | abonelikte (`subscription_data`) `metadata.app` yok | 🔴 `testAylikPlan…` |
| U3 | uygulama beyaz listesi yok | 🔴 `testBilinmeyenUygulama422…` (+uyarı) |
| U4 | uygulamasız olaya varsayılan `keepchat` | 🔴 2 test (`…AbonelikOlayiUygulanmaz`, `…OdemeOlayiUygulanmaz`) |
| U5 | dönüş adresinde uygulama yok | 🔴 `testAylikPlan…` |
| U6 | huni `app` göndermiyor | 🔴 `funnel-tip` |
| U7 | DB'de `NOT NULL` kaldırıldı | 🔴 `testUygulamasizAbonelikKisiti…`; geri alma TRUNCATE + SET NOT NULL, `nullable=NO` ölçüldü |

## Gerçek istek
- Göç **dolu** dev DB'de: önce 2 satır / sütun yok → sonra `keepchat|2`,
  `is_nullable=NO`; app'siz INSERT `violates not-null constraint`.
- Gerçek Stripe oturumu: `metadata={"app":"keepchat"}`, dönüş `/keepchat/…`.
- Tarayıcıda `4242` ödemesi → `/keepchat/basarili`; Stripe aboneliği
  `metadata.app=keepchat`; DB `keepchat|active|test+uygulama@`.
  İki olay **aynı saniyede eşzamanlı** geldi, `ON CONFLICT` doğru sonuç verdi.
- `stripe trigger` (metadata'sız): 200, işlenen olay 6→7, abonelik 3→3,
  uyarı günlüğü 1.

## Ölçüm
`bin/kontrol` EXIT=0 · core-test `OK (19 tests, 58 assertions)`, 0 atlanan.
