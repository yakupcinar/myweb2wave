# Rakipler görselliği ve AI tasarımını nasıl çözüyor (2026-11-03)

**İstek:** kullanıcı 2026-11-03: "daha çok varlık, tema ve yol lazım; belki
AI'ın quiz sayfasını daha iyi kurmasına yardım eden depolar — rakipler ne
yaptı". Önceki soru Ç99 (sınır araçta mı yazarda mı).

## Rakiplerde ölçülen (kendi belgelerinden)

| Rakip | Görsel kapsam | AI |
|---|---|---|
| **FunnelFox** (Adapty) | 22 öğe: buton, metin, görsel/video, liste, seçenekler, metin girişi, tarih seçici, kaydırmasız SSS, yorumlar, **grafik** (sonuç projeksiyonu), **processing** (çok adımlı ilerleme), yükleyici, zamanlayıcı, popup (animasyonlu), plan/fiyat seçici, ödeme, cüzdan düğmesi, kap (container), **Raw (serbest HTML/CSS/JS)**, Lottie + video. Öğe başına Stil sekmesi (dolgu, zemin/gradyan, köşe, kenar, gölge, konum: akışta/sabit). Tema: global renk, font, düğme, yükleyici; öğe bazında ezme (⚡) + "temaya dön". Görünürlük: cevap, ülke, işletim sistemi, tarayıcı (Instagram/Facebook uygulama içi), ücretli/organik, URL parametresi, Apple Pay var mı. Hazır şablon kütüphanesi (niş başına) | Editörde AI sohbet + MCP + **Claude Code eklentisi `fnlfx/ai`** |
| **Heyflow** | "600+ stil değişkeni", ekran görüntüsünden/URL'den **tasarım içe aktarma**; inceleme: font/animasyon ince ayarı sınırlı (TrustRadius) | — |
| **RevenueCat Funnels** | Huni ekranları paywall düzenleyicisiyle aynı motor; hazır huni şablonları | Paywall'da "AI ile üret" (açıklama + ekran görüntüsü) ve sohbetle düzenleyen ajan (beta) |
| **Web2Wave** | Ana sayfa: hazır bloklu sürükle-bırak, "oyunlaştırılmış ekranlar", ilerleme çubuğu | Yalnız AI çeviri (40+ dil) görüldü; animasyon/özel CSS belgesi bulunamadı |

## `fnlfx/ai` — FunnelFox'un Claude eklentisi

**Lisans ÖLÇÜLDÜ: tescilli** ("All rights reserved"; kopya, uyarlama,
çeviri yasak, yalnız FunnelFox hesabıyla kullanım). Kural 12: **tek satır
alınmaz**; aşağıdakiler yalnız yaklaşımın kendi cümlemizle özeti.

Yapı: tasarım becerisi + düzenleme becerisi + strateji becerisi + ekran
görüntüsü ve "huniyi yürü" betikleri + tasarımcı/araştırmacı alt ajanları +
kayıttan önce şema doğrulayan kanca. Yaklaşım:

- **Aşama modeli:** giriş (tek kolay dokunuş) → erken hedef sorusu →
  ürünün gerçekten kullandığı profil soruları → soru grupları arasında
  kanıt/değer ekranı → cevaplardan kurulan sonuç/projeksiyon (önünde
  işleme ekranı) → isteğe bağlı e-posta (ne alacağıyla çerçeveli) →
  paywall → ödeme → "şimdi ne olacak" ekranı.
- **Görsel kalite denetim listesi, her ekran görüntüsüne karşı:** hiyerarşi
  (önce başlık), tek birincil eylem, kontrast (seçili seçenek dahil),
  aralık, tüm ekranı kullanmak (üst üçte toplanıp altı boş kalmasın), tek
  kelimelik son satır yok, şablon artığı yok, marka tutarlılığı.
- **Döngü:** düzenle → ekran görüntüsü al → listeye göre bak → düzelt;
  "nasıl göründüğünü" görüntüye bakmadan anlatmamak.
- **Emoji uyarısı:** emoji cihazın kendi renkleriyle çizilir, temayı
  izlemez — markalı simge tercih edilir.
- **Görselsiz zanaat:** görsel yoksa düz gri kutu/çıplak satır şablon gibi
  okunur; yapı ve tipografiyle telafi.
- Dürüstlük kuralları (uydurma sayı/yorum yok, gerçek biten sayaç, hayalî
  "eski fiyat" yok) — bizde zaten D1/yönerge var.

## Bizdeki karşılık (Ç99 ölçümü)

Tanımda animasyon alanı yok; hareket `huni.css`'te 4 sabit animasyon. Tema
11 belirteç, öğe bazında stil yok. AI yönergesi `Tanim.php`'de tek paragraf;
**aşama modeli, görsel denetim listesi ve görüntüye-bak döngüsü YOK** —
`ekran_goruntusu` aracı var ama yönerge onu kullanmayı şart koşmuyor.
`uyku-ornek`te altı boş ekran (Ç97 gözlemi) tam bu listenin yakalayacağı
kusur.

## Açık lisanslı varlık kaynakları (lisans depo başına ÖLÇÜLDÜ, gh api)

| Kaynak | Lisans | İşe yarar |
|---|---|---|
| microsoft/fluentui-emoji | MIT | Cihazdan bağımsız, tutarlı 3B/düz emoji görselleri (emoji sorununu çözer) |
| lucide-icons/lucide | ISC (LICENSE okundu) | Çizgi simgeler; `currentColor` ile temayı izler |
| phosphor-icons/core | MIT | Alternatif simge seti (ağırlık varyantlı) |
| material-foundation/material-color-utilities | Apache-2.0 | Tek tohum renkten erişilebilir palet — AI renk uydurmaz, tohum seçer |
| airbnb/lottie-web · LottieFiles/dotlottie-web | MIT | Lottie oynatıcı; **animasyon dosyalarının lisansı dosya başına ayrı** |
| unDraw | depo bulunamadı; kendi lisansı — ÖLÇÜLMEDİ | — |

AI'a yardım eden açık kaynak "quiz tasarım" deposu aranıp bulunamadı
(Ratne/QuizFunnel: lisanssız, 0 yıldız, şemasız). En işe yarar kaynak
rakiplerin kendi belgeleri; yaklaşım kendi cümlemizle yazılır.

## Önerilen sıra (V1–V6, kullanıcı kararı bekliyor — Ç99)

| # | İş | Neden bu sırada |
|---|---|---|
| V1 | **AI tasarım rehberi + görsel döngü**: aşama modeli, görsel denetim listesi, "kaydetmeden önce her ekranın görüntüsüne bak" zorunluluğu (panel asistanı + MCP yönergesi), otomatik kontrast denetimi çekirdekte | En ucuz, "Claude'un stili" kısmını doğrudan düzeltir; kod değil yönerge + bir denetim |
| V2 | **Tema ön ayarları + tohum renkten palet** (8–12 hazır tema; material-color-utilities) | AI renk uydurmak yerine seçer; kontrast garantili |
| V3 | **Varlık kütüphanesi**: Fluent emoji görselleri (seçenek/kutu simgesi temadan bağımsız tutarlı), Lucide simgeler (maddeler, faydalar); fotoğraf/illüstrasyon kaynağı kullanıcı kararı | Görselsiz huni sorununun yarısı varlık eksikliği |
| V4 | **Hareket katmanı** (tema belirteci `hareket`: yok/sakin/canlı) + blok giriş sırası, seçim efekti, istatistik sayacı — katalog, serbest CSS yok (D1) | Araç sınırının asıl kısmı |
| V5 | **Yeni bloklar**: projeksiyon grafiği, kaydırıcı/ölçek, önce/sonra, Lottie (dosya lisansı tek tek) | Ç97 (2)(3)(5) ile birleşir |
| V6 | **Niş şablonları** (uyku, fitness, dil, finans…): depoda tanım JSON'u; `yeni_huni` ve AI için örnek | AI'ın en iyi öğretmeni iyi örnek |

Kaynaklar: funnelfox.com/docs (elements/overview, editor/themes, llms.txt),
github.com/fnlfx/ai (LICENSE + beceri başlıkları), heyflow.com (arama
özeti; ürün sayfası 403), revenuecat.com (paywall AI, funnels), web2wave.com.
