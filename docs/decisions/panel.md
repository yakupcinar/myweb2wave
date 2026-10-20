# Blok: İç panel — reklamdan mı geldi, ödedi mi (A6, 2. parça) — 2026-10-20

## Ne yapıldı
`/panel` (çekirdekte, Symfony + Twig): üstte reklam / organik ayrı sayımlar
(sayfayı gördü → ödemeye geçti → ödedi, dönüşüm %), altta en yeni 200
ziyaret: zaman, uygulama, kaynak (reklam etiketi + `utm_source` ya da
fbclid/ttclid), kampanya, aşama, abonelik durumu, **maskeli e-posta**
(`a***@alan.com`). Aralık 7 / 30 / 90 gün. Giriş: tek yönetici, kullanıcı
adı + şifre **özeti** ortam değişkeninde (`PANEL_KULLANICI`,
`PANEL_SIFRE_OZETI`); biri boşsa kimse giremez. CSRF, kullanıcı adı+IP
başına dakikada 5 deneme, hangi alanın yanlış olduğu söylenmez; sayfalar
`no-store` + `X-Frame-Options: DENY`. Dağıtıma iki değişken ve özet biçim
ön kontrolü eklendi.

## Neden böyle
- **Kullanıcı kararları (2026-10-20):** giriş kullanıcı adı + şifre
  (reddedilen: yalnız ssh tüneli — telefondan bakılamaz; Caddy temel
  şifre — çıkış/oturum yok) · e-posta maskeli (tam adres Stripe'ta var).
- **Çekirdeğin içinde, ayrı uygulama değil:** veri aynı Postgres'te; ayrı
  bir yönetim uygulaması ikinci dağıtım ve ikinci DB erişimi demek.
- **Kullanıcı tablosu yok:** tek yönetici; tablo + kayıt akışı A1'e ters
  (kendi uygulamalarımız, tek ekip). Özet ortam değişkeninde.
- **"Reklam" tanımı tek yerde** (`Rapor::REKLAM`): tıklama kimliği ya da
  ücretli `utm_medium` (cpc, ppc, paid, paid_social, display, ads). Satırlar
  ve sayımlar aynı ifadeyi kullanır.
- **Dış betik/stil yok:** tarifin `base.html.twig`'i sürümü sabitlenmemiş
  bir CDN betiğini koşullu yüklüyordu; yönetim sayfasından çıkarıldı.
- **Tarif varsayılanları daraltıldı:** güvenlik duvarı her yolu
  kapsıyordu → yalnız `^/panel`.

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| P1 | `/panel` herkese açık | 🔴 4 test |
| P2 | CSRF kapalı | 🔴 `testCsrfJetonuOlmadanGirilmez` |
| P3 | deneme sınırı yok | 🔴 `testBesYanlisDenemeden…` |
| P4 | boş özet/ad denetimi yok | 🔴 `testOzetYaDaAdBossaKimseGiremez` |
| P5 | güvenlik duvarı deseni kaldırıldı | **TUTMADI** — API'ye yine çerez gitmiyor: lazy duvar oturum açmıyor. Yorumdaki "API oturumsuz kalır" iddiasını desen sağlamıyor; yorum ölçüme göre düzeltildi (tuzak §10) |
| P6 | Cache-Control ezmesini kapatan başlık yok | 🔴 `testDogruSifreyleGirilir` |
| P7 | `X-Frame-Options` yok | 🔴 aynı |
| P8 | e-posta maskelenmiyor | 🔴 `testSatirdaAsama…MaskeliEposta` |
| P9 | şablonda `|raw` (XSS) | 🔴 `testZararliUtmMetniKacislanir` |
| P10 | reklam tanımından fbclid çıktı | 🔴 3 test |
| P11 | "ödedi" sayımı tamamlanmayı aramıyor | 🔴 sayım testi |
| P12 | gün aralığı uygulanmıyor | 🔴 sayım testi |
| P13 | deneme sınırı mesajı genel mesaja düştü | 🔴 `testBesYanlisDenemeden…` |
| P14 | `.env.test`'te özet tırnaksız | ilk deneme yalnız açılış tırnağını sildi → ayrıştırma hatası, **sahte kırmızı** (EXIT=2, test koşmadı). P14b iki tırnak da silindi → 🔴 giriş testi, hata yok: özet sessizce bozuldu → tuzak §26 |

## Ölçüm
- `bin/kontrol` YEŞİL: 63 test / 281 doğrulama (öncesi 52 / 220).
- **Bulgu (testte yakalandı):** `Cache-Control: no-store` oturum açıkken
  Symfony tarafından sessizce `max-age=0, must-revalidate, private` ile
  eziliyordu — çıkıştan sonra panel tarayıcı önbelleğinde kalabilirdi.
  `NO_AUTO_CACHE_CONTROL_HEADER` ile kapatıldı (P6 ölçtü).
- **Compose ve `$` (tuzak §26):** aynı özet `--env-file`'da tek tırnaklı →
  konteynerde eşit; tırnaksız → 22 karakter. `docker compose config` doğru
  değeri `$$` diye gösteriyor; ön kontrol ilk hâliyle doğru değeri de
  reddederdi — `$$`→`$` eklendi, iki dosyayla ölçüldü (tırnaklı EXIT=0,
  tırnaksız EXIT=1 + mesaj).
- **Üretim imajı (bulgu):** `templates/` açık `COPY` listesinde yoktu →
  `/panel/giris` **500** (testler geliştirme imajında, kod bağlı birimde
  koştuğu için görmedi). Eklendi → 200, CSRF alanı var, girişsiz `/panel`
  → 302. Yeni dizin = `COPY` listesi + imajda ölçüm (`dagitim.md` K9).
- **Çerez (bulgu):** üretim imajında oturum çerezi `Secure`'suzdu — çekirdek
  Caddy'nin arkasında düz HTTP görüyor, `cookie_secure: auto` koymuyor.
  `when@prod` `cookie_secure: true` → `secure; httponly; samesite=lax`.
- **Tarayıcı (Claude in Chrome, yerel çekirdek, test şifresi):** `/panel` →
  `/panel/giris` → giriş → panel: reklamdan 2 gördü / 1 ödemeye geçti /
  1 ödedi / %50, satır `reklam · meta | ekim-test | ödedi | active |
  t***@example.com` (atıf bloğundaki gerçek Stripe ödemesi). Çıkış →
  `/panel` yine girişe yönlendi. İlk "Giriş" tıklaması sayfaya ulaşmadı
  (araç davranışı, önceki bloklarda da).

## Açık
Ç35 (tek yönetici, 2FA yok, oturum dağıtımda düşer, panel API alan
adında) · Ç34 (sayılar şişirilebilir — panel bunu gösterir, düzeltmez).
