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
- [ ] **Huni oynatıcısı React'e geçiş (B4)** — sıra önemli; her H ayrı blok (ölç → kır → tarayıcı). Bugünkü kod ölçüldü: huni `funnel/src/components/HuniKabugu.astro` (370 satır düz TS, `textContent`, testi YOK — davranış yalnız tarayıcıda denendi); Puck `core/editor/src/yapilandirma.tsx` 3 bileşeni (`Tanitim`, `Soru`, `Paywall`) satır içi stille YENİDEN çiziyor; tema formülü (`parlaklik`, yazı tipi yığınları) üç yerde (huni, düzenleyici, `Tema.php`)
  - [x] **H0** — 2026-10-21, `docs/decisions/huni-oynatici-h0.md`: anket JS 4,8 KB gzip, en küçük React 67,4 KB gzip; içerik 3 sıralı ağ adımından sonra (tanım isteği JS'i bekliyor); 15 davranış (D1–D15); bütçe önerisi ≤ 90 KB gzip; yavaş mobil ölçülmedi (Ç50). Eski metin: kod yok. (a) bugünkü JS (~12 KB, 3 dosya) ve yavaş mobil ağda ilk anlamlı boyama (B4'ün ölçülmemiş varsayımı; araç: Chrome, ağ kısıtlı); (b) korunacak davranışların listesi = H2'nin test listesi: önizlemede ölçüm yok + ödeme kapalı · geri tuşu önceki adım · adımda başlığa odak · ilerleme çubuğu · ziyaret/adım/cevap bildirimi (A6) · seçim parametreleri yalnız k/utm/huni (A8) · görsel yalnız 64-hex kimlikten · tema + düğme yazısı kontrastı · ödeme hata metinleri (502 / ağ) · `?onizleme=` süresi dolmuş metni · metin HTML olarak ASLA yorumlanmaz; (c) React bütçesi: ölçülen sayıya göre üst sınır (öneri ≤ 120 KB gzip), `bin/kontrol`'de ölçülür
  - [x] **H1** — 2026-10-21, `docs/decisions/huni-bilesenleri-h1.md`: `huni-bilesenleri/` (`Tanitim`/`Soru`/`Paywall` + `tema.ts` + `huni.css`), bağımlılıksız — düzenleyicinin `node_modules`'u bağlanır, `dedupe` + `tek-react` bekçisi; Puck ve huni sayfası aynı CSS/tema; `Tema.php` ile ortak örnek dosyası; `bin/kontrol` +4 adım (`html-yok` dahil). Kalanlar Ç51
  - [x] **H2** — 2026-10-21, `docs/decisions/huni-oynatici-h2.md`: `funnel/src/oynatici/Huni.tsx` tek React adacığı (`client:only`), H1 bileşenleriyle; tanım isteği satır içi betikte (JS'i beklemiyor); D1–D15 = 22 test, kırma 27 deneme · 36 koşu (2 sahte/koşulmadı → düzeltildi, 2 tutmadı → gereksiz satır kaldırıldı); JS 73,2 KB gzip (`funnel-butce` ≤ 90 KB). Kalanlar Ç52 (ikinci JS turu, ölçülemeyen duvar saati)
  - [x] **H3** — 2026-10-21, `docs/decisions/huni-onizleme-h3.md`: aynı tanım iki bağımsız yoldan (Puck alanları / tanım → `Adim`) aynı DOM (düzenleyici testi, kırma 6/6); tarayıcıda 9 öğe × 12 hesaplanmış özellik fark 0; geçerli önizleme bağlantısı yürüdü. Klasik düzenleyici kaldı (Ç45, kullanıcı kararı)
  - [x] **b1 · yayınla/önizleme/sürümler Puck'ta** (Ç45) — 2026-10-21, `docs/decisions/puck-yayin-b1.md`: üst çubuk Kaydet · Önizle · Yayınla · Sürümler; 3 JSON ucu; kaydedilmemişken yayın/önizleme/geri yükleme kapalı; kırma 9/10 (+S9 tutmadı → koruma access_control'da, S9b). Sırada **b2** (klasik düzenleyiciyi silmek; önce Safari) — Ç53
  - [x] **Ada önyükleme** (Ç52 (1)) — 2026-10-21, `docs/decisions/ada-onyukleme.md`: derlemede `modulepreload`; ikinci JS turu kalktı, içerik yalnız tanımı bekliyor (ısınmış: ilk bildirim 142 ms, H0 230)
  - [x] **H4** — 2026-10-21, `docs/decisions/huni-gecisler-h4.md`: yalnız CSS (`@keyframes`, 220 ms), ilk adımda yok (`data-gecis`), `prefers-reduced-motion`'a uyar; JS +23 B; kırma 5/5. Kalanlar Ç54
  - [x] **H5** — 2026-10-21, `docs/decisions/huni-dallanma-h5.md` (A11): seçenek ve tanıtım düğmesi → hedef adım, yalnız ileri; skor yok; sözleşme + çekirdek + oynatıcı + Puck açılır listesi; kırma 9/9. XState/Zustand GEREKMEDİ (iki `useState` yetti). Kalanlar Ç55
  - [x] **H6a · onay şeridi** (A12) — 2026-10-21, `docs/decisions/huni-izin-h6a.md`: herkese Kabul/Reddet; tıklama kimlikleri yalnız izinle (çekirdek izinsizini atar); yeni uç `…/izin`; tarayıcıda iki kusur bulundu (göçsüz geliştirme DB'si, CORS §36). Kalanlar Ç56
  - [x] **H6b · sunucu dönüşümü** — 2026-10-23, `docs/decisions/huni-donusum-h6b.md`: Stripe webhook'unda yalnız izinli ve ödenmiş ziyaret için Meta CAPI + TikTok Events API; kuyruk/yeniden deneme; IP/UA geçici tutulur ve kuyruk yükü en geç 7 günde silinir. Gerçek reklam hesabı doğrulaması için Meta Pixel ID + erişim anahtarı + test olay kodu ve TikTok Pixel ID + erişim anahtarı gerekir; henüz yapılmadı.
  - [ ] **H6c · tarayıcı pikselleri** — izinden sonra Meta Pixel + TikTok Pixel; Purchase başarı sayfasında aynı `event_id`; piksel yüklenmeden önce adresten `session_id` silinir (A10)
  - [ ] **H6 · Olay/piksel noktası** (H6a–c'ye bölündü; aşağıdaki eski metin) — tek `olayBildir` (bugünkü `ilerlemeBildir`'in yerine): çekirdek ölçümü + Meta Pixel / TikTok Pixel / GA4. Ön koşul: çerez onayı (Ç12, Ç34) ve sunucu bildirimiyle tekilleştirme (Ç28) — aşağıdaki "Reklam ağına dönüşüm" maddesiyle aynı blok. Başarı sayfasında piksel `session_id`'yi görmemeli (A10, Ç46)
- [x] **E2.4b ilk yeni soru türü: çoklu seçim** — 2026-10-22, `docs/decisions/huni-coklu-secim.md`
- [x] **E2.4b müşteri yorumu/sosyal kanıt adımı** — 2026-10-22, `docs/decisions/huni-yorum.md`
- [x] **E2.4c soru açıklaması** — 2026-10-22, `docs/decisions/huni-soru-aciklamasi.md`
- [x] **E2.4b serbest metin soru türü** — 2026-10-23, `docs/decisions/huni-serbest-metin.md`
- [ ] Reklam ölçümünün canlıya hazır olması — H6b sunucu aktarımı tamam; H6c tarayıcı pikselleri + Ç56 hukuk/izin açıkları tamamlanmadan ücretli trafikten önce kapanmaz.
- [ ] KeepChat "aboneliğimi bağla" (KeepChat deposunda) · Apple · Google · hesap silme
- [ ] Ç19/Ç21 (fiyat tek kaynak) — OpenAPI + huni tarafında üretilen tipler; ilk gerçek uç (ödeme oturumu) ile
- [ ] **Ödeme sağlayıcısı araştırması** — Ç10
- [ ] **Ç9 kararı** — uygulamayla eşleme (e-posta / parmak izi), hangi iOS uygulaması
- [x] **`/kontrol`** — `bin/kontrol`, kırma K4–K7

## 2. Backlog

- _—_
