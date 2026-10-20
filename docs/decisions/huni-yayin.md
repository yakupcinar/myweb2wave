# Blok: E1.1 — huni tanımı veritabanında, değişmez sürümler, önizleme (A7) — 2026-10-20

## Ne yapıldı
Huni tanımı koddan (`funnel/src/huniler/keepchat.ts`, silindi) veritabanına
taşındı: `huniler.taslak` (düzenlenen) + `huni_surumleri` (yayınlanan her
sürüm **değişmez** anlık görüntü, v1, v2…). Göç, o günkü KeepChat hunisini
taslak + v1 olarak tohumladı. Huni sayfası artık **statik kabuk**
(`components/HuniKabugu.astro`): tanımı çalışma anında `GET /huni/{app}`'ten
okur (60 sn önbellek) ve çizer; metinler yalnız `textContent` ile.
`?onizleme=<anahtar>` ile taslak gösterilir (`GET /huni/{app}/taslak`,
HMAC imzalı, 1 saat, uygulamaya bağlı) — önizlemede hiçbir şey kaydedilmez,
ödeme kapalı. Ziyaret, gördüğü sürümü taşır (`huni_ziyaretleri.surum`).
Doğrulama çekirdekte (`App\Huni\Tanim`) — taslak kaydı ve yayın ondan geçer.
Panel ekranı E1.2'de; şimdilik `bin/console app:huni yayinla|onizleme <app>`.

## Neden böyle
- **A7** (kullanıcı, 2026-10-20): E1'den başla, hedef E2.
- **Çalışma anında okuma** (yayında statik derleme değil): sunucuda derleme
  hattı gerekmez, yayın anında canlı; bedeli ilk boyamada bir istek (Ç38,
  sunucuda ölçülecek).
- **Değişmez sürüm:** hunide ilerleyen ziyaretçi tanımı bir kez belleğe
  alır, yayın onu bozmaz; panel sürümler arası karşılaştırabilir.
- **Doğrulama tek yerde (çekirdek):** eskiden derlemedeydi (huni-anket H10–12);
  tanım artık derlemede yok, aynı kurallar + daha fazlası yayından önce.
- **Önizleme anahtarı, giriş değil:** huni başka kökende; panel oturum
  çerezini oraya taşımak CORS + kimlik bilgisi demek. Anahtar süreli,
  uygulamaya ve süreye bağlı imza.
- **HTML yok:** tanımda serbest HTML alanı yok, çizici `textContent`
  kullanır — panel ele geçse bile huniye betik giremez.

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| Y1 | paywall-sonda kuralı yok | 🔴 2 doğrulama örneği |
| Y2 | adım alan denetimi yok | 🔴 fazla/eksik alan örnekleri |
| Y3 | anahtarlar SIRALI karşılaştırılıyor | 🔴 3 test — veritabanından dönen geçerli tanım reddedildi → tuzak §28 |
| Y4 | yayında doğrulama yok | 🔴 komut testi (geçersiz taslak yayınlandı) |
| Y5 | en eski sürüm okunuyor | 🔴 yayın testi |
| Y6 | sürüm numarası sabit | 🔴 3 test |
| Y7 | anahtarın süresi denetlenmiyor | 🔴 önizleme testi |
| Y8 | imzadan uygulama yalnız DOĞRULAMADA çıkarıldı | **TUTMADI** — kusur denemede: üreten taraf hâlâ uygulamayı imzalıyordu. Y8b iki taraftan da çıkarıldı → 🔴 başka uygulamanın anahtarı kabul edildi |
| Y9 | imza hiç denetlenmiyor | 🔴 |
| Y10 | taslakta `no-store` yok | 🔴 |
| Y11 | yayında `max-age` yok | 🔴 |
| Y12 | ziyaret sürümü doğrudan yazılıyor | 🔴 bilinmeyen sürüm → yabancı anahtar hatası |
| Y13 | huni tanımı ucunda CORS yok | 🔴 |

Çizicinin `textContent` kullanması otomatik testle değil **tarayıcıda**
ölçüldü (huninin birim testi yok).

## Ölçüm
- `bin/kontrol` YEŞİL: 94 test / 433 doğrulama (öncesi 72 / 328).
- Göç tohumunun doğrulamadan geçtiği test ediliyor (yoksa v1 hiç açılmazdı).
- **Tarayıcı (yerel çekirdek + huni):**
  - `/keepchat/` tanımı `GET /huni/keepchat`'ten çizdi (6 adım, `max-age=60, public`); ziyaret `surum = 1`, 2 adım kaydı.
  - Taslağa `<b>KALIN</b><img src=x onerror="document.title='XSS'">` yazıldı, önizleme bağlantısı komutla üretildi: metin **düz yazı** göründü, sayfa başlığı "Taslak başlık" kaldı (betik çalışmadı); sarı önizleme bandı; iki adım ilerlendi → ziyaret sayısı 7 → 7 (kayıt yok).
  - `app:huni yayinla` → `keepchat v2 yayında`; API hemen v2. Tarayıcıda hemen yeniden açılan sayfa **v1** gösterdi (önbellek) ve ziyaret `surum = 1` yazdı — kullanıcının gördüğüyle tutarlı. Önbellek süresi geçince (yayından ~130 sn sonra) yeniden açılan sayfa **"Merhaba — v2"** gösterdi, ziyaret `surum = 2`.

## Açık
Ç38 (60 sn gecikme; ilk boyama sunucuda ölçülmedi; önizleme bağlantısını
alan taslağı görür) · E1.2 panel düzenleme ekranı · Ç36 (metin taslak).
