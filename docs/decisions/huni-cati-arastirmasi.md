# Huni çatısı: Next.js mi Astro mu — rakip ölçümü, 2026-10-21

Soru (kullanıcı): anket kısmı için "web2wave gibi Next.js + TypeScript" mi,
Astro mu? Bugünkü karar B2 (Astro statik huni + Symfony çekirdek).

## Ölçüm (curl, 2026-10-21; HTML'deki çatı izleri + betik sayısı)

| Ürün | Anket/huni sayfası | Çatı | Not |
|---|---|---|---|
| **web2wave** | `api.web2wave.com` (yeniden ölçüldü) | **Laravel, sunucuda HTML + jQuery 3.7.1** | `XSRF-TOKEN` + `quiz_session` çerezleri; Next.js izi YOK — "web2wave Next.js" varsayımı yanlış (`web2wave-yigin-arastirmasi.md` 2026-10-19 ile aynı) |
| RevenueCat Funnels | `signup.cat` | **Next.js App Router** (523 `/_next/`, `self.__next_f`) | barındırılan, sunucuda üretim |
| BetterMe | `quiz.betterme.world` | **Next.js** (pages router, `__NEXT_DATA__`) | pazarlama sitesi de Next.js |
| WalkFit (Welltech) | `walk-fit.io/onboarding` | **Vite + React SPA**, boş `#root`, tek betik **1,8 MB** | pazarlama sitesi `walkfit.pro` **Astro** (147 `/_astro/`) |
| Muscle Booster (Welltech) | `plan.muscle-booster.io/onboarding` | **Vite + React SPA** (aynı kabuk, 11 KB HTML) | pazarlama sitesi **Astro** |
| Simple | `simple.life/survey/` | **Vite SPA** (`#app`, CDN'den sürümlü paket) | pazarlama sitesi Astro izi |
| FunnelFox | `<proje>.fnlfx.com/<takma ad>` | **ÖLÇÜLMEDİ** — canlı huni adresi bulunamadı | pazarlama `funnelfox.com` + `fnlfx.com` Astro; panel `app.funnelfox.com` Svelte |
| **Biz** | `funnel/dist/keepchat/` | Astro statik kabuk + düz TypeScript | HTML 1,6 KB, JS ~12 KB (3 dosya); içerik çalışma anında `GET /huni/{app}` |

## Bulgular
1. **Tek bir "sektör çatısı" yok.** Huni çalışma zamanı üç yolda: sunucuda
   HTML (web2wave/Laravel, RevenueCat/Next.js, BetterMe/Next.js), istemcide
   SPA (Welltech, Simple — Vite + React/Vue), statik kabuk (biz).
2. **Astro, rakiplerde pazarlama sayfasının çatısı** (Welltech, Simple,
   FunnelFox); anketin kendisi değil. Anket her yerde ya sunucu ya React/Vue
   uygulaması.
3. **Asıl fark çatı değil, anket bileşenlerinin React olup olmaması.**
   Bizde Puck düzenleyicisi React; huni ise düz TypeScript → aynı bloğun iki
   çizimi var (önizleme "taklit eder", `puck.md`). Next.js'e geçmeden Astro
   içinde React adacığıyla aynı bileşenler huniye taşınabilir.
4. **Next.js'in kazandıracağı tek şey sunucuda üretim** (panel içeriği ilk
   HTML'de; bugün tarayıcı önce kabuğu, sonra `GET /huni/{app}` alıyor).
   Bedeli: herkese açık trafikte Node süreci — B2'nin kaldırdığı yüzey
   (self-hosted Next.js CVE'leri B2'de). Bu bekleme süresi ÖLÇÜLMEDİ.

## Karar → pre-setup B4 (2026-10-21, kullanıcı)
React tabanlı huni oynatıcısı, Puck ile ortak bileşenler, sunucusuz (Next.js yok).
Geçiş planı `PLAN.md` §1 (H0–H6). Aşağıdaki öneri kararın öncesindeki hâlidir.

## Öneri (karar öncesi) → Ç48
Astro kalır (B2). Anket bileşenleri E2.4b'de **React adacığına** taşınır,
Puck ile aynı bileşenler (önizleme = huni). Next.js yalnız şu ölçülürse
yeniden tartılır: içerik isteğinin yavaş mobil ağda ilk anlamlı boyamaya
eklediği süre dönüşümü bozacak kadar büyük ve yayında statik üretimle
(yayınla → sayfayı derle) çözülemiyor.

Kaynaklar: <https://www.revenuecat.com/docs/tools/funnels>,
<https://funnelfox.com/docs/editor/publishing.md>, <https://blog.funnelfox.com/quiz-funnel-guide/>;
çatı izleri doğrudan curl ile.
