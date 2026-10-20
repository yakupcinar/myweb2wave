# Blok: ödeme sonrası giriş bağlantısı (A10 / Ç46) — 2026-10-21

## Ne yapıldı
Web'de ödeyen kişi uygulamaya e-posta yazmadan, şifresiz girer. Stripe
`checkout.session.completed` webhook'u aboneliğe ödeme oturumunu (`cs_…`)
ve tamamlanma anını yazar. Başarı sayfası (`/keepchat/basarili`) çekirdekten
`POST /huni/giris-baglantisi {app, oturum}` ile **tek kullanımlık** bir
bağlantı ister (`keepchat://giris?jeton=<43 karakter>`); webhook sayfadan geç
gelirse 404 `not_ready` alır ve 8 × 1,5 sn yeniden dener, alamazsa e-posta /
"Aboneliğimi bağla" yolunu yazar. Sayfa `session_id`'yi okuyunca adresten
siler. Aynı türden ikinci bir bağlantı ödeme e-postasına **bir kez** gider
(webhook'tan sonra, giden kutusu = `subscriptions.giris_epostasi_at`).
Uygulamanın sunucusu jetonu `POST /apps/{app}/giris/kullan {jeton, kullanici}`
ile kullanır → 200 `{kisi}`; bağlama e-posta kodu yoluyla aynı
(`App\Kimlik\Baglama`: kişi + bağlantı + RevenueCat kuyruğu).
Şema uygulama başına `uygulama_semalari` (`config/packages/uygulamalar.yaml`).

## Neden böyle
- **Dış istem ("Lazy Auth") reddedildi:** Stripe işlem kimliğini kalıcı
  RevenueCat kimliği yapıp bağlantıyla taşımak — kimlik başarı sayfası
  adresinde durur (geçmiş, ekran görüntüsü, piksel), süresiz ve çok
  kullanımlık bir parolaya dönüşür; A9'un reddettiği "geçici kimlik + takma
  adla birleştirme"nin aynısı. Ayrıntı Ç46.
- **Rakip ölçümü** (`rakip-arastirmasi.md` § Ek 2026-10-20): RevenueCat
  Redemption Links = özel şema + tek kullanımlık 60 dk jeton + e-posta;
  Superwall süreli kod + e-posta. Biz aynı çizgideyiz.
- **Süre 24 sa (RevenueCat 60 dk):** RevenueCat süresi dolanı otomatik
  yeniden e-postalar; bizde yenileme yok, kişi uygulamayı sonra kurabilir.
  Tek kullanım sızıntıyı sınırlar. Süresi dolan için kurtarma = e-posta kodu.
- **Başarı sayfası penceresi 60 dk, oturum başına en çok 3 jeton** (e-postanınki
  dahil): sızan `cs_` ile sonradan jeton basılamaz; sayfa yenilemeye yer kalır.
- **Kimliği çekirdek üretir, Stripe API'si sorulmaz:** webhook verisinden okunur.
  Reddedilen: başarı sayfasında `checkout.sessions.retrieve` — stripe-mock
  değerleri bilmediği için ölçülemezdi ve webhook yarışı yine kalırdı.
- **Universal Link değil özel şema:** aynı alandaki sayfadan dokunulan
  Universal Link iOS'ta uygulamayı açmaz (huni sayfası ile bağlantı aynı
  alanda olurdu); ayrıca Team ID + AASA dosyası ister. RevenueCat da özel şema
  kullanıyor. Şemayı başka uygulamanın kaydetmesi riski tek kullanım + 24 sa
  ile sınırlı → Ç47.
- **Kullanılmış ve bilinmeyen jeton aynı cevap** (`invalid_token`): hangisi
  olduğu dışarı sızmaz.
- **E-posta işlem içinde gönderilir:** işlem düşerse ikinci e-posta gidebilir;
  hiç gitmemesinden iyi. Gönderim hatası webhook'u düşürmez (günlüğe yazılır,
  sonraki webhook'ta yeniden denenir).

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | kullanılmış jeton yeniden kabul | 🔴 1 (`testJetonIkinciKezKullanilamaz`) |
| K2 | süre denetimi kaldırıldı | 🔴 1 |
| K3 | 60 dk pencere kaldırıldı | 🔴 1 |
| K4 | oturum başına sınır 3 → 99 | 🔴 1 |
| K5 | jeton düz saklanıyor | 🔴 1 |
| K6 | jetonun uygulaması denetlenmiyor | 🔴 1 |
| K7 | e-posta: kilit altındaki `giris_epostasi_at IS NULL` kaldırıldı | 🟢 **tutmadı** — dıştaki seçim aynı filtreyi taşıyor; bu satır yalnız eşzamanlı iki webhook'a karşı. Sıralı test göremez |
| K7b | e-posta gönderildi işareti yazılmıyor | 🔴 1 (`testGirisEpostasiBirKezGider`) |
| K7c | dıştaki filtre kaldırıldı | 🟢 tutmadı — kilit altındaki okuma yakalıyor (K7'nin tersi) |
| K7+K7c | iki filtre birlikte | 🔴 1 — çift birbirinin yedeği, birlikte ölçülüyor |
| K8 | bağlantıya jeton yerine `cs_` | 🔴 5 |
| K9 | webhook ödeme anını yazmıyor | 🔴 8 |
| K10 | bağlamada RevenueCat kuyruğu yok | 🔴 1 |

Eşzamanlı iki webhook'un çift e-posta göndermemesi (K7) ÖLÇÜLMEDİ — Ç47.

## Ölçüm
- `bin/kontrol` YEŞİL: 168 çekirdek testi (155 + 13), 1028 iddia; huni tip
  denetimi + derleme; sözleşme tipleri güncel (huni + düzenleyici).
- Gerçek HTTP (yerel `:8081`, imzalı webhook dev sırrıyla): webhook öncesi
  sayfa **404** → webhook **200** → CORS ön uçuşu yalnız `http://localhost:4321`
  → sayfa **201** `keepchat://giris?jeton=…` → Mailpit'te **1** e-posta
  "Uygulamaya giriş bağlantınız" → kullanım **200** `{kisi}` → ikinci kullanım
  **422** `invalid_token`.
- Tarayıcı (Chrome, `localhost:4321`): webhook 4 sn geç gönderildi → sayfa
  yeniden deneyip bağlantıyı gösterdi (jeton 43 karakter), adres
  `/keepchat/basarili` (session_id silindi). Webhook hiç gelmeyen oturumda
  ~12 sn sonra yedek metin.
- Ölçülmedi: gerçek iPhone'da şema açılışı (KeepChat'te şema yok — Ç47),
  gerçek Stripe test ödemesiyle uçtan uca (webhook elle imzalandı).
