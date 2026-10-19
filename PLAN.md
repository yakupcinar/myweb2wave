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

## 1. Şu an üzerinde çalışılan AKTİF GÖREVLER (en çok 3-4)

- [x] **Yığın kararı** — B1, 2026-10-19
- [x] **İskelet** — 2026-10-19, `docs/decisions/iskelet.md`
- [x] **CI** — 2026-10-19, `docs/decisions/ci.md` (yeşil→kırmızı→yeşil)
- [ ] **Stripe test ödemesi + sözleşme (R2)** — OpenAPI + huni tarafında üretilen tipler; ilk gerçek uç (ödeme oturumu) ile
- [ ] **Ödeme sağlayıcısı araştırması** — Ç10
- [ ] **Ç9 kararı** — uygulamayla eşleme (e-posta / parmak izi), hangi iOS uygulaması
- [x] **`/kontrol`** — `bin/kontrol`, kırma K4–K7

## 2. Backlog

- _—_
