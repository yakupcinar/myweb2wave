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
| Reklam atfı (A6/1) | ✅ 52 test, kırma 13/13 (+A13 tutmadı → tuzak §25), gerçek Stripe ile reklam → ödeme → abonelik | `docs/decisions/atif.md` |
| İç panel (A6/2) | ✅ 63 test, kırma 13/14 (P5 tutmadı → yorum düzeltildi), tarayıcıda gerçek veriyle | `docs/decisions/panel.md` |
| Huni anket + tanıtım | ✅ 72 test, kırma 13/13 (3'ü derleme), tarayıcıda geri tuşu + panel | `docs/decisions/huni-anket.md` |
| Huni yayını (A7 / E1.1) | ✅ 94 test, kırma 13/13 (+Y8 deneme kusuru → Y8b), tarayıcıda önizleme + XSS + sürüm | `docs/decisions/huni-yayin.md` |
| Panelde huni düzenleme (E1.2) | ✅ 105 test, kırma 8/8, tarayıcıda düzenle → hata → kaydet → yayınla → geri yükle | `docs/decisions/panel-huni.md` |
| Çok huni + kurallar (A8 / E2.1) | ✅ 117 test, kırma 8/8, tarayıcıda tek adres → iki huni | `docs/decisions/cok-huni.md` |
| Stripe → RevenueCat (A9) | ✅ 126 test, kırma 9/9; gerçek RevenueCat ölçülmedi (Ç43) | `docs/decisions/revenuecat.md` |
| Huni teması (E2.2) | ✅ 141 test, kırma 6/6, tarayıcıda koyu tema + kontrast reddi | `docs/decisions/tema.md` |
| Huni görselleri (E2.3) | ✅ 154 test, kırma 8/8, gerçek HTTP 16 Mpx + 4,6 MB, tarayıcıda kapak/seçenek | `docs/decisions/gorseller.md` |

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
- [ ] ⏸ **Dağıtım (test modu) — ERTELENDİ** (kullanıcı kararı 2026-10-20: Oracle ücretsiz kotası KeepChat + MyTrade'e bölünmüş, ayrı sunucu yok; şimdilik yalnız yerel, sunucuya ileride). Sonuç: KeepChat'te bağlama üretimde kapalı kalır (çekirdek adresi boş); reklam trafiği ölçülemez — ücretli trafikten önce sunucu ŞART. — kullanıcı kararları 2026-10-20: **yeni ayrı sunucu** (keepchat-vps değil, para akışı ayrı — B2) · **Stripe test modu** (canlı ödeme Ç10/Ç12 bekliyor). Kapsam: çekirdek + Postgres + yedek, TLS, huni (statik), Stripe webhook ucu; ölçüt `/health` → `version`. Dosyalar hazır (`deploy/`, `dagitim.md`). Bekleyen: sunucu IP + ssh (kullanıcı açar), iki alan adı, e-posta sağlayıcısı (Ç30), Stripe test webhook uç noktası
  - 2026-10-20 durum: kullanıcıda Oracle Cloud hesabı (KeepChat sunucusu orada) + DuckDNS var, ayrı sunucu yok. Öneri: **aynı Oracle hesabında ikinci Always Free VM** (Ampere A1, Ubuntu 24.04; ücretsiz kota KeepChat VM'iyle paylaşılır) + **iki DuckDNS alt alanı** (API, huni). Ölçüldü: sabitlenmiş beş imajın hepsinde `linux/arm64` var. ⚠️ Oracle'a özgü: VCN güvenlik listesinde 80/443 girişi açılmalı; Oracle Ubuntu imajı kendi iptables kurallarıyla geliyor — `sunucu-hazirla.sh`'in ufw adımı sunucuda ölçülecek
- [x] **Reklam atfı (A6, 1. parça)** — 2026-10-20, `docs/decisions/atif.md`
- [x] **İç panel (A6, 2. parça)** — 2026-10-20, `docs/decisions/panel.md` (kullanıcı adı + şifre, e-posta maskeli)
- [x] **Huni anket + tanıtım** — 2026-10-20, `docs/decisions/huni-anket.md`
- [ ] **Anket metnini kullanıcı düzeltecek** — panelde: `/panel/huniler/keepchat` (Ç36)
- [x] **Huni editörü kararı** — A7: E1'den başla, hedef E2 (2026-10-20)
- [x] **E1.1 huni yayını** — 2026-10-20, `docs/decisions/huni-yayin.md`
- [x] **E1.2 panelde huni düzenleme** — 2026-10-20, `docs/decisions/panel-huni.md`
- [x] **E2.1 çok huni + yönlendirme kuralları** (A8) — 2026-10-20, `docs/decisions/cok-huni.md`
- [x] **Stripe → RevenueCat aktarımı** (A9) — 2026-10-20, `docs/decisions/revenuecat.md`; gerçek ölçüm Ç43
- [x] **E2.2 tema** — 2026-10-20, `docs/decisions/tema.md`
- [x] **E2.3 görseller** — 2026-10-21, `docs/decisions/gorseller.md`
- [ ] **E2.4 sürükle-bırak** (Puck) + bileşen kütüphanesi
- [ ] Reklam ağına dönüşüm (Meta CAPI / TikTok Events) + çerez onayı (A6, Ç12, Ç34) — ücretli trafikten önce
- [ ] KeepChat "aboneliğimi bağla" (KeepChat deposunda) · Apple · Google · hesap silme
- [ ] Ç19/Ç21 (fiyat tek kaynak) — OpenAPI + huni tarafında üretilen tipler; ilk gerçek uç (ödeme oturumu) ile
- [ ] **Ödeme sağlayıcısı araştırması** — Ç10
- [ ] **Ç9 kararı** — uygulamayla eşleme (e-posta / parmak izi), hangi iOS uygulaması
- [x] **`/kontrol`** — `bin/kontrol`, kırma K4–K7

## 2. Backlog

- _—_
