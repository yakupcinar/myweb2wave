# Blok: Reklam atfı — ziyaret → ödeme oturumu → abonelik (A6, 1. parça) — 2026-10-20

## Ne yapıldı
Huni sayfası açılınca URL'deki reklam alanları (`utm_source`, `utm_medium`,
`utm_campaign`, `utm_content`, `utm_term`, `fbclid`, `ttclid`) çekirdeğe
yazılır: `POST /huni/ziyaretler` → `huni_ziyaretleri`, dönen kimlik sayfanın
belleğinde. "Abone ol"da kimlik ödeme isteğine eklenir; çekirdek açtığı
Stripe oturumunu `odeme_oturumlari`'na ziyaretle bağlar.
`checkout.session.completed` gelince oturum **ödendi** işaretlenir ve
abonelik kimliği yazılır → `subscriptions` ile birleşince "reklamdan geldi
mi, ödedi mi, aboneliği şu an ne durumda" tek sorgu. Panel (2. parça) bunu
gösterecek; reklam ağına dönüşüm gönderimi (CAPI) ayrı blok.

## Neden böyle
- **Ham veri bizde** (A6). Reddedilen: atıfı yalnız Stripe metadata'sında
  tutmak — ödemeyen ziyaret görünmez, dönüşüm oranının paydası kaybolur.
- **Çerez/tarayıcı deposu yok:** huni tek sayfa; kimlik sayfa belleğinde
  yeter. Reddedilen: `sessionStorage`/çerez — onay yükü (Ç12) ve gereksiz.
- **Oturum ayrı tablo:** aynı ziyaretten iptal edip yeniden denenen her
  oturum ayrı satır; tek sütunda tutsaydık son oturum öncekini ezer, eski
  sekmeden tamamlanan ödeme eşleşmezdi.
- **Eşleşme oturum kimliğiyle**, Stripe metadata'sıyla değil: kimliği
  oturumu açarken zaten biliyoruz; ikinci bir kaynak gereksiz.
- **Atıf ödemeyi durdurmaz:** bilinmeyen, bozuk ya da başka uygulamanın
  ziyareti sessizce atlanır, ödeme 201. Ziyaret kaydı başarısızsa huni yine
  ödeme açar. Bozuk kimlik biçimi Postgres uuid dönüşümünde 500 olacağı
  için çekirdekte biçim denetimi var (A6 kırması ölçtü).
- **Uzunluk sınırı 500:** fbclid ~200 karakter; üstü kötüye kullanım.

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| A1 | `fbclid` kaydedilmiyor | 🔴 `testZiyaretReklamAlanlariylaKaydedilir` |
| A2 | sınır `>` → `>=` | 🔴 sınır testi |
| A3 | dizge denetimi yok | 🔴 `testDizgeOlmayanAlan422` |
| A4 | boş dizge null'a çevrilmiyor | 🔴 `testReklamsizZiyaret…` |
| A5 | oturum bağlarken uygulama denetimi yok | 🔴 `testBaskaUygulamaninZiyaretiBaglanmaz…` |
| A6 | uuid biçim denetimi yok | 🔴 `testTaninmayanZiyaretOdemeyiDurdurmaz` (500) |
| A7 | ödeme oturumu ziyarete bağlanmıyor | 🔴 `testZiyaretliOdemeOturumuZiyareteBaglanir` |
| A8 | webhook oturumu ödendi işaretlemiyor | 🔴 `testOdemeTamamlanincaHuniOturumuOdendiOlur` |
| A9 | webhook TÜM oturumları işaretliyor | ilk deneme **sahte kırmızı**: yazdığım SQL'in kendisi hata verdi, 3 ilgisiz test düştü (tuzak §8). A9b geçerli SQL → 🔴 yalnız `…HuniOturumuOdendiOlur` |
| A10 | CORS yolu ziyaret ucunu kapsamıyor | 🔴 CORS testi |
| A11 | sözleşmede yanıt alanı adı farklı | 🔴 sözleşme doğrulaması |
| A12 | `odeme_oturumlari` yabancı anahtarı düşürüldü (test DB) | 🔴 `testOdemeOturumuZiyaretsizOlamaz`; kısıt geri kondu |
| A13 | huni yanlış alan adı (`ziyaretId`) gönderiyor | **TUTMADI** — `astro check` yeşil: nesne yayma fazla alanı denetlemiyor → tuzak §25. Gövde üretilen tipe bağlandı |
| A13b | aynı, düzeltmeden sonra | 🔴 `ts(2551)` |
| A13c | `ALANLAR`'da yazım hatası (`fbclidx`) | 🔴 `ts(2551)` |

## Ölçüm
- `bin/kontrol` YEŞİL: 52 test / 220 doğrulama (öncesi 41 / 180).
- **Gerçek zincir** (yerel çekirdek + gerçek Stripe test + `stripe listen`,
  Claude in Chrome): `localhost:4321/keepchat/?utm_source=meta&utm_medium=paid_social&utm_campaign=ekim-test&fbclid=IwAR0olcumtest`
  → `POST /huni/ziyaretler` 201 → "Abone ol" → Stripe sandbox, `4242…` →
  `/basarili`; olaylar `checkout.session.completed` + `subscription.created`
  ikisi de 200. Birleşik sorgu: `meta | paid_social | ekim-test |
  IwAR0olcumtest | cs_test_… | ödendi | sub_… | active | test+atif@… | keepchat`.
- Aynı adres iki kez yüklendi → 2 ziyaret satırı (yenileme yeni ziyaret,
  Ç34). İlk DB sorgum sayfanın isteğinden önce koştu ve "0 satır" gösterdi —
  zamanlama, kusur değil.
- Tarayıcıda ilk "Abone ol" tıklaması sayfaya ulaşmadı (önceki bloklarda
  da görüldü); koordinatla ikinci tıklama çalıştı.

## Açık
Ç34 (açık uç şişirilebilir, yenileme = yeni ziyaret, fbclid hukuki
dayanağı Ç12) · panel (2. parça) · reklam ağına dönüşüm (CAPI) · çerez onayı.
