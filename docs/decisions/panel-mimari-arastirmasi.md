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
