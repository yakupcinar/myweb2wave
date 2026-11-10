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
| Huni adım ayarları | ✅ 187 çekirdek testi + editör/oynatıcı testleri, kırma 3/3; panel görsel kontrolü bekliyor | `docs/decisions/huni-adim-ayarları.md` |
| Huni çoklu seçim (E2.4b) | ✅ 191 çekirdek testi, editör 18 + bileşen 20 + huni 37 test; kırma 4/4; yerel API/DB göçü + HTTP 200 | `docs/decisions/huni-coklu-secim.md` |
| Huni müşteri yorumu (E2.4b) | ✅ 192 çekirdek testi, editör 20 + bileşen 21 + huni 38 test; kırma 3/3; sözleşme, önizleme ve oynatıcı | `docs/decisions/huni-yorum.md` |
| Soru açıklaması (E2.4c) | ✅ 193 çekirdek testi, editör 22 + bileşen 22 + huni 38 test; kırma 3/3; boşken yok, tanım ↔ Puck ↔ önizleme | `docs/decisions/huni-soru-aciklamasi.md` |
| Huni serbest yanıt (E2.4b) | ✅ 198 çekirdek testi / 1.239 kontrol; editör 19, bileşen 11, oynatıcı 37; kırma 3/3; göç + gerçek HTTP 204 ve DB kaydı; `bin/kontrol` yeşil | `docs/decisions/huni-serbest-metin.md` |
| Huni görselleri (E2.3) | ✅ 154 test, kırma 8/8, gerçek HTTP 16 Mpx + 4,6 MB, tarayıcıda kapak/seçenek | `docs/decisions/gorseller.md` |
| Sürükle-bırak (E2.4a, Puck) | ✅ 155 + 5 test, kırma 6/6, tarayıcıda sürükle + kaydet + hata | `docs/decisions/puck.md` |
| Huni çatısı (B4) | 🟡 karar ✅, H0 ✅ ölçüldü, H1 ✅ ortak bileşen + tek tema/CSS, H2 ✅ React oynatıcı (22 test, JS 73,2 KB gzip), H3 ✅ önizleme = huni (DOM + stil farkı 0), H4 ✅ CSS geçişleri, H5 ✅ dallanma (A11), H6 bekliyor | `docs/decisions/huni-cati-arastirmasi.md`, `huni-bilesenleri-h1.md`, `huni-oynatici-h2.md`, `huni-onizleme-h3.md`, `huni-gecisler-h4.md`, `huni-dallanma-h5.md` |
| Ek ürün upsell (M3c) | ✅ 587 çekirdek testi, kırma 16/16, Chrome'da gerçek abonelik + tek tık €19,99 (kayıtlı kart) | `docs/decisions/m3c-ek-urun-upsell.md` |
| Ödeme sonrası (M3b) | ✅ 575 çekirdek testi, kırma 9/9, gerçek oturum dönüş adresi + Chrome'da çizim | `docs/decisions/m3b-odeme-sonrasi.md` |
| Downsell (M3a) | ✅ 571 çekirdek testi, kırma 10/10, Chrome'da gerçek geri tuşuyla | `docs/decisions/m3a-downsell.md` |
| Gömülü ödeme (M2d) | ✅ 564 çekirdek testi, kırma 10/10, Chrome'da test kartıyla gerçek abonelik (eur 999, gömülü); cüzdanlar sunucuda | `docs/decisions/m2d-gomulu-odeme.md` |
| Çok paywall (M2c) | ✅ 561 çekirdek testi, kırma 4/4, Chrome'da iki cevap → iki paywall | `docs/decisions/m2c-cok-paywall.md` |
| Sayaç + güvence (M2b) | ✅ 559 çekirdek testi, kırma 17/17, gerçek Stripe'ta bitiş öncesi 0 / sonrası 999, onay metadata'da | `docs/decisions/m2b-sayac-guvence-onay.md` |
| Plan seçici (M2a) | ✅ 549 çekirdek testi, kırma 15/15, ekran + Chrome + gerçek yıllık ödeme; Puck alanları tarayıcıda bekliyor (Ç110) | `docs/decisions/m2a-plan-secici.md` |
| Çok para birimi (M1c) | ✅ 537 çekirdek testi, kırma 20/20 (K21 testi buldu), gerçek Stripe'ta 6 ülke paywall = ödeme, Adaptive kapalı; dağıtım betiği düzeltildi (§49) | `docs/decisions/m1c-cok-para-birimi.md` |
| Teklifler (M1b) | ✅ 523 çekirdek testi, kırma 20/20, gerçek Stripe'ta üç teklif (0 € / 0,99 € / 4,99 bugün); panel formu tarayıcıda bekliyor (Ç108) | `docs/decisions/m1b-teklifler.md` |
| Niş şablonları (V6) | ✅ 4 şablon, 503 çekirdek testi, kırma 15/15, 57 adım ekranda; 17 boşluk uyarısı (Ç107) | `docs/decisions/v6-nis-sablonlari.md` |
| Yeni bloklar (V5) | ✅ 495 çekirdek testi, kırma 13/13, ekran + Chrome ölçümü; JS 18,6 KB / 30 | `docs/decisions/v5-yeni-bloklar.md` |
| Hareket (V4) | ✅ 483 çekirdek testi, kırma 12/12, Chrome'da ölçüldü; Ç101 kapandı | `docs/decisions/v4-hareket.md` |
| Stok fotoğraf (V3b) | ✅ testlerle (sahte Pexels); gerçek Pexels anahtar bekliyor (Ç103); kırma 12/12 | `docs/decisions/v3b-stok-fotograf.md` |
| Fluent simgeler (V3a) | ✅ 470 çekirdek testi, kırma 11/11, gerçek GitHub + Chrome + panel önizlemesinde ölçüldü | `docs/decisions/v3a-fluent-simgeler.md` |
| Hazır temalar (V2) | ✅ 12 tema, panel seçici Puck'ta ölçüldü, kırma 8/8 | `docs/decisions/v2-hazir-temalar.md` |
| AI tasarım döngüsü (V1) | ✅ 459 çekirdek testi, kırma 8/8; yapışık düğme gerilemesi (§45) düzeldi; uyku-ornek 16/16 uyarısız | `docs/decisions/v1-tasarim-dongusu.md` |
| E-posta adımı (E1) | ✅ 454 çekirdek testi, kırma 19/20 (K16 kodu kaldırttı), gerçek Stripe oturumunda customer_email; gerçek ziyaretle tarayıcı yürüyüşü bekliyor (Ç98) | `docs/decisions/e1-eposta-adimi.md` |
| Fiyat kataloğu (M1a) | ✅ 437 çekirdek testi, kırma 15/15, gerçek Stripe test API; panel tarayıcıda bekliyor (Ç96) | `docs/decisions/m1a-fiyat-katalogu.md` |
| Giriş bağlantısı (A10) | ✅ 168 test, kırma 10/10 (+K7 çift yedek filtre), gerçek HTTP + tarayıcıda geç webhook; KeepChat tarafı yok (Ç47) | `docs/decisions/giris-baglantisi.md` |

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
- [x] **E2.4a sürükle-bırak (Puck)** — 2026-10-21, `docs/decisions/puck.md`
- [x] **Ödeme sonrası giriş bağlantısı** (A10, Ç46) — 2026-10-21, `docs/decisions/giris-baglantisi.md`
- [ ] **KeepChat: giriş bağlantısı** — KeepChat deposunda ayrı oturumda: `keepchat://` şeması + dinleyici + sunucuda `/apps/keepchat/giris/kullan` + `Purchases.logIn(kisi)` (Ç47, kılavuz `docs/uygulama-entegrasyonu.md` §3b)
- [x] **Huni çatısı kararı** — B4 (2026-10-21): React huni oynatıcısı, Puck ile ortak bileşen, sunucusuz; `docs/decisions/huni-cati-arastirmasi.md`
- [x] **Huni oynatıcısı React'e geçiş (B4)** — sıra önemli; her H ayrı blok (ölç → kır → tarayıcı). Bugünkü kod ölçüldü: huni `funnel/src/components/HuniKabugu.astro` (370 satır düz TS, `textContent`, testi YOK — davranış yalnız tarayıcıda denendi); Puck `core/editor/src/yapilandirma.tsx` 3 bileşeni (`Tanitim`, `Soru`, `Paywall`) satır içi stille YENİDEN çiziyor; tema formülü (`parlaklik`, yazı tipi yığınları) üç yerde (huni, düzenleyici, `Tema.php`) — ✅ H0–H6 tamam; 2026-11-03 Astro da kaldırıldı (`astro-kaldirma.md`)
  - [x] **H0** — 2026-10-21, `docs/decisions/huni-oynatici-h0.md`: anket JS 4,8 KB gzip, en küçük React 67,4 KB gzip; içerik 3 sıralı ağ adımından sonra (tanım isteği JS'i bekliyor); 15 davranış (D1–D15); bütçe önerisi ≤ 90 KB gzip; yavaş mobil ölçülmedi (Ç50). Eski metin: kod yok. (a) bugünkü JS (~12 KB, 3 dosya) ve yavaş mobil ağda ilk anlamlı boyama (B4'ün ölçülmemiş varsayımı; araç: Chrome, ağ kısıtlı); (b) korunacak davranışların listesi = H2'nin test listesi: önizlemede ölçüm yok + ödeme kapalı · geri tuşu önceki adım · adımda başlığa odak · ilerleme çubuğu · ziyaret/adım/cevap bildirimi (A6) · seçim parametreleri yalnız k/utm/huni (A8) · görsel yalnız 64-hex kimlikten · tema + düğme yazısı kontrastı · ödeme hata metinleri (502 / ağ) · `?onizleme=` süresi dolmuş metni · metin HTML olarak ASLA yorumlanmaz; (c) React bütçesi: ölçülen sayıya göre üst sınır (öneri ≤ 120 KB gzip), `bin/kontrol`'de ölçülür
  - [x] **H1** — 2026-10-21, `docs/decisions/huni-bilesenleri-h1.md`: `huni-bilesenleri/` (`Tanitim`/`Soru`/`Paywall` + `tema.ts` + `huni.css`), bağımlılıksız — düzenleyicinin `node_modules`'u bağlanır, `dedupe` + `tek-react` bekçisi; Puck ve huni sayfası aynı CSS/tema; `Tema.php` ile ortak örnek dosyası; `bin/kontrol` +4 adım (`html-yok` dahil). Kalanlar Ç51
  - [x] **H2** — 2026-10-21, `docs/decisions/huni-oynatici-h2.md`: `funnel/src/oynatici/Huni.tsx` tek React adacığı (`client:only`), H1 bileşenleriyle; tanım isteği satır içi betikte (JS'i beklemiyor); D1–D15 = 22 test, kırma 27 deneme · 36 koşu (2 sahte/koşulmadı → düzeltildi, 2 tutmadı → gereksiz satır kaldırıldı); JS 73,2 KB gzip (`funnel-butce` ≤ 90 KB). Kalanlar Ç52 (ikinci JS turu, ölçülemeyen duvar saati)
  - [x] **H3** — 2026-10-21, `docs/decisions/huni-onizleme-h3.md`: aynı tanım iki bağımsız yoldan (Puck alanları / tanım → `Adim`) aynı DOM (düzenleyici testi, kırma 6/6); tarayıcıda 9 öğe × 12 hesaplanmış özellik fark 0; geçerli önizleme bağlantısı yürüdü. Klasik düzenleyici kaldı (Ç45, kullanıcı kararı)
  - [x] **b1 · yayınla/önizleme/sürümler Puck'ta** (Ç45) — 2026-10-21, `docs/decisions/puck-yayin-b1.md`: üst çubuk Kaydet · Önizle · Yayınla · Sürümler; 3 JSON ucu; kaydedilmemişken yayın/önizleme/geri yükleme kapalı; kırma 9/10 (+S9 tutmadı → koruma access_control'da, S9b). Safari dar ekran menüsü düzeltildi ve görsel kontrol edildi (94b8eee). Sırada **b2** (kullanıcı Puck'ı gerçek kullanımda denedikten sonra klasik düzenleyiciyi silmek; Ç53).
  - [x] **Açık kaynak web-funnel/anket arayüzü repo listesi** — 2026-10-23: yüksek yıldızlı Formbricks, HeyForm, SurveyJS Library, OpnForm, Xiaoju Survey; doğrudan huni örnekleri OpenFunnel / OpenFunnels. Yıldız, lisans ve UI inceleme hedefi `docs/decisions/huni-arayuz-repo-arastirmasi.md` içinde. Sıradaki görsel araştırma iki doğrudan huni örneğiyle başlar. Kod alınmaz (CLAUDE.md §2.12).
  - [x] **Puck üst çubuk durum görünürlüğü** — 2026-10-23: kaydedilmemiş / yayına hazır taslak / yayındaki sürüm renkli ve ekran okuyucuya duyurulan etiket; HeyForm/OpenFunnels yayın akışından davranışsal esin, kod kopyası yok. Editör 24/24 test + üretim derlemesi. Ayrıntı: `docs/decisions/huni-arayuz-repo-arastirmasi.md`.
  - [x] **Puck soru arketipleri** — 2026-10-23: tek seçim / çoklu seçim / serbest yanıt ekleme menüsünde ayrı hazır blok; kayıtta tek `soru` şeması korunur. OpenFunnel README davranış fikri; kod yok. Editör 26/26 + TypeScript denetimi. Ayrıntı: `docs/decisions/huni-arayuz-repo-arastirmasi.md`.
  - [x] **Ada önyükleme** (Ç52 (1)) — 2026-10-21, `docs/decisions/ada-onyukleme.md`: derlemede `modulepreload`; ikinci JS turu kalktı, içerik yalnız tanımı bekliyor (ısınmış: ilk bildirim 142 ms, H0 230)
  - [x] **H4** — 2026-10-21, `docs/decisions/huni-gecisler-h4.md`: yalnız CSS (`@keyframes`, 220 ms), ilk adımda yok (`data-gecis`), `prefers-reduced-motion`'a uyar; JS +23 B; kırma 5/5. Kalanlar Ç54
  - [x] **H5** — 2026-10-21, `docs/decisions/huni-dallanma-h5.md` (A11): seçenek ve tanıtım düğmesi → hedef adım, yalnız ileri; skor yok; sözleşme + çekirdek + oynatıcı + Puck açılır listesi; kırma 9/9. XState/Zustand GEREKMEDİ (iki `useState` yetti). Kalanlar Ç55
  - [x] **H6a · onay şeridi** (A12) — 2026-10-21, `docs/decisions/huni-izin-h6a.md`: herkese Kabul/Reddet; tıklama kimlikleri yalnız izinle; `docs/decisions/huni-izin-geri-alma.md`: ayarı yeniden açıp aktif ziyaretten izni geri alır, kuyruktaki paylaşım iptal edilir. Ç56 hukuki metin/bağlantı ve raporlama açık.
  - [x] **H6b · sunucu dönüşümü** — 2026-10-23, `docs/decisions/huni-donusum-h6b.md`: Stripe webhook'unda yalnız izinli ve ödenmiş ziyaret için Meta CAPI + TikTok Events API; kuyruk/yeniden deneme; IP/UA geçici tutulur ve kuyruk yükü en geç 7 günde silinir. Gerçek reklam hesabı doğrulaması için Meta Pixel ID + erişim anahtarı + test olay kodu ve TikTok Pixel ID + erişim anahtarı gerekir; henüz yapılmadı.
  - [x] **H6c · tarayıcı pikselleri** — `docs/decisions/huni-donusum-h6c.md`; izin + doğrulanmış satın alma sonrası Meta `Purchase` / TikTok `CompletePayment`, sunucu ile aynı Stripe `event_id`; yerel test ve gerçek HTTP geçti. Reklam hesabı doğrulaması ve Ç56 hukuk/izin açık.
  - [x] **H6 · reklam olayları** — H6a–c tamamlandı: izin şeridi, çekirdek ölçümü, Meta + TikTok sunucu/tarayıcı satın alma olayları ve aynı kimlikle tekilleştirme. H6a.1 aktif ziyaretten izni geri alır. GA4 A12'de reddedildi; canlı hesap testi ve Ç56 hâlâ ücretli trafik ön koşulu.
- [x] **E2.4b ilk yeni soru türü: çoklu seçim** — 2026-10-22, `docs/decisions/huni-coklu-secim.md`
- [x] **E2.4b müşteri yorumu/sosyal kanıt adımı** — 2026-10-22, `docs/decisions/huni-yorum.md`
- [x] **E2.4c soru açıklaması** — 2026-10-22, `docs/decisions/huni-soru-aciklamasi.md`
- [x] **E2.4b serbest metin soru türü** — 2026-10-23, `docs/decisions/huni-serbest-metin.md`
- [ ] Reklam ölçümünün canlıya hazır olması — H6b sunucu aktarımı tamam; H6c tarayıcı pikselleri + Ç56 hukuk/izin açıkları tamamlanmadan ücretli trafikten önce kapanmaz.
- [ ] KeepChat "aboneliğimi bağla" (KeepChat deposunda) · Apple · Google · hesap silme
- [x] Ç19/Ç21 (fiyat tek kaynak) — OpenAPI + huni tarafında üretilen tipler; ilk gerçek uç (ödeme oturumu) ile — ✅ L10 ile yapıldı (`fiyat-tek-kaynak.md`)
- [x] **Ödeme sağlayıcısı** — Ç10 kararı 2026-11-03 (kullanıcı): **canlıda da Stripe**; Paddle ücretleri yüksek, reddedildi. Canlıya geçişte Stripe hesabının şirket/ülke koşulu (DE) yine çözülmeli
- [ ] **Ç9 — uygulamaya giriş** (kullanıcı 2026-11-03: ana yöntem e-posta kodu; araştırma `docs/decisions/eposta-kodu-arastirmasi.md` — değerler sektör aralığında). Kalan: giriş bağlantısı Universal Link + yedek sayfa (sunucu/alan adıyla) · KeepChat'te `.oneTimeCode` · Apple/Google girişi + hesap silme (KeepChat deposu) · sıradaki iOS uygulaması
- [x] **`/kontrol`** — `bin/kontrol`, kırma K4–K7
- [x] **DÖNGÜ KUYRUĞU — A13** (kararlar 2026-10-26, `pre-setup.md` A13). Sırayla, her biri ayrı blok (`/blok`), bitince commit + push; dış girdi gerekirse açık uca yaz, atla. İşaretle ve `docs/decisions/`e taşı: — ✅ L1–L20 tamam
  - [x] L1 · Ç60 dev DB göçü + `bin/kontrol` uyarı adımı · Ç62 `reference.php` `.gitignore` — `docs/decisions/dev-goc-uyari.md`
  - [x] L2 · Ç58 izin yarışı — istemcide sıralama — `docs/decisions/huni-izin-yarisi.md`
  - [x] L3 · Ç59 terk edilen ödeme IP/UA — `checkout.session.expired` + 48 sa cron — `docs/decisions/odeme-ip-temizligi.md`
  - [x] L4 · Ç61 Puck: yalnız 3 hazır soru türü, kayıttan doğru türle açılış, benzersiz kimlik — `docs/decisions/puck-soru-turleri.md` (tarayıcı: kullanıcı bakacak)
  - [x] L5 · Ç55 ulaşılamayan adım uyarısı + yeniden adlandırmada hedef güncelleme — `docs/decisions/puck-akis-denetimi.md` (tarayıcı: kullanıcı)
  - [x] L6 · Ç61 huni: görünür geri düğmesi + okunur gizlilik bağlantısı — `docs/decisions/huni-geri-gizlilik.md`
  - [x] L7 · Ç61 paywall: fayda listesi + şartlar/gizlilik/iptal bağlantı alanları — `docs/decisions/paywall-fayda-yasal.md`
  - [x] L8 · Ç61 tema: logo + masaüstü düzeni — `docs/decisions/huni-logo-masaustu.md`
  - [x] L9 · Ç44 görsel alt metni (+ logo medya kontrolü) — `docs/decisions/gorsel-alt-metni.md`
  - [x] L10 · Ç19/Ç21 fiyat tek kaynak (Stripe → çekirdek → paywall); Adaptive Pricing açık kalır (Ç19 yerel para farkı açık) — `docs/decisions/fiyat-tek-kaynak.md` (kullanıcı: paywall metninden elle fiyatı sil)
  - [x] L11 · Ç53 b2 klasik düzenleyiciyi sil — `docs/decisions/puck-tek-duzenleyici-b2.md`
  - [x] L12 · Ç39 eşzamanlı kayıt 409 — `docs/decisions/eszamanli-kayit.md`
  - [x] L13 · Ç41 panel oturumu 8 sa + uyarı · Ç35 oturumlar DB'de — `docs/decisions/panel-oturum.md`
  - [x] L14 · Ç41 huni arşivleme/silme — `docs/decisions/huni-arsiv.md`
  - [x] L15 · Ç34 ziyaret hız sınırı + sekmede ziyaret sürdürme (+ güvenilir vekil, H6b IP hatası) — `docs/decisions/ziyaret-hiz-siniri.md`
  - [x] L16 · Ç56-3 izin oranı raporu — `docs/decisions/izin-orani.md`
  - [x] L17 · Ç18 webhook bütünlük testi · Ç20 past_due test clock — `docs/decisions/webhook-butunluk-past-due.md`
  - [x] L18 · 2026-11-02, `l18-baglanti-dependabot.md`: `funnel-baglanti` kontrol adımı (eski Ç23 hatasını yakaladı) · `.github/dependabot.yml` (cooldown 14 gün, şemaya karşı geçerli); Dependabot `main` dalına birleşince çalışır (Ç80)
  - [x] L19 · 2026-11-02, `l19-gecisler.md`: ileri sağdan / geri soldan, yumuşak ilerleme dolgusu (yerel progress erişilebilirlik için kaldı); hareket azaltma gerçek tarayıcıda ölçüldü (hiç animasyon yok). Kırma 6/6
  - [x] L20 · 2026-11-02, `l20-preact.md`: huni Preact (compat) — JS 77,4 → 19,7 KB, görüntü farkı 0, testler Preact'le 73/73; bütçe 30 KB (Ç81 onay)
- [x] **Huni mobil görünüm** (kullanıcı 2026-10-27: "huniyi sevmedim, örnek repolara bak"; yön: mobil uygulama gibi). `docs/decisions/huni-mobil-gorunum.md` — ✅ 1–5 tamam
  - [x] 1 · görünüm: seçenek kartları + işaret, alta yapışık düğme, plan kartı — 2026-10-27
  - [x] 2 · tek seçimde otomatik ilerleme — zaten vardı
  - [x] 3 · 2026-11-02, `docs/decisions/mobil-gorunum-3-5.md`: seçenek `simge` (tek emoji, görselle değil, `aria-hidden`); ekran kabına renkli emoji fontu (AI kutu görüyordu); bölünen emoji taşması düzeltildi. Kırma 8/8
  - [x] 4 · 2026-11-02, `mobil-gorunum-3-5.md`: `yukleme` adım türü (düğmesiz, süre dolunca oynatıcı geçirir, geri dönünce iptal); ekran çekicisi hareket azaltmayı öykünür (AI son hâli görür). Kırma 10/10
  - [x] 5 · 2026-11-02, `mobil-gorunum-3-5.md`: sonuç sayfaları huni iskeleti + huninin teması (oturum belleğiyle taşınır, sunucu/adres değişmedi). Kırma 6/6
- [x] **Huni görünüm yüzeyi** (Ç71 (b); kullanıcı 2026-10-27: "AI birebir aynı quiz verdi, renk farkı yok yazı farkı yok"). Bugün görünüm = huni başına 5 tema alanı + logo; adım başına çeşit yok. (a) AI yönergesi yapıldı (`docs/decisions/ai-tema-yonergesi.md`). Her adım `/blok`; her yeni alan = sözleşme `HuniTemasi`/adım şeması + `Tema.php`/`Tanim.php` + `huni-bilesenleri` + Puck alanı + `Tema::aiYonergesi` örnekleri (AI ancak sözleşmedekini üretebilir): — ✅ G1–G7 tamam
  - [x] G1 · 2026-10-27, `docs/decisions/gorunum-g1.md`: envanter (web2wave, Formbricks) + 19 varyant × 3 adım ekran ölçümü; zemin baskın, yapısal ayarlar ikinci, düğme/köşe küçük. Sıra aşağıda (ölçüme göre); Ç75, Ç76
  - [x] G3 · 2026-10-27, `docs/decisions/gorunum-g3.md`: `tema.zemin` geçiş / görsel + karartma (en kötü görsele göre eşik, hata en az değeri söyler); Puck alanları, AI örnekleri; yan bulgu: tema boyamadan sonra uygulanıyordu → `useLayoutEffect` (§40). Kırma 11/11
  - [x] G6 · 2026-10-27, `docs/decisions/gorunum-g6.md`: `tema.kartRengi`; kart varsa okunurluk metin ↔ kart (koyu zemin + açık kart geçerli), zemin sayfa kökünde, yanlarda boşluk; Puck alanı + aynı kurallı uyarı, AI örneği. Kırma 11/11
  - [x] G7 · 2026-10-30, `docs/decisions/gorunum-g7.md`: `baslikBoyu` (kucuk/normal/buyuk), `yogunluk` (sik/normal/ferah), `hizalama` (sol/orta); Puck alanları, AI örnekleri; gerçek düzen farkı %71–88. Kırma 9/9
  - [x] G2 · 2026-10-30, `docs/decisions/gorunum-g2.md`: kullanıcı kararı seçenek 3 — fontlar huniyle birlikte (Fontsource OFL, latin + latin-ext); serif → Lora, yuvarlak → Nunito, yeni modern (Manrope), geometrik (Outfit); KVKK bekçisi (CSS'te dış adres yok), aile başına ≤ 80 KB. Kırma 8/8
  - [x] G5 · 2026-11-02, `docs/decisions/gorunum-g5.md`: tanıtımda `duzen` ust/kahraman/tamEkran (görsel ister), tam ekranda sabit karartma + beyaz metin — beyaz görselde 13,97:1 (ilk sürüm 2,58 → düzeltildi). Kapsam T1'e kadar yalnız tanıtım (Ç77). Kırma 8/8
  - [x] G4 · 2026-11-02, `docs/decisions/gorunum-g4.md`: `tema.dugmeBicimi` dolu/cerceve/hap (çerçevede ana renk düğmenin altındaki yüzeyde 4,5:1), `soru.duzen` liste/izgara (yalnız seçmeli). Kırma 13/13
- [ ] **YOL HARİTASI — AI asistanı + platform temelleri** (kullanıcı 2026-10-27: "MCP'yi içine göm, prompt iç arayüzde"; araştırma `docs/decisions/ai-asistan-arastirmasi.md`, `web2wave-platform-yapisi.md`). Sıra bağımlılığa göre; her adım `/blok`, ayrı commit:
  - [x] **A0** · araç katmanı `HuniAraclari`: listele, oku, doğrula, taslağa yaz, önizleme; girdi şeması sözleşmedeki `HuniTanimi`'den (elle kopya yok)
  - [x] **A1** · konsol: `app:huni-arac <app> <araç>` (JSON `--girdi`/stdin) — Claude Code depodan promptla huni kurar; `ai-a1-konsol.md`
  - [x] **A2** · panel AI arka ucu (`ai-a2-panel-arka-uc.md`; gerçek Claude ölçülmedi — anahtar yok): `/panel/api/ai/sohbet` (oturum+CSRF, SSE akış), PHP SDK tool runner, `claude-opus-5-5`, doğrulama döngüsü, yalnız taslak, tur/jeton/hız sınırı, istek kaydı + maliyet; sahte API ile testler (`SahteRevenueCat` deseni). **Anahtar yok (kullanıcı 2026-10-27, boş kalacak)** → anahtar boşsa panelde "AI kapalı", istek gitmez; testler sahte API'yle; anahtarı kullanıcı `core/.env.local`'e kendisi yazar
  - [x] **A3** · panel AI arayüzü: Puck eklentisi (sohbet, canlı adımlar, geri al) — `ai-a3-panel-arayuz.md`; "AI ile yeni huni" P2'ye ertelendi
  - [x] **A4** · ürün bağlamı (`ai-a4-urun-baglami.md`): uygulama başına profil (kategori, kitle, ton, yasaklar) panelde, sistem istemine
  - [ ] ⏸ **SIRADAKİ (B6, kullanıcı 2026-10-27): A5 öne alındı** — dışarıya açık MCP sunucusu (URL); önce araştırma (aktarım, kimlik, herkese açık adres), sonra kurulum. Anahtar gerekmez (kullanıcının Claude aboneliği). — A5a ve A5+ tamam; A5b/A5c sunucuya ertelendi (2026-11-03)
  - [x] **T1** · temel 1: ekran = blok listesi + blok kataloğu (Ç68 ile; P3 düzenleyici SPA'ya burada) — AI'ın üretebildiği kendiliğinden büyür — ✅ T1a–T1c tamam
    - [x] T1a · 2026-11-02, `docs/decisions/t1a-blok-katalogu.md`: `ekran` adımı = başlık + 7 bloklu katalog (ara başlık, paragraf, görsel, maddeler, puanlı yorum, istatistik, not kutusu) + düğme/hedef; sözleşme → Tanim → bileşenler → oynatıcı → Puck yuvası → AI istemleri (uydurma sayı yasağı). Gerçek ekranda ölçüldü. Kırma 17/17. Onay: Ç82; kalan: Ç83
    - [x] T1b · 2026-11-02, `docs/decisions/t1b-soru-blogu.md`: ekranda `soru` bloğu (tekli seçenek geçirir, düğmesiz; çoklu/serbest ekranın düğmesiyle), cevap ekranın kimliğiyle (sunucu değişmedi), ortak `Yanit`/`SoruSecenegi`, Puck yuvası + iç içe hedefler. Gerçek ekranda ölçüldü. Kırma 17/17. Onay: Ç85; Ç84
    - [x] T1c · 2026-11-02, `t1c-tasima-olcumu.md`: Puck tarayıcıda ölçüldü (düzenleyicide blok stili kusuru düzeltildi) · kullanıcı kararı Ç86 → ekran kapağı (tanıtımla eşitlik), taşıma göçü (27 taslak + 73 sürüm adımı, 19 tanım geçerli), tanıtım/soru/yorum türleri kaldırıldı — içerik adımı yalnız ekran. Kırma 5+5+5+1. Ç84 + Ç87 kapandı (düzenleyici bekçisi; resolveData yuva değişiminde koşmuyor — tarayıcıda ölçüldü)
  - [x] **T2–T4** · kullanıcı özellik deposu → koşul motoru → olay deposu
    - [x] T2a · 2026-11-02, `docs/decisions/t2-ozellik-deposu.md`: `ziyaret_ozellikleri` görünümü (ikinci yazım yok) + sözlük (`Ozellikler::SOZLUK`) + ziyaret/kişi okuma + `app:ozellikler`; gerçek veride 103 ziyaret / 441 özellik, hepsi sözlükte. Kırma 6/6. Açık: Ç88 cihaz, Ç89 ölçek, Ç90 serbest metin
    - [x] T2b · 2026-11-02, `t2-ozellik-deposu.md`: `huni-bilesenleri/ozellikler.ts` (oynatıcının olaylarından aynı özellikler) + ortak örnek dosyası, sunucu gerçek uçlardan, tarayıcı işlevden ölçülür; bulunan tuzak: en_US sıralaması → `COLLATE "C"` göçü. Kırma 6/6
    - [x] **T3** · koşul motoru (bildirimsel: koşul + VE/VEYA; sonraki ekran / atla / koşullu değer) — girdi T2 özellikleri
      - [x] T3a · 2026-11-03, `docs/decisions/t3-kosul-motoru.md`: ekranda `yonlendirme` kuralları (ilk tutan → seçenek → ekran → sıradaki); çekirdek sözlük/sıra/tür/seçenek denetimi, oynatıcı T2b özelliklerinden, Puck alanı + akış denetimi, AI yönergesi. Tarayıcıda ölçüldü. Kırma 11/11 (2'si testi düzeltti). Açık Ç91
      - [x] T3b · 2026-11-03, `t3-kosul-motoru.md`: ekranda `goster` (yalnız önceki cevaplar, ilk adımda yok); tutmazsa atlanır, geçmişe girmez; Puck alanı + akış denetimi. Tarayıcıda ölçüldü. Kırma 10/10
      - [x] T3c · 2026-11-03, `t3-kosul-motoru.md`: ekran + blok `degerler` (kural başına tek alan, alan öğede var olmalı, değer alanın kuralıyla); oynatıcı çizimde, Puck alanı. Tarayıcıda ölçüldü. Kırma 9/9
    - [x] **T4** · 2026-11-03, `docs/decisions/t4-olay-deposu.md`: `ziyaret_olaylari` görünümü (ikinci yazım yok) + 8 olaylık sözlük; ziyaret çizelgesi + imleçli uygulama akışı + `app:olaylar`; gerçek veride 381 olay, hepsi sözlükte. Kırma 7/7. Açık Ç92
  - [ ] **A5** · MCP sunucusu (B6, HTTP) — araştırma `mcp-sunucu-arastirmasi.md`:
    - [x] A5a · (`mcp-a5a.md`) kişisel API anahtarları (panel, özet saklanır, uygulama kapsamlı) + `/mcp` Streamable HTTP (`mcp/sdk` 0.8.1), Bearer; ölçüm Claude Code + localhost
    - [x] A5+ · (`ai-ekran-goruntusu.md`, Ç72 (1)) `ekran_goruntusu` aracı: başsız Chrome kabı (`ekran`), önizlemede `?adim=`, MCP görüntü içeriği; gerçek MCP HTTP ile ölçüldü. Kalan: Ç72 (2)–(4), Ç73, Ç74
    - [ ] ⏸ A5b · herkese açık adres + claude.ai özel bağlayıcı — **sunucuya ertelendi** (kullanıcı 2026-11-03: Oracle Cloud sunucusunu sonra kuracak; geçici tünel yerine kalıcı adres dağıtımla gelir. Not: geçici Cloudflare tüneli ve DuckDNS ücretsiz — maliyet engel değil)
    - [ ] ⏸ A5c · claude.ai OAuth (web2wave gibi: yetki sayfası anahtar ister) — "Request headers" hesapta yoksa; A5b ile birlikte sunucuya ertelendi (2026-11-03)
  - [x] **A6** · 2026-11-03, `docs/decisions/ai-a6-degerlendirme.md`: `app:ai-degerlendir` — 6 brieflik set, geçici huni + panelin döngüsü, geçerli oran/tur/maliyet/süre JSON, --model/--efor. Sahte Claude ile ölçüldü; gerçek koşu Ç95 (anahtar). Kırma 4/4
  - Sonra: P2/P4/P5 ✅ yapıldı; kalan modüller aşağıda "YOL HARİTASI — para ve büyüme modülleri"
- [x] **Panel React (B5)** — kullanıcı 2026-10-27; `docs/pre-setup.md` B5, `docs/decisions/panel-mimari-arastirmasi.md` — ✅ P0–P5 tamam (2026-11-03)
  - [x] P0 · `core/editor` → `core/panel` (yalnız taşıma; davranış aynı)
  - [x] P1 · iskelet: Router + Tailwind + Radix + Query; `/panel/*` SPA kabuğu; `/panel/api/rapor` (OpenAPI) + Rapor sayfası; Twig rapor silinir
  - [x] P2 · 2026-11-03, `docs/decisions/p2-huniler-sayfasi.md`: Huniler React sayfası + 6 JSON uç (sözleşme), Twig liste ve form uçları silindi; kabuk jetonu (düzenleyicininki geçmez). Kırma 9/9. Tarayıcı + "AI ile yeni huni": Ç93
  - [x] P3 · 2026-11-03, `docs/decisions/p3-duzenleyici-sayfasi.md`: düzenleyici panel uygulamasının tembel yüklenen sayfası, açılış JSON ucundan; Twig ve ayrı giriş silindi; stil yalıtımı (tarayıcıda önizleme/kabuk farkı 0; yeni tuzak §43). Kırma 6/6. Açık Ç94
  - [x] P4 · 2026-11-03, `docs/decisions/p4-grafik.md`: raporda iki grafik (günlük ziyaret; ödemeye geçti/ödedi), UTC günü, olay deposundan; doğrulanmış renkler, tablo görünümü, tembel parça. Tarayıcıda gerçek veriyle (düzenek). Kırma 5/5
  - [x] P5 · 2026-11-03, `docs/decisions/p5-kullanicilar.md`: Kullanıcılar sayfası — abonelik başına satır, kaynak/kampanya, kişi/bağlantı, maskeli e-posta, arama + durum süzgeci, istemcide sıralama (tablo kitaplığı gerekmedi). Kırma 6/6
- [x] **Panel görünümü** (kullanıcı 2026-10-27: "basit siyah ekran, web2wave'e bak") — sol menü, özet, huni şeridi, açık/koyu tema. `docs/decisions/panel-gorunum.md`

- [x] **Astro'yu kaldır → Vite (MPA) + Preact** (kullanıcı 2026-11-03; yapıldı aynı gün, `docs/decisions/astro-kaldirma.md`: JS −%18, node_modules 340 → 135 MB, adresler aynı, tarayıcıda üretim derlemesi; kırma 6/6). Araştırma ve öneri: `docs/decisions/astro-kaldirma-arastirmasi.md` (Seçenek 1: 3 HTML girişi, adresler/`dist` düzeni/dağıtım aynı; ≈ 4,5 KB JS ve Astro bağımlılıkları gider). Her adım `/blok`:
  - [x] S1 · prototip + ölçüm (yollar, JS gzip, modulepreload/ilk JS turu, eğik çizgisiz adres)
  - [x] S2 · sayfaları taşı (kabuk + erken istek eklentisi, basarili/iptal betikleri)
  - [x] S3 · araçlar (`tsc`, prettier, `bin/kontrol` adımları, `onyukleme.ts` silinir)
  - [x] S4 · geliştirme ortamı (`huni-dev`, `ekran`, belgeler)
  - [x] S5 · tarayıcıda eşlik (H3/L20 stil farkı 0, uçtan uca)
  - [x] S6 · paketleri kaldır, yeniden ölç

- [ ] **YOL HARİTASI — para ve büyüme modülleri** (kullanıcı 2026-11-03: "quiz builder bitti, web2wave'in diğer özelliklerini sırayla planla"; gerekçe, kapsam dışı liste ve dış girdiler `docs/decisions/web2wave-platform-yapisi.md` § Ek 2026-11-03). **Sıra onaylandı — `pre-setup.md` A14** (2026-11-03). Her biri `/blok`:
  - [x] E1 · e-posta adımı (kullanıcı 2026-11-03: "önce e-posta, sonra M1b"; Ç97 (1)) — 2026-11-03, `docs/decisions/e1-eposta-adimi.md`: `secimTuru: eposta` + izin kutusu, ayrı uç/tablo, ödeme sayfası adresle, izinsiz adres 90 gün. Kırma 19/20. Açık Ç98, Ç99
  - [ ] **V serisi — görsellik ve AI tasarımı** (kullanıcı 2026-11-03: "önce V serisi, quiz builder'ı iyileştir"; M1b'den önce). `rakip-gorsellik-arastirmasi.md`:
    - [x] V1 · 2026-11-03, `docs/decisions/v1-tasarim-dongusu.md`: tasarım yönergesi + ekran ölçümü/uyarıları + zorunlu görsel döngü; `#huni` gerilemesi (§45), yükleme ortalı. Kırma 8/8. Açık Ç100
    - [x] V2 · 2026-11-03, `docs/decisions/v2-hazir-temalar.md`: 12 hazır tema tek kaynaktan (AI istemi + panel seçici), gerçek ekranda ve Puck'ta ölçüldü. Kırma 8/8. Açık Ç101
    - [ ] V2b · tohum renkten palet (material-color-utilities, Apache-2.0 ölçüldü)
    - [x] V3a · 2026-11-03, `docs/decisions/v3a-fluent-simgeler.md`: Fluent simgeler (renkli 3B / tek renk ana renkte), dizin depoda, görsel ilk istekte sabit commit'ten → WebP. Kırma 11/11. Açık Ç102
    - [x] V3b · 2026-11-03, `docs/decisions/v3b-stok-fotograf.md`: Pexels (lisans + API kuralları ölçüldü; Unsplash/Openverse elendi), içe alma yeniden kodlar, kaynak/fotoğrafçı/lisans saklanır, panel arama + AI araçları. Kırma 12/12. **Gerçek Pexels anahtar bekliyor (Ç103)**
    - [x] V4 · 2026-11-03, `docs/decisions/v4-hareket.md`: tema `hareket` (yok/sakin/canlı) — sırayla giriş, seçim efekti, sayan istatistik; Ç101 kapandı. Gerçek tarayıcıda `getAnimations` ile ölçüldü. Kırma 12/12
    - [x] V5 · 2026-11-03, `docs/decisions/v5-yeni-bloklar.md`: grafik (sayısız projeksiyon), ölçek 1–5 (tekli hattı), önce/sonra; Lottie bütçe yüzünden elendi, sayısal kaydırıcı ertelendi (Ç106). Kırma 13/13
    - [x] V6 · 2026-11-04, `docs/decisions/v6-nis-sablonlari.md`: uyku/fitness/dil/finans şablonları (`config/huni/sablonlar`), panel Başlangıç seçicisi + API + AI `yeni_huni` `sablon`; tema hazır temadan, plan uygulamadan, görsel/yorum yok. Kırma 15/15. Açık Ç107
  - [ ] M1 · fiyat kataloğu — üç blok:
    - [x] M1a · 2026-11-03, `docs/decisions/m1a-fiyat-katalogu.md`: `planlar` tablosu, panel → Planlar (Stripe'ta ürün + fiyat; fiyat değişimi yeni fiyat), ödeme/huni/Puck/AI katalogdan, ortam değişkeni → `app:plan-ice-aktar`. Gerçek Stripe test API. Kırma 15/15 (K11 testi buldu). Açık Ç96
    - [x] M1b · 2026-11-04, `docs/decisions/m1b-teklifler.md`: plan başına bir teklif (katalogda); deneme kartlı, ücretli giriş = deneme + tek seferlik ücret (kullanıcı kararı, schedule reddedildi), indirim tek dönemlik yüzde kuponu; paywall metni teklifle. Kırma 20/20. Açık Ç108
    - [x] M1c · 2026-11-04, `docs/decisions/m1c-cok-para-birimi.md`: ülke IP'den (DB-IP Lite, kullanıcı kararı + rakip araştırması), `?ulke=` testi, ülke → EUR/GBP/TRY/USD yoksa ana birim; paywall ve ödeme aynı seçim (`currency` zorlanır), Adaptive Pricing kapalı; ek birim = yeni Stripe fiyatı; giriş ücreti tek birimli. Kırma 20/20. Açık Ç109
  - [ ] M2 · paywall (bölünme ve kararlar `pre-setup.md` A15, kullanıcı 2026-11-04):
    - [x] M2a · 2026-11-04, `docs/decisions/m2a-plan-secici.md`: `planlar` (2–3) + `onerilen`, kart başlığı plan adı, seçili plana göre ödeme koşulu (iptal sözü yok); plan hatası planları sayar. Kırma 15/15. Açık Ç110
    - [x] M2b · 2026-11-04, `docs/decisions/m2b-sayac-guvence-onay.md`: teklif bitişi (tek bekçi `teklifler()`, istemci saati), sayaç yalnız gerçek bitişe; rozetler yalnız doğru olanlar; şart onayı düğmeyi kilitler, kanıt Stripe metadata'sında. Kırma 17/17. Açık Ç111
    - [x] M2c · 2026-11-04, `docs/decisions/m2c-cok-paywall.md`: "tam bir kez" kalktı, son adım paywall; öndeki paywall'a seçenek hedefi/yönlendirme; her paywall'da çubuk dolu. Kırma 4/4. Açık Ç112
    - [x] M2d · 2026-11-04, `docs/decisions/m2d-gomulu-odeme.md`: gömülü ödeme (Stripe.js yüklenemezse barındırılan sayfa), panel kapat/Escape; para birimi hep zorlanır (§50). Kırma 10/10. **Apple/Google Pay sunucuya ertelendi** (kullanıcı). Açık Ç113
  - [x] M3 · downsell + upsell (kararlar `pre-setup.md` A16, kullanıcı 2026-11-04):
    - [x] M3a · 2026-11-04, `docs/decisions/m3a-downsell.md`: geri tuşu / panel kapatma → bir kez downsell penceresi (katalog planı, fiyat + koşul Stripe'tan, şart onayı korunur). Kırma 10/10 (K6 testi buldu). Açık Ç114
    - [x] M3b · 2026-11-04, `docs/decisions/m3b-odeme-sonrasi.md`: tanım kökünde `sonrasi` (başlık + bloklar + düğme metni), giriş alanı sabit; dönüş adresi huniyi taşır; düzenleyicide "Ödeme sonrası ekran". Kırma 9/9 (K6 testi buldu). Açık Ç115
    - [x] M3c · 2026-11-04, `docs/decisions/m3c-ek-urun-upsell.md`: ek ürün kataloğu (tek seferlik fiyat), ödeme sonrası tek tık upsell (on-session, kayıtlı kart, oturum + ürün başına bir, ≤ 1 sa, 3D Secure sayfada). Kırma 16/16 (K10 testi buldu). Açık Ç116
  - [x] M4 · deneyler (kararlar `pre-setup.md` A17, kullanıcı 2026-11-04): M4a tamamı sonuç okumadan işe yaramaz; sonuçlar ayrı, ölçülebilir blokta kalır.
    - [x] M4a · 2026-11-09, `docs/decisions/m4a-deneyler.md`: yönlendirme + içerik (`deney.<ad>`), sekme tohumundan atama (A17), panelden taslak/başlat/durdur/bitir; ilk commit `296a78c` CI kırmızıydı → düzeltildi. Kırma 24/24. Açık Ç117
    - [x] M4b · 2026-11-09, `docs/decisions/m4b-deney-sonuclari.md`: varyant başına ziyaret / ödemeye geçti / abone oldu (dönüşüm, deneme dahil — kullanıcı) / ödedi (tutar > 0) + kazanma olasılığı (Beta, belirlenimci); deneme Stripe'ta "paid", tutar 0 (§52). Kırma 15/16 (B10 kod kaldırıldı). Açık Ç118
    - [x] Ç118 (1)(2) · 2026-11-09, `docs/decisions/c118-deneme-rapor-reklam.md`: Rapor "ödedi" tutar > 0'dan, kartlı deneme ayrı "deneme başladı", dönüşüm = abone (deneylerle aynı); reklam ağlarına (sunucu + piksel) denemede StartTrial (kullanıcı). Kırma 12/12
  - [x] M5 · analitik (sıra kullanıcı 2026-11-09, `pre-setup.md` A18): önce Ç92 ekleme-tabanlı olay deposu; ardından cevap dağılımı · UTM raporu · kohort geliri; en son Meta harcama → ROAS ve değer aktarımı. Gerekçe: geçmişi olmayan son-hâl verisinden güvenilir dönüşüm raporu çıkmaz.
    - [x] M5a · 2026-11-09, `docs/decisions/m5a-olay-deposu.md`: yalnız ekleme `olaylar` tablosu (güncelleme tetikleyiciyle reddedilir), yazma yolları aynı işlemde ve yalnız durum değişince; cevap / izin / abonelik geçmişi korunur; akış bitmemiş işlemi bekler (§53); eski 488 olay birebir taşındı. Kırma 18/18. Ç92 kapandı
    - [x] M5b · 2026-11-09, `docs/decisions/m5b-faturalar.md`: `invoice.paid` (tutar > 0) → `odeme_faturalari` + aboneliğe bağlı her ziyarete `odeme.fatura`; fatura oturumdan önce gelirse bağlanınca; abonelik `parent.subscription_details`'ten (§54). Kırma 7/8 (F8 kod kaldırıldı). Ç118 (3) kapandı, açık Ç120
    - [x] M5c · 2026-11-09, `docs/decisions/m5c-raporlar.md`: kaynak kırılımı (gelirle) · cevaba göre dönüşüm · kohort (UTC hafta × ilk 24 saat / 7 / 30 / 60 gün, ziyaret başına; olgunluk haftanın en genç ziyaretinden); "ödedi" = ilk ödeme ya da fatura (Rapor + deneyler tek tanım). Kırma 15/15 (4 deneme testi güçlendirdi, biri gerçek hata buldu — §55)
    - [x] M5d · 2026-11-09, `docs/decisions/m5d-meta-harcama.md`: Meta Insights kampanya × gün harcaması (günlük `app:reklam-harcamalari:cek`, son 7 gün yeniden yazılır), panelde "Meta kampanyaları" (harcama · ziyaret · ödedi · gelir · ROAS, utm_campaign = kampanya adı). Kırma 13/13. **Gerçek Meta hesabıyla ölçülmedi** (Ç121). Değer aktarımı sonraya (kullanıcı)
  - [x] M6 · abonelik yönetimi: panelden iptal/iade + müşteri portalı (ücretli trafikten önce) — kararlar A19
    - [x] M6a · 2026-11-09, `docs/decisions/m6a-iptal-iade.md`: Kullanıcılar satırında dönem sonunda iptal / geri al ve son faturanın kalanını tam iade (iki adımlı onay); webhook dönem sonunu kalemden, iadeyi `charge.refunded` birikimli tutarından (fatura bağı `invoice_payment.paid`); iade gelirden (kırılım, kohort, ROAS) düşülür. Kırma 19/19; gerçek Stripe test modunda iptal / geri al / iade
    - [x] M6b1 · 2026-11-09, `docs/decisions/m6b1-portal.md`: portal ayarı kodda (`app:stripe-portal:kur`, etiketle bulunur, kurulur / güncellenir) + `POST /apps/{app}/kullanicilar/{kullanici}/portal` (uygulama anahtarı; erişim veren abonelik önce). Kırma 9/9; gerçek test hesabında ayar kuruldu / güncellendi, oturum açıldı
    - [x] M6b2 · 2026-11-09, `docs/decisions/m6b2-portal-web.md`: hunide `/keepchat/abonelik/` → `POST /huni/portal-baglantisi` (hep 202, e-posta başına saatte 3) → e-postada tek kullanımlık 1 saatlik bağlantı → onay sayfası (GET jetonu yakmaz) → POST → Stripe portalı. Kırma 11/11 (W8 testi güçlendirdi); gerçek akış dev + Mailpit + Stripe test
  - [x] M7 · giden webhook + yönetim API / MCP genişlemesi — kararlar A20, A21
    - [x] M7a · 2026-11-09, `docs/decisions/m7a-giden-webhook.md`: `webhook_hedefleri` + `webhook_teslimleri`, `app:webhook:gonder` (dakikalık cron), hedef başına `(islem, id)` imleci, Standard Webhooks imzası, üstel bekleme (10 deneme), iç ağ adresine gitmez; **§53'ün ilk çözümü yetersizdi** — akış da `(islem, id)`'ye geçti, Ç119 kapandı. Kırma 16/16 (üçü testi güçlendirdi)
    - [x] M7b · 2026-11-09, `docs/decisions/m7b-webhook-paneli.md`: panelde "Webhook'lar" — hedef ekle (https, sözlükten olaylar, sır bir kez, uygulama başına 10), etkin / pasif, iki adımlı sil, teslim günlüğü (son 50) ve yeniden gönder. Kırma 12/12; test gerçek bir çökmeyi buldu (onay kutusu)
    - [x] M7c1 · 2026-11-09, `docs/decisions/m7c1-okuma-araclari.md`: MCP'de 6 salt-okur rapor aracı (özet, kaynak, kohort, Meta ROAS, cevap, deney sonuçları), Rapor'a uygulama süzgeci; `GET /apps/{app}/olaylar` (uygulama anahtarı, imleç + devam). Kırma 12/12 (biri testi güçlendirdi); gerçek MCP çağrısı dev'de
    - [x] M7c2 · 2026-11-10, `docs/decisions/m7c2-yazma-araclari.md`: MCP'de abonelik (listele, iptal planla / geri al, son faturayı iade) ve deney (kaydet, durum) araçları — yalnız "yönetim" izinli anahtarda, yıkıcılar işaretli; abonelik uygulamanınsa. Kırma 12/12 (biri testi güçlendirdi)
  - [ ] M8 · e-posta (A22: Postmark, karma izin) — [x] M8a1 sağlayıcı + bastırma (`m8a1-postmark.md`) · [x] M8a2 aktivasyon dizisi (`m8a2-aktivasyon.md`) · [x] M8b terk edilen ödeme (`m8b-kurtarma.md`)
  - [ ] M9 · proje ayarları (A23; roller şimdilik yok) — [x] M9a uygulama kimliği (`m9a-uygulama-kimligi.md`) · [ ] M9b yasal veriler + metinler · [ ] M9c varsayılan tema/paywall · [ ] M9d dil/ülke yönlendirmesi
  - [ ] M10 destek kutusu · M11 çeviri (isteğe bağlı)

## 2. Açılmamış Görevler & Gelecek Yol Haritası (Backlog)

> **Not:** Zamanı geldikçe önceliklendirilip aktif sprint bölümüne taşınacak açılmamış görev havuzudur.

### A. Ürün Özellikleri (Ciro, Dönüşüm & Büyüme)
- [ ] **M8 ilk dizi: terk edilen ödeme kurtarma.** İzinli e-posta için 15 dk / 6 sa / 24 sa serisi; durdurma koşulu ödeme, indirim ise açık teklif kuralıyla. SMS ayrı sağlayıcı, izin ve maliyet kararıdır. Önkoşul: M7 outbox + M8 sağlayıcı/double opt-in kararı.
- [ ] **Ç9: uygulamaya geçiş.** Deferred deep link (Branch/AppsFlyer) e-posta koduna ek yol olabilir; her zaman e-posta/sihirli bağlantı yedeği kalır. Parmak iziyle eşleştirme yapılmaz. Önkoşul: ilk iOS uygulaması ve sağlayıcı kararı.
- [ ] **M6 alt akışı: iptal önleme ve dunning.** İptal mikro anketi, dönem sonu iptal/duraklatma/indirim teklifi ve başarısız ödeme bildirimleri; kolay iptal yolu korunur. Önkoşul: M6 abonelik ve hukuk metinleri.
- [ ] **AI pazarlama operasyonları (sonra).** Kullanıcının yetkili App Store URL'sinden taslak bağlamı ve sahip olunan huni varlıklarından reklam kreatifi; scraping kapsamı, varlık hakları ve reklam politikası ayrıca kararlaştırılır.
- [ ] **M5 ileri analitik (sonra).** Temel kohort raporundan sonra yeterli veriyle LTV 30/60/90 tahmini ve Meta CAPI dinamik değer. Önkoşul: Ç92 olay deposu, tanımlı başarı metriği ve veri hacmi.

### B. Canlı Sonrası Ölçeklenme (Deploy ve gerçek trafik sonrasında)
- [ ] **Multi-Armed Bandit.** Sabit deney sonuçları güvenilir ve yeterli örneklemli olduktan sonra, koruma bantlarıyla kazanana trafik kaydırma. Gerekçe: M4b + M5 ve gerçek trafik olmadan otomasyon yanlış kazanana kilitlenebilir.
- [ ] **Entegrasyon bağlayıcıları.** Çekirdek M7 outbox'ı tamamlandıktan sonra Zapier/Klaviyo/CRM bağlayıcıları eklenir. Gerekçe: önce kendi teslim günlüğü ve yeniden deneme davranışı canlıda kanıtlanmalıdır.
- [ ] **Chargeback otomasyonu.** Stripe Radar ve gerekli 3D Secure canlıya çıkış kapısında kurulur; Chargeflow gibi üçüncü taraf otomasyonu gerçek hacim kanıtlanmadan eklenmez. Gerekçe: sahte pozitif ve sağlayıcı maliyeti ancak gerçek işlem verisiyle değerlendirilir.
- [ ] **Özel alan adı.** Proje/tenant sınırı ve alan adı sahipliği netleştikten sonra Cloudflare for SaaS ile doğrulama, sertifika durumu, geri alma ve host-başına huni eşlemesi eklenir. Gerekçe: tek marka/alan adı varken operasyonel bedeli faydasından yüksektir.

### C. Canlıya Geçiş (Dağıtım Hazırlıkları — özellik değil, sürüm kapısı)
- [ ] **Alan adı, TLS ve gerçek HTTP kanıtı:** DNS, HTTPS, CORS ön isteği ve webhook imza doğrulaması canlı alan adında ölçülür.
- [ ] **Stripe canlıya geçiş:** canlı anahtarlar, canlı webhook, şirket/banka doğrulaması; gerçek düşük tutarlı uçtan uca ödeme ve iade denemesi.
- [ ] **Gerçek posta sağlayıcısı:** Resend/Postmark/SES seçimi, SPF/DKIM/DMARC, unsubscribe ve teslim/geri dönüş ölçümü. M8 gönderim açılmadan tamamlanır.
- [ ] **Statik huni sunumu:** Vite `dist` üretim kökeninden/CDN'den sunulur; içerik özetli varlık önbelleği, ödeme dönüş adresleri ve CORS canlıda ölçülür.
- [ ] **Sunucu yüzeyini kapat:** PostgreSQL, Mailpit ve dahili servisler internete kapalı; yalnız reverse proxy gerekli portları dinler; firewall ve erişim geri dönüş testi yapılır.
- [ ] **Uzak ve geri dönülebilir yedek:** var olan yerel günlük dump'a ek olarak şifreli S3/R2 kopyası, saklama politikası ve düzenli geri yükleme tatbikatı.

### D. Huni Builder İyileştirmeleri & Eksikler (Builder Gaps)
- [ ] **Görsel Akış Şeması (Node Graph / Canvas View):** `core/panel` içine `@xyflow/react` entegrasyonu; `akis.ts` verisinden ekranlar arası geçişleri, koşullu dallanmaları (`yonlendirme`) ve ulaşılamayan adımları görselleştiren kuşbakışı harita sekmesi. Düğüme tıklayınca Puck editörüne odaklanma.
- [ ] **Ağırlıklı Puanlama & Skorlama Motoru (Quiz Scoring):** `SoruSecenegi` şemasına `puanlar` (ör. `{"stres": 3}`) eklenmesi, oynatıcıda skor toplama ve `T3` koşul motorunda `skor:kategori >= X` kurallarıyla dinamik sonuç/teşhis ekranlarına yönlendirme.
- [ ] **İnteraktif Niş Bileşenler:** 
  1. *Vücut Haritası:* SVG tabanlı tıklanabilir silüet (bölge seçimi, çoklu seçim yanıtı üretir).
  2. *Sayısal Kaydırıcı & Hesaplayıcı:* Boy/kilo slider'ları ve anlık BMI/metabolik yaş hesaplama ara ekranı.
  3. *Sosyal Kanıt Bildirimi:* Huni altında periyodik çıkan güven toast'ları ("X kullanıcısı 4 dk önce katıldı").
- [ ] **App Store Kazıyıcı & AI Reklam Kreatifleri:** iTunes API ile App Store linkinden uygulama açıklaması, puanlar ve ekran görüntülerini çekip `HuniAsistani`'na brief olarak verme; huni acı noktalarından TikTok/Reels video senaryosu ve kanca (hook) metinleri üretme CLI komutu.
