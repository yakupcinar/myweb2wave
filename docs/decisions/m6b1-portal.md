# M6b1 — müşteri portalı: ayar kodda + uygulama ucu (2026-11-09)

Karar `pre-setup.md` A19 (kullanıcı: portala iki yol, ayar kodda) · önceki `m6a-iptal-iade.md` · sonraki M6b2 (web yolu).

## Ne yapıldı

- **Ayar kodda** (`App\Billing\MusteriPortali::yapilandirma`): iptal dönem sonunda (`at_period_end`,
  orantılama yok — panelle aynı kural), kart güncelleme ve fatura geçmişi açık, plan değiştirme ve müşteri
  bilgisi düzenleme kapalı, varsayılan dönüş huni adresi. `app:stripe-portal:kur` Stripe'ta
  `metadata.kurulum = myweb2wave-m6b` etiketli ayarı arıyor; varsa güncelliyor, yoksa kuruyor (her ortamda bir kez, kod değişince yeniden).
- **Uygulama ucu** `POST /apps/{app}/kullanicilar/{kullanici}/portal` (uygulama anahtarı, sözleşmede):
  bağlı kişinin e-postası → bu uygulamadaki aboneliğin müşterisi (erişim veren önce, `Hak` ile aynı sıra) → Stripe
  portal oturumu (etiketli ayar, dönüş `huni/<app>/`) → `201 {adres}`. `unknown_user` / `no_subscription` 404,
  `portal_not_configured` / `stripe_error` 503. Dönüş adresi istekten alınmıyor (açık yönlendirme yok).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Ayar etiketle Stripe'ta bulunur | kimliği env'de tutmak | Kod tek kaynak; ortam değişkeni unutulmaz. Bedeli: oturum başına bir liste isteği (seyrek işlem) |
| 2 | Erişim veren abonelik önce | en son güncellenen | Eski iptal edilmiş abonelik müşteriyi yanlış portala götürmesin |
| 3 | Dönüş adresi sabit (huni/<app>/) | uygulamanın verdiği adres | Açık yönlendirme olmasın |
| 4 | Portal oturumu yalnız uygulama sunucusundan | tarayıcıdan | Anahtar istemciye çıkmaz (A5 ile aynı) |

## Test (`PortalTest`, 3)

Erişim veren abonelik önce (iptal edilmiş daha yeni olsa da). Etiketsiz ayar atlanıyor. Stripe'a müşteri,
ayar ve dönüş gidiyor. Yanıt sözleşmeye uyuyor. Anahtar yanlışsa 401; bağlanmamış kişi, başka uygulamanın
aboneliği ve kurulmamış portal durumlarında Stripe'a oturum isteği gitmiyor. Kurulum: özellikler birebir
(iptal modu, kapalılar), etiket, var olan ayar güncelleniyor.

## Kırma (9/9 🔴)

P1 erişim önceliği yok · P2 etiket denetimi yok (3 test) · P3 hemen iptal · P4 plan değiştirme açık · P5 hep yeni ayar ·
P6 yetki yok · P7 e-posta harf duyarlı (2) · P8 uygulama süzgeci yok · P9 dönüş uygulamasız. Hepsi iddia hatası.

## Gerçek sistem (2026-11-09, Stripe test hesabı)

- `app:stripe-portal:kur` → "Kuruldu: bpc_1UZrjF…"; ikinci koşu → "Güncellendi" (aynı kimlik). Stripe'tan geri okunan
  ayar: iptal `true / at_period_end`, kart `true`, fatura `true`, plan `false`, müşteri `false`, dönüş `http://localhost:4321/`.
- M4b'nin test müşterisi için oturum açıldı (`billing.stripe.com/p/session?secret=…`). ⚠️ Ölçüm betiğinde adresi
  `/session/<kimlik>` biçiminde sanıp maskeledim; gerçek biçim `?secret=…` olduğu için test oturum adresi çıktıya
  maskesiz düştü. Kısa ömürlü bir test modu oturumu; gerçek müşteri ya da para yok. Ders: gizli taşıyan çıktıyı
  biçim varsaymadan, yalnız "üretildi mi" diye yaz.
- Portal sayfası tarayıcıda açılmadı. Uygulama ucu dev'de uçtan uca denenmedi: dev'de uygulamaya bağlı kişi yok.
