# Blok: Huniye anket + tanıtım adımları — 2026-10-20

## Ne yapıldı
KeepChat hunisi artık tek sayfada adım adım: karşılama → 3 soru (kiminle,
cihaz, öncelik) → tanıtım → paywall. Adımlar `funnel/src/huniler/keepchat.ts`'te
**veri** olarak (metin kod bilmeden düzenlenir); motor
`funnel/src/components/Huni.astro`. Her adıma ulaşılması ve her cevap
çekirdeğe yazılır (`POST /huni/ziyaretler/{ziyaret}/ilerleme` →
`huni_adimlari`, `huni_cevaplari`); panelde "kaç ziyaret hangi adıma
ulaştı" ve cevap dağılımı. Reklam kaydı ve ödeme akışı aynı motorun içinde
(`lib/ziyaret.ts`). Başarı sayfasındaki yanlış yönerge ("uygulamaya aynı
e-postayla giriş yapın" — KeepChat'te e-postayla giriş yok) gerçek yolla
değiştirildi: Ayarlar → Abonelik → Aboneliğimi bağla.

## Neden böyle
- **Kullanıcı kararları (2026-10-20):** metin taslak, kullanıcı düzeltecek
  (Ç36) · cevaplar ve adımlar panelde · tek sayfada adım adım.
- **Tek sayfa:** ziyaret kimliği bellekte kalır, çerez/depo yok (A6).
  Tarayıcı geri tuşu `history.pushState` ile bir önceki adıma döner.
  Reddedilen: her adım ayrı sayfa — kimliği taşımak için depo gerekir.
- **Adım başına satır, "son adım" sütunu değil:** "kaç kişi bu adıma
  ulaştı" doğrudan sayılır; son adım sütunuyla hiç kimsenin takılmadığı
  adımın adı panelde kaybolurdu.
- **Geri dönüp yeniden gelmek bir sayılır, son cevap geçerli** (PK + upsert).
- **Tanım hatası derlemeyi durdurur:** geçersiz/tekrar eden kimlik,
  paywall'ın sonda olmaması — çekirdek bunları 422 ile reddeder, panelde
  adım sessizce eksik kalırdı.
- **Metin yalnız bugün doğru olanı söyler:** Pro hiçbir özelliği açmıyor
  (A5), taslakta özellik vaadi yok.

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| H1 | cevap güncellenmiyor (DO NOTHING) | 🔴 `testGeriDonupYenidenGelmek…` |
| H2 | ziyaret var mı denetimi yok | 🔴 bilinmeyen ziyaret 500 (404 beklenir) |
| H3 | sıra üst sınırı yok | 🔴 `testGecersizIlerleme422…` |
| H4 | cevap biçim denetimi yok | 🔴 aynı |
| H5 | uuid biçim denetimi yok | 🔴 bozuk kimlik 500 |
| H6 | CORS yolu ilerleme ucunu kapsamıyor | 🔴 CORS testi |
| H7 | panel adım sayımında gün aralığı yok | 🔴 `testAnketAraligi…` |
| H8 | oran ilk adım yerine kendine bölünüyor | 🔴 `testAnketAdimlariUlasanVeOran` |
| H9 | anket bölümü veri yokken de görünüyor | 🔴 2 test |
| H9b | cevap sayımı ziyaret başına gruplanıyor | 🔴 oran/cevap testi |
| H10 | tekrar eden adım kimliği | 🔴 derleme: `Huni keepchat: geçersiz ya da tekrar eden adım kimliği "kiminle"` |
| H11 | paywall yok | 🔴 derleme: `paywall tam bir kez ve EN SONDA olmalı` |
| H12 | büyük harfli seçenek kimliği | 🔴 derleme: `geçersiz ya da tekrar eden seçenek "Aile"` |

## Ölçüm
- `bin/kontrol` YEŞİL: 72 test / 328 doğrulama (öncesi 63 / 281).
- **Tarayıcı (Claude in Chrome, yerel çekirdek + huni):** TikTok reklam
  adresiyle anket; ikinci soruda tarayıcı geri tuşu → önceki soru (sayfadan
  çıkmadı), cevap "aile" → "is" değiştirildi; paywall'a kadar. DB: 6 adım
  satırı (her biri bir kez), 3 cevap, `kiminle = is` (son cevap). Organik
  ikinci tur → "Abone ol" → Stripe test oturumu ziyarete bağlandı. Panel:
  adımlar 1–6 ulaşan 2/2, cevaplar 6 satır; reklam 3 / organik 1 ziyaret.
- **Bulgu (yalnız tarayıcıda görüldü):** paywall düğmesi stilsizdi —
  bileşen stili yuvadaki öğeye uygulanmıyor → tuzak §27, `is:global`.
- **Ortam:** Docker'da durdurulan `astro dev` kilit dosyası
  (`.astro/dev.json`, `pid: 20`) bıraktı; yeni konteyner "zaten çalışıyor"
  diyerek açılmadı, `--force` ise aynı PID'i alan **kendini** öldürdü
  (SIGTERM). Doğrusu: kilit dosyasını sil, `--force` kullanma.
- Tarayıcıda sayfa yüklenir yüklenmez yapılan tıklamalar betik yüklenmeden
  gitti (adım ilerlemedi); 1 sn beklemeyle tekrarlandı — araç zamanlaması.

## Açık
Ç36 (metin taslak, Pro faydası tanımsız, yenileme anketi baştan başlatır,
fiyat elle) · Ç34 (açık uç şişirilebilir — ilerleme ucu da aynı).
