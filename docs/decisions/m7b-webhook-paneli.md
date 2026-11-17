# M7b — panelde giden webhook hedefleri (2026-11-09)

Karar `pre-setup.md` A20 · motor `m7a-giden-webhook.md`.

## Ne yapıldı

- **Panel "Webhook'lar"** (`/panel/webhooklar`; menüde, doğrudan adres de açılıyor — §51). Hedef ekleme: adres
  (https) + sözlükten olay onay kutuları. Olay seçilmeden "Ekle" kapalı. Eklenince sır (`whsec_…`) **bir kez**
  uyarı kutusunda görünüyor. Liste: adres, olaylar, etkin / pasif, teslim · bekliyor · vazgeçildi sayıları,
  "Pasifleştir / Etkinleştir", "Günlük", iki adımlı "Sil". Günlük son 50 teslimi gösteriyor; bekleyen olmayanda "Yeniden gönder" var.
- **Uçlar** (sözleşmede, CSRF): `GET|POST /panel/api/uygulamalar/{app}/webhooklar`, `PUT|DELETE …/{id}`,
  `GET …/{id}/teslimler`, `POST …/{id}/teslimler/{teslim}/yeniden` (`App\Olay\WebhookHedefleri`).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Sır yalnız eklemede, listede son 4 hane | her zaman göstermek | Ekran görüntüsü / omuz üstünden sızmasın; kaybedilirse hedef silinip yeniden eklenir |
| 2 | Adres yalnız https | http de | Gövde imzalı ama içerik açıkta gitmesin; iç ağ zaten engelli (M7a) |
| 3 | Uygulama başına 10 hedef | sınırsız | Her olay hedef sayısı kadar istek demek |
| 4 | Olaylar sözlük sırasıyla saklanır | girildiği sırayla | Liste tutarlı; tekrar ve bilinmeyen ad reddedilir |
| 5 | Yeniden gönder denemeyi sıfırlar, hemen | yalnız durumu değiştirmek | Vazgeçilmiş teslim 10. denemede kalırsa ilk hatada yine vazgeçilirdi |

## Test

- `PanelWebhookTest` (4): sır yalnız eklemede (listede sır yok, son 4 hane), sözlük sırası, doğrudan adres; doğrulama (http, boş,
  bilinmeyen, tekrar), 10 sınırı, jetonsuz 403; etkin / pasif, silme, başka uygulamanın hedefine 404; günlük ve yeniden gönder
  (deneme 0, hemen), olmayan teslim 404. Yanıtlar sözleşmeye göre doğrulanıyor.
- Panel `Webhooklar.test.tsx` (3): olaysız "Ekle" kapalı, gövde (kırpılmış adres, seçilen olaylar), sır görünüyor, menüde var;
  pasifleştir + iki adımlı sil; günlük satırları ve yalnız bekleyen olmayanda "Yeniden gönder" (doğru teslim kimliği).

**Testin bulduğu hata:** onay kutusu `setSecili(s => e.currentTarget.checked …)` — güncelleyici sonra, çizimde koşuyor ve
`currentTarget` o an null; sayfa ilk tıklamada çöküyordu. Tip denetimi ve derleme yeşildi. Değer güncelleyiciden önce okunuyor.

## Kırma (12/12 🔴)

H1 sır listede · H2 http kabul · H3 bilinmeyen olay · H4 sınır yok · H5 başka uygulamanın hedefi · H6 deneme sıfırlanmaz ·
H7 CSRF yok · H8 sunucu rotası yok (4 test) · H12 girdi sırası · H9 olaysız ekle · H10 tek tıkla sil · H11 bekleyende yeniden gönder.

## Gerçek sistem (2026-11-09, dev)

`/panel/webhooklar` girişsiz → 302 giriş (rota var; olmayan sayfa 404). `app:webhook:gonder` → "Kuyruğa: 0; teslim: 0;
başarısız: 0" (dev'de hedef yok). **Panel ekranı tarayıcıda açılmadı** (giriş kullanıcıda). Gerçek teslimin imzası M7a'da Python alıcıyla ölçüldü.
