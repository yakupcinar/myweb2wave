# PLAN — Aktif Sprint

> **YÖNLENDİRİCİ, arşiv değil.** Burada yalnız *şu an yapılan* ve
> *sıradaki* durur. Bitmiş iş `docs/decisions/` altına **tam metniyle**
> taşınır. ⚠️ Satır bütçesi 400 — `wc -l PLAN.md`.

## 0. Durum — tek bakışta

| Katman | Durum | Kanıt |
|---|---|---|
| Ürün kapsamı | ✅ A1–A3 | `docs/pre-setup.md` §A |
| Yığın seçimi | ✅ B2 Astro + Symfony | `docs/pre-setup.md` §B2 |
| İskelet + /kontrol | ✅ 2 test, 7/7 kırma kırmızı | `docs/decisions/iskelet.md` |
| CI | ✅ GitHub Actions = `bin/kontrol`, kırma ölçüldü | `docs/decisions/ci.md` |
| Stripe çekirdek | ✅ 12 test, kırma 10/10, gerçek Stripe test API + yeniden gönderim | `docs/decisions/stripe-odeme.md` |
| Huni paywall + R2 | ✅ 15 test, kırma 8/8, tarayıcıda E1–E8 | `docs/decisions/huni-paywall.md` |
| Uygulama boyutu | ✅ 19 test, kırma 7/7, gerçek ödeme `metadata.app` | `docs/decisions/uygulama-boyutu.md` |
| Rakip araştırması | ✅ 5 araç × 4 soru + akış özeti, 5 bulgu → Ç24–Ç28 (kod yok) | `docs/decisions/rakip-arastirmasi.md` |
| Kimlik + hak | ✅ 41 test, kırma 11/11 (2'si eksik testi buldu), web ödemesi → bağlama → hak uçtan uca | `docs/decisions/kimlik-hak.md` |
| Dağıtım dosyaları | 🟡 yerelde uçtan uca TLS + sürüm ölçümü, kırma 10/10 (+K9 tutmadı → iki katman ölçüldü); **sunucu bekleniyor** | `docs/decisions/dagitim.md` |

## 1. Şu an üzerinde çalışılan AKTİF GÖREVLER (en çok 3-4)

- [x] **Yığın kararı** — B1, 2026-10-19
- [x] **İskelet** — 2026-10-19, `docs/decisions/iskelet.md`
- [x] **CI** — 2026-10-19, `docs/decisions/ci.md` (yeşil→kırmızı→yeşil)
- [x] **Stripe test ödemesi (çekirdek)** — 2026-10-19, `docs/decisions/stripe-odeme.md`
- [x] **Huni paywall + sözleşme (R2)** — 2026-10-19, `docs/decisions/huni-paywall.md` (E1–E8)
- [x] **Uygulama boyutu (A4)** — 2026-10-19, `docs/decisions/uygulama-boyutu.md`
- [x] **Rakip araştırması** — 2026-10-19, `docs/decisions/rakip-arastirmasi.md` (Ç24–Ç27)
- [x] **Araştırma sonrası kararlar** — A5 (Ç24–Ç27), A6 (Ç28), 2026-10-19
- [x] **Kimlik + e-posta kodu + hak ucu** — 2026-10-19, `docs/decisions/kimlik-hak.md`
- [ ] **KeepChat "aboneliğimi bağla"** — KeepChat deposunda, **KeepChat klasöründe ayrı oturumda** (hook'ları ve kuralları yüklensin). Kullanıcı kararları 2026-10-19: **yalnız durumu göster** (hiçbir özellik kilitlenmez → Ç29'a takılmaz) · **sunucu + iOS** (web sonra). Kılavuz: `docs/uygulama-entegrasyonu.md`
- [x] KeepChat tarafı yazıldı — KeepChat `7bdc46a` (§A39): sunucu + iOS, Go 12/12 + iOS 4/4, kırma 11/11; çekirdek adresi boş → üretimde KAPALI
- [ ] **Dağıtım (test modu)** — kullanıcı kararları 2026-10-20: **yeni ayrı sunucu** (keepchat-vps değil, para akışı ayrı — B2) · **Stripe test modu** (canlı ödeme Ç10/Ç12 bekliyor). Kapsam: çekirdek + Postgres + yedek, TLS, huni (statik), Stripe webhook ucu; ölçüt `/health` → `version`. Dosyalar hazır (`deploy/`, `dagitim.md`). Bekleyen: sunucu IP + ssh (kullanıcı açar), iki alan adı, e-posta sağlayıcısı (Ç30), Stripe test webhook uç noktası
- [ ] **Analitik + reklam atfı + çerez onayı** (A6) — canlıdan önce
- [ ] KeepChat "aboneliğimi bağla" (KeepChat deposunda) · Apple · Google · hesap silme
- [ ] Ç19/Ç21 (fiyat tek kaynak) — OpenAPI + huni tarafında üretilen tipler; ilk gerçek uç (ödeme oturumu) ile
- [ ] **Ödeme sağlayıcısı araştırması** — Ç10
- [ ] **Ç9 kararı** — uygulamayla eşleme (e-posta / parmak izi), hangi iOS uygulaması
- [x] **`/kontrol`** — `bin/kontrol`, kırma K4–K7

## 2. Backlog

- _—_
