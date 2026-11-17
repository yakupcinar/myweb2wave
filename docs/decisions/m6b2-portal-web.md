# M6b2 — web yolundan müşteri portalı (2026-11-09)

Karar `pre-setup.md` A19 (kullanıcı: portala iki yol) · önceki `m6b1-portal.md`.

## Ne yapıldı

Uygulamayı silen müşteri de aboneliğini yönetebiliyor:

1. Huni sayfası `/keepchat/abonelik/`: e-posta formu (`src/giris/abonelik.ts`).
2. `POST /huni/portal-baglantisi {app, eposta}` (herkese açık, CORS'ta, sözleşmede) her zaman **202** dönüyor.
   E-posta yalnız o uygulamada müşterisi olan adrese gidiyor, e-posta başına saatte en çok 3 kez.
3. E-postadaki bağlantı `…/portal/{jeton}`: 43 karakter, 1 saat geçerli, tek kullanımlık, özeti saklanıyor (`portal_jetonlari`,
   göç `Version20261109220000`).
4. **GET onay sayfası** gösteriyor ("Portalı aç"). Jeton düğmenin **POST**'unda kullanılıyor, müşteri o an
   çözülüyor (erişim veren abonelik önce, `MusteriPortali::musteri` — M6b1 ile tek tanım) → 303 Stripe portalı.
   Hatada 410 / 503 sayfası "Yeni bağlantı iste" bağlantısıyla gösteriliyor. Sayfa `no-store`, `Referrer-Policy: no-referrer`, çerçeveye girmez.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Hep 202, metin de aynı | "abonelik bulunamadı" | Hangi e-postanın abone olduğu sızmasın |
| 2 | Jeton POST'ta harcanır, GET onay sayfası | GET'te doğrudan Stripe'a | E-posta tarayıcıları (Outlook Safe Links vb.) bağlantıyı GET ile önceden açar, tek kullanımlık jeton müşteriden önce yanardı |
| 3 | Müşteri kullanım anında çözülür (jeton e-postayı taşır) | jetona müşteri kimliği yazmak | Arada abonelik değişebilir |
| 4 | Saatte 3, e-posta başına | IP başına | Hedef kişiye e-posta bombardımanı engellensin; IP sınırı vekil arkasında yanıltır |
| 5 | Bağlantının kökü isteğin geldiği çekirdek adresi | ayrı ortam değişkeni | Ayrı değişken iki kaynak demek |

## Test

- `PortalBaglantisiTest` (6): abone: kırpılmış ve küçük harfe çevrilmiş adrese tek e-posta gidiyor, jeton özetle saklanıyor,
  GET jetonu yakmıyor ve Stripe'a gitmiyor, başlıklar doğru, POST 303 (müşteri + ayar doğru),
  ikinci POST 410. Hesap sızdırmaz (abone olmayan ve başka uygulamanın abonesi: 202, e-posta yok, jeton yok).
  Saatte 3 (istek başına ölçülüyor, çünkü `getMailerMessages` yalnız son isteğinkini veriyor: 1,1,1,0). Süresi dolan ve
  geçersiz jeton. Geçersiz istek 422. CORS ön isteği: köken **ve POST izni**.
- Huni `abonelik.test.ts` (2): kırpılmış gövde, 202'de sızdırmayan metin, 422 ve ağ hatası ayrı metin, düğme yeniden etkin.

## Kırma (11/11 🔴, biri ilk koşuda tutmadı)

W1 abone olmayana da gider · W2 saat sınırı yok · W3 jeton düz (2) · W4 GET jetonu yakar · W5 tek kullanım yok ·
W6 süre yok · W7 e-posta kırpılmaz · **W8 CORS'tan çıkar: ilk koşu 🟢**. Yol alttaki yalnız-GET huni tanımı desenine de uyuyor,
köken başlığı geliyor; test yalnız kökene bakıyordu → `Allow-Methods` POST ölçüldü → 🔴 (tuzak §36 ailesi) ·
W9 Referrer başlığı yok · W10 huni 422'de başarı metni · W11 huni kırpmaz.

## Gerçek sistem (2026-11-09, dev + Mailpit + Stripe test)

- M4b test müşterisinin e-postasıyla `POST /huni/portal-baglantisi` (Origin huni) → 202; abone olmayan adres → 202.
  Mailpit'te yalnız bir e-posta var, bağlantı `http://localhost:8081/portal/<jeton>`.
- GET → 200, "Portalı aç". POST → **303 → billing.stripe.com** (adres maskeli okundu, M6b1 dersi). İkinci POST → 410.
- Gerçek CORS ön isteği (`OPTIONS`, Origin `http://localhost:4321`) → 200, `Allow-Methods: POST, DELETE, OPTIONS`.
- **Huni sayfası tarayıcıda açılmadı** (huni geliştirme sunucusu çalışmıyordu). Form happy-dom testinde, uç curl ile ölçüldü.
