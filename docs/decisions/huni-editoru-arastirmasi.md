# Araştırma: huniyi panelden dinamik yönetmek — ne kadar, nasıl (2026-10-20)

> Tetikleyen: kullanıcı "rakipler gibi kapsamlı, dinamik yönetelim"
> (2026-10-20). Bu **A1'in "görsel editör yok" kısmını** değiştirir; karar
> kullanıcının (Ç37). Tek kiracı (A1'in asıl özü) değişmez — editör yalnız
> bizim uygulamalarımız için. Kaynaklar yalnız herkese açık belge (C4);
> lisanslar depodan ölçüldü (kural 12). Kod yazılmadı.

## 1 · Rakipler nasıl yapıyor

**FunnelFox** (en ayrıntılı belgelenen):
- **Yayınlama = statik derleme.** Her yayın huniyi değişmez statik
  dosyalara derler (v1, v2…); eski sürüm yenisi hazır olana kadar sunulur,
  **hunide ilerleyen ziyaretçi kendi sürümünde kalır**. Önizleme her zaman
  son kaydı gösterir, canlı yalnız yayınlanmışı. Yerleşik geri alma yok:
  eski kaydı geri yükleyip yeniden yayınla. Yayından önce "hata kontrolü".
- **Öğeler:** düğme/CTA/ödeme düğmesi, metin, görsel, ödeme formu,
  sayaç, açılır pencere, plan seçici, yorumlar, metin girişi, açılır
  liste, cüzdan düğmeleri, RAW (özel HTML/JS).
- **Stil (öğe başına):** konum, iç boşluk, arka plan (renk/gradyan/görsel),
  köşe yuvarlaklığı, kenarlık, gölge; proje geneli **tema**.
- **Koşullu görünürlük:** cevap değeri, ülke, işletim sistemi, tarayıcı,
  trafik türü (ücretli/organik), URL parametresi, ödeme yöntemi; VE/VEYA.
  Cevaplar ID'li öğelerde **değişken** olur (kişiselleştirme).
- **Deneyler:** bir URL, kontrol + varyant huniler, ağırlıkla bölme;
  yalnız yayınlanmış huniler.
- **Yerelleştirme:** huni başına 25 dil; URL parametresi → IP ülkesi →
  tarayıcı dili; AI çeviri; dil de statik derlemenin parçası.
- Huni geneli özel kod (`<style>`/`<script>`); görsel editör stilleri
  yüksek özgüllükte → `!important` gerekir. Editör Safari'de çalışmıyor.

**web2wave:** görsel quiz + paywall editörü, Wavy AI (beta; kendi uyarısı:
canlı huniyi sessizce değiştirebilir, önce kopyala). A/B: quiz akışı,
paywall düzeni, metin, fiyat, ödeme yöntemi; cihaz/user-agent/IP ile
bölme, p-değeri ve örneklem. 40+ dil tek tıkla; otomatik dil/ülke
yönlendirmesi üst planda. MCP sunucusu (huni analizi, şablon göçü).

**RevenueCat:** web paywall'u yerel paywall editörüyle aynı bileşenler;
şablon ya da boş sayfa; ülke/platform/sürüm/segment hedefleme.
**Adapty:** web paywall'u FunnelFox üzerinden (kendi web editörü yok).

## 2 · Editör altyapıları (lisans depodan ölçüldü, 2026-10-20)

| Proje | Lisans | Durum | Not |
|---|---|---|---|
| **Puck** (`puckeditor/puck`, `@puckeditor/core` 0.23.0) | **MIT** | 13,5k ★, son push 2026-10-16 | React görsel editör; çıktı **düz JSON**; bileşenleri biz tanımlarız (alanlar + render), veri bizde |
| GrapesJS | BSD (lisans metni: "Redistribution and use…") | 26k ★, aktif | serbest HTML/CSS düzenleyen sayfa kurucu |
| Craft.js | MIT | 8,7k ★, son push **2025-02** | React sürükle-bırak çatısı; ~20 aydır push yok |
| Typebot | **FSL 1.1** (2 yıl sonra Apache) | — | kaynağı açık ama OSI değil; barındırıp satma kısıtlı → **elenir** |
| Formbricks | AGPL + kurumsal kısım | — | **elenir** (kural 12) |
| HeyForm | **AGPL-3.0** | — | **elenir** |

Kod alınmaz, **kütüphane bağımlılık olarak kullanılır** (MIT/BSD izin
verir); referans depolardan yalnız davranış.

## 3 · Bizim mimarimize oturtmak

Bugün: huni `funnel/src/huniler/keepchat.ts` (veri) + `Huni.astro`
(motor); adım/cevap/atıf ölçümü motorun içinde, otomatik.

**Değişmeyecek olan (güçlü yanımız):** ölçüm motorun işi — editörde ne
kurulursa kurulsun adım, cevap, reklam, ödeme otomatik kaydedilir; huni
tanımı yine **doğrulanır** (bugün derlemede, yarın yayınlarken).

**Gerekecek parçalar:**
1. **Veri modeli:** `huniler` + `huni_surumleri` (yayınlanan sürüm
   **değişmez** JSON anlık görüntü) + taslak. Ziyaret, hangi sürümü
   gördüğünü taşır (sürümler arası panel karşılaştırması bunun üstüne).
2. **Bileşen kütüphanesi (şema):** tanıtım, tek seçim, çoklu seçim,
   metin/e-posta girişi, görsel, yorumlar, sayaç, plan seçici + paywall —
   her biri alanlar + sınırlı stil (tema belirteçleri: renk, yazı tipi,
   yuvarlaklık, boşluk). **RAW HTML yok** (XSS ve ölçüm motorunu atlama).
3. **Yayınlama:** iki yol —
   - (a) FunnelFox gibi yayında **statik derleme** (Astro build + CDN'e
     yükleme): B2'ye birebir uyar, en hızlı ilk boyama; ama sunucuda
     derleme hattı, dakikalık gecikme.
   - (b) **Statik kabuk + yayınlanmış JSON'u çalışma anında çekmek**
     (çekirdek `GET /huni/{app}` → CDN önbellekli, sürüm kimliğiyle):
     yayın anında canlı, hat yok; ilk boyamada bir istek daha (ölçülmeli).
   Öneri: **(b) ile başla**, ilk boyama ölçümü kötüyse (a)'ya geç —
   bileşen render kodu ikisinde aynı.
4. **Editör (panelde):** Puck (React) — sürükle-bırak, alan panelleri,
   canlı önizleme; JSON'u çekirdeğe kaydeder. Panel Twig; editör aynı
   kökenden sunulan ayrı bir React paketi (`/panel/editor`, oturum çerezi
   aynı köken → CORS yok). Önce Puck'sız **form tabanlı** düzenleme de
   mümkün (adım listesi, metin alanları, sürükle-sırala).
5. **Sonra:** A/B (ağırlıklı bölme; atama ziyaret oluşurken çekirdekte —
   yenilemede aynı varyant için tarayıcı deposu = çerez onayı, Ç12),
   koşullu dallanma/değişkenler, yerelleştirme (IP ülkesi = kişisel veri).

## 4 · Seçenekler (karar DEĞİL — Ç37)

| | Kapsam | Yaklaşık iş | Risk |
|---|---|---|---|
| **E1 · Panelde metin + sıra** | bugünkü adım türleri; metin, seçenek, sıra panelden; yayınla/sürüm/önizleme | 2–3 blok | düşük; A1'e en yakın |
| **E2 · Şemalı editör (FunnelFox'un çekirdeği)** | E1 + bileşen kütüphanesi, tema (renk/yazı tipi/yuvarlaklık), Puck ile sürükle-bırak, koşullu görünürlük | E1 + 4–6 blok | orta: React editör paketi, bileşen sözleşmesi, XSS denetimi |
| **E3 · Tam rakip** | E2 + A/B, dallanma/değişken, çok dil, AI | E2 + 5+ blok | yüksek; çerez/KVKK, istatistik |
| ~~Serbest HTML (GrapesJS)~~ | sayfayı serbest düzenleme | — | **reddedilmesi önerilir**: ölçüm motoru ve sözleşme atlanır, XSS |

**Öneri:** E2'yi hedefle, **E1'den başlayarak** — E1'in veri modeli,
yayınlama ve sürümü E2'nin temeli; boşa gitmez. Her faz kendi başına
kullanılabilir biter. E3'ün parçaları (A/B önce) gerçek trafik geldikçe.

## Kaynaklar
FunnelFox: <https://funnelfox.com/docs/editor/publishing.md>,
<https://funnelfox.com/docs/elements/overview.md>,
<https://funnelfox.com/docs/editor/localizations.md>,
<https://funnelfox.com/docs/dashboard/experiments.md>,
<https://funnelfox.com/docs/editor/coding-funnel-wide.md> ·
web2wave: <https://www.web2wave.com/post/a-b-testing>,
<https://www.web2wave.com/post/ai-localization>,
<https://docs.web2wave.com/reference/wavy-ai>,
<https://docs.web2wave.com/reference/mcp-server> ·
RevenueCat: <https://www.revenuecat.com/release/paywalls-on-the-web-2025-11-07> ·
Adapty: <https://ask.adapty.io/questions/p/custom-web-paywall> ·
Puck: <https://puckeditor.com/docs> · lisanslar: `gh api repos/<r>`,
`npm view @puckeditor/core license` (2026-10-20).
