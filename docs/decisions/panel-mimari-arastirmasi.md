# Panel mimarisi — rakipler nasıl kurmuş (2026-10-27)

Kullanıcı: "yapılarını nasıl kurmuşlar, basite kaçmayalım, geliştirmemiz
ileride kesilmesin". Karar: `pre-setup.md` B5. **Kod alınmadı** (kural 12);
yalnız dizin yapısı ve bağımlılık adları okundu.

## Ölçüm
`gh api repos/<depo>/contents/<dizin>` (dizin adları) ve `package.json` /
`composer.json` bağımlılık listesi; web2wave için `web2wave-yigin-arastirmasi.md`.

| Ürün | Arka uç | Panel | Ziyaretçi tarafı | Ortak |
|---|---|---|---|---|
| OpnForm | `api/`: Laravel (cashier, sanctum, horizon, spatie/laravel-data) | `client/`: Nuxt + @nuxt/ui, Pinia, @tanstack/vue-query, chart.js | aynı istemci | — |
| HeyForm | `packages/server`: NestJS + GraphQL (Apollo), Mongoose | `packages/webapp`: React + Radix + Tailwind, Apollo, Zustand, react-router | `packages/form-renderer` (React) + `embed` | `shared-types-enums`, `answer-utils`, `utils` |
| Formbricks | `apps/web` (Next.js, Prisma, OpenAPI, zod) | aynı: Radix + Tailwind, TanStack Query + Table, Recharts, react-hook-form, i18next | `packages/surveys` (**Preact**), `packages/survey-ui` | `types`, `database`, `i18n-utils`, `config-*` |
| openFunnel | Bun, JSON/JSONL, isteğe bağlı Supabase | `apps/admin`, `apps/builder` | `apps/runtime` + bağımlılıksız `packages/engine` | — |
| web2wave | Laravel (çerez biçimi) | ölçülemedi (Cloudflare 403) | sunucuda HTML + jQuery | — |

## Ortak desen
1. Arka uç panel için **veri servisi** (REST/GraphQL/OpenAPI).
2. Panel **ayrı istemci uygulaması**: yönlendirici + veri önbelleği
   (Query/Apollo) + tablo + grafik + form + erişilebilir bileşen (Radix ya da
   eşdeğeri).
3. Ziyaretçi tarafı **ayrı, hafif** paket (Formbricks: Preact).
4. Tipler/şemalar **ortak paket**.

## Bizde (önce)
1, 3, 4 var (Symfony API, Astro+React huni, `huni-bilesenleri`, OpenAPI →
TS). 2 yok: panel Twig, düzenleyici ayrı sayfa (`core/editor`). Görünüm
yenilendi (`panel-gorunum.md`) ama yapı aynı.

## P0 — `core/editor` → `core/panel` (2026-10-27)
Yalnız taşıma, davranış aynı: dizin (`git mv`), compose servisi + birim
(`panel`, `panel-node-modules`), sözleşme kopyası (`/panel-src`), derleme
çıktısı `/varliklar/panel/` (Vite `base`/`outDir`, denetleyici + test
manifest yolu, Dockerfile aşaması, `.dockerignore`, `.gitignore`), kontrol
adımları `panel-*`, CLAUDE.md komut satırı. Eski çıktı dizini silindi.
Geçmiş kayıtlardaki `core/editor` anmaları tarih olarak bırakıldı.
- Kırma: K1 panel sözleşme kopyası bozuk → `cmp: EOF on /tmp/api.ts` 🔴 ·
  K2 betik adresi eski yol → `PanelHuniTest` 1 kırmızı 🔴.
- `bin/kontrol` **YEŞİL**, EXIT=0 (panel-npm/biçim/tip/test/derle, 233
  çekirdek testi).

## P1 — iskelet + Rapor (2026-10-27)
**Kurulan:** `core/panel/src/uygulama/` — `main.tsx` (React Router
`createBrowserRouter`, TanStack Query; yollar dışa açık → test aynı ağacı
bellek yönlendiricisiyle kurar), `Duzen.tsx` (sol menü, `NavLink` →
`aria-current`; Huniler P2'ye kadar düz bağlantı), `api.ts` (openapi-fetch,
tipler sözleşmeden; `oturumDustu` → girişe), `ui/` (cn, Dugme, Kart, Rozet,
Tablo — shadcn yöntemi, cva + tailwind-merge), `bicim.ts` (yüzde "33,3 %",
UTC zaman), `stil.css` (Tailwind 4 `@theme`; açık/koyu değişkenler
`panel-gorunum.md` ile aynı). Vite iki giriş: düzenleyici + uygulama.
Sürümler iki haftadan eski (react-router 8.4.0, @tanstack/react-query
5.104.2, radix-ui 1.8.1, tailwindcss 4.3.3, lucide-react 1.55.0; daha yeni
olanlar bilerek alınmadı); openapi-fetch ve test araçları huniyle aynı.
**Çekirdek:** `/panel` → `panel/uygulama.html.twig` kabuğu (`Varliklar`
servisi manifestten; düzenleyici de onu kullanır), `/panel/api/rapor` JSON
(sözleşme `PanelRaporu`), `panel/index.html.twig` silindi.
**Testler kararla taşındı:** veri kararları (sayım, izin, gün aralığı, 30'a
düşme, maskeli e-posta, anket, serbest yanıt, girişsiz → yönlendirme,
başlıklar) `PanelTest`'te JSON + sözleşme doğrulaması; gösterim kararları
(özet toplamı, yüzde biçimi, şerit genişliği, zararlı metin yazı olarak,
menü işareti, gün adresten) `Rapor.test.tsx`'te.
Marka bağlantısı ilk hâlinde `NavLink`'ti → "bulunulan sayfa" iki öğe;
test yakaladı, düz `Link`.

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | özet yalnız reklam ziyareti | 🔴 Rapor.test |
| K2 | şerit hep %100 | 🔴 (ilk deneme sed eşleşmedi → KOŞULMADI; yeniden) |
| K3 | gün adresten okunmaz | 🔴 |
| K4 | Rapor menüde düz Link | 🔴 |
| K5 | JSON alanı sözleşme dışı ad | 🔴 7 (sözleşme doğrulaması) |
| K6 | gün aralığı denetimi yok | 🔴 1 (sözleşme: enum) |

**Gerçek:** derlenmiş panel, örnek yanıt veren statik önizlemede (oturum
yok — panel şifresi bende değil) masaüstü 1280 px ve 235 px; görünüm Twig
sürümüyle aynı. **Ölçülmedi:** canlı oturumla `/panel` (kullanıcıda).
`bin/kontrol` **YEŞİL**, EXIT=0, 232 çekirdek testi / 1.653 doğrulama
(ilk koşu phpstan: testteki JSON dizisi tipi; `@var` atama satırına).
