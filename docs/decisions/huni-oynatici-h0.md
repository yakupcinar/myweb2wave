# Blok: H0 — huni oynatıcısı geçişi öncesi taban ölçümü (B4) — 2026-10-21

Kod değişmedi. Amaç: React'e geçmeden önce bugünkü huninin boyutunu,
içeriğin ekrana gelme zincirini ve korunacak davranışları sabitlemek.

## (a) Ölçüm

**Boyut** (`funnel/dist`, `bin/kontrol` derlemesi; gzip -9):

| Dosya | Ham | gzip |
|---|---|---|
| `keepchat/index.html` | 1.561 B | 926 B |
| `HuniKabugu…js` (oynatıcı) | 4.524 B | 2.238 B |
| `cekirdek…js` (API istemcisi) | 6.513 B | 2.584 B |
| **Anket sayfası JS toplamı** | **11.037 B** | **4.822 B** |
| En küçük React 19.3.0 paketi (`createRoot` + `<p>`; Vite 8.3.3, düzenleyici kabında, sonra silindi) | 218.800 B | **67.374 B** |

→ Yalnız React, anket JS'ini gzip'te ~14 katına çıkarır (Puck dahil değil).

**İçeriğin geliş zinciri** (Chrome, yayın derlemesi `python3 -m http.server`
ile `:4321`'den, çekirdek yerel `:8081`; ağ kısıtı YOK; Resource Timing):

| Kaynak | başlangıç → bitiş (ms) |
|---|---|
| oynatıcı JS | 44 → 94 |
| `cekirdek.js` (oynatıcının `import`'u — zincirleme) | 95 → 96 |
| `GET /huni/keepchat` (tanım) | 102 → 212 |
| ziyaret + ilk adım bildirimi | 230 → 310 |

- İçerik, **sıralı üç ağ adımından** sonra çizilebiliyor: HTML → JS (iki
  dosya, zincirleme) → tanım isteği. Tanım isteği JS çalışmadan başlamıyor.
- Uç ısınmışken `GET /huni/keepchat` ~20 ms (5 ölçüm 18–24 ms); ilk istek
  **2,38 sn** — geliştirme ortamında soğuk Symfony önbelleği (az önce
  `cache:clear`). Üretim imajında soğuk başlangıç ölçülmedi.
- FCP/LCP alınamadı: sekme arka plandaydı, Chrome görünmeyen sekmede boyama
  kaydı tutmuyor. Yavaş mobil ağda süre **ÖLÇÜLMEDİ** (araçta ağ kısıtı yok) → Ç50.

**Bulgu (karar B4 için önemli):** React adacığı bu zinciri kısaltmaz,
**uzatır** — içerik isteği yine JS çalıştıktan sonra başlar ve önce ~67 KB
daha indirilip çalıştırılır. B4'ün "ilk boyama gecikmesi" gerekçesini React
değil, **tanım isteğini JS'ten bağımsız erken başlatmak** (HTML'de
`<link rel="preload">` ya da satır içi küçük betik) ya da yayında tanımı
statik dosyaya yazmak çözer. Bunu H2'ye ekledim (PLAN).

## (b) Korunacak davranışlar — H2'nin test listesi

Kaynak: `funnel/src/components/HuniKabugu.astro` (bugünkü hâl).

| # | Davranış | Nasıl test edilir (H2) |
|---|---|---|
| D1 | Metinler yalnız düz metin; tanım HTML taşıyamaz | `<b>x</b>` içeren başlık metin olarak görünür; `dangerouslySetInnerHTML` yok (kontrolde grep) |
| D2 | Görsel adresi yalnız 64-hex kimlikten: `${CORE}/medya/<id>.webp`, `alt=""` | adres biçimi |
| D3 | Önizleme (`?onizleme=`): taslak ucu, uyarı şeridi, ziyaret/adım/cevap bildirimi YOK, ödeme düğmesi kapalı | sahte istemciyle çağrı sayısı 0 |
| D4 | Önizleme açılamazsa "bağlantının süresi dolmuş olabilir"; yayın açılamazsa "Sayfa yüklenemedi" | hata metni |
| D5 | Seçim parametreleri yalnız `k`, `utm_source`, `utm_campaign`, `utm_content`, `huni`, her biri ≤ 200 karakter (fbclid gitmez — önbellek, A8) | giden sorgu |
| D6 | `document.title` = `sayfaBasligi` | başlık |
| D7 | Tema: CSS değişkenleri `setProperty` ile; düğme yazısı siyah/beyazdan okunanı; tema yoksa varsayılanlar (`Tema::VARSAYILAN` ile aynı) | değişken değerleri; çekirdekle aynı formül |
| D8 | İlerleme çubuğu: en çok = adım sayısı, değer = sıra + 1 | `progress` |
| D9 | Tanıtım düğmesi ve seçenek sonraki adıma geçer; seçenek önce cevabı bildirir | bildirim sırası |
| D10 | Her adımda `history.pushState`; ilk adım `replaceState`; **geri tuşu önceki adıma** döner, sayfadan çıkmaz | `popstate` |
| D11 | Adım değişince başlığa (`h1`, `tabIndex=-1`) odak | `document.activeElement` |
| D12 | Ziyaret bir kez açılır (huni + sürüm); her adım gösterilince adım bildirimi; bildirim hatası akışı durdurmaz | hata yutulur |
| D13 | Ödeme: plan adı + (varsa) ziyaret kimliği → `POST /checkout/sessions` → dönen adrese yönlenme; tıklanınca düğme kapanır (çift tıklama yok) | gövde, `disabled` |
| D14 | Ödeme hataları: 502 `payment_provider_unavailable` → "Ödeme sağlayıcısına şu an ulaşılamıyor…"; diğer → "Bir şeyler ters gitti…"; ağ hatası → "Bağlantı kurulamadı…"; düğme yeniden açılır | üç metin |
| D15 | Yükleniyor durumu ("Yükleniyor…") içerik gelince kalkar | DOM |

Başarı sayfası (`basarili.astro`, A10) bu geçişin dışında; ayrı sayfa kalır.

## (c) Bütçe önerisi
Anket sayfası JS'i **≤ 90 KB gzip** (React 67 KB + oynatıcı ve bileşenler
~15 KB + `cekirdek` 2,6 KB). `bin/kontrol`'de H2 ile ölçülür (derleme
çıktısının gzip toplamı; aşınca kırmızı). Alternatif: Preact (`preact/compat`,
Puck bileşenleri aynı kalır) — boyutu ve Puck'la uyumu ÖLÇÜLMEDİ → Ç50.

## Ortam notu
Disk %98 dolu (5,5 GB boş). Tarayıcı ölçümü için `huni-dev` kabı durduruldu,
`dist` aynı portta sunuldu, sonra kap `--force` ile yeniden açıldı (kilit
dosyası kalmıştı) ve `:4321` 200 döndü.
