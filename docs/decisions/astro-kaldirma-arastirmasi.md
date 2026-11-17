# Astro'yu kaldırma — ne kullanılır, nasıl yapılır (araştırma, 2026-11-03)

Kullanıcı 2026-11-03: "Astro'yu uygulamadan ve projeden kaldırma görevi; Vite + React / Preact olabilir, ne kullanılacağına da bak." Bu belge karar önerisi + uygulama planı; **kod değişmedi**.

B4 (`pre-setup.md`) zaten iki yolu açık bırakmıştı: "Astro sayfasında tek adacık **ya da saf Vite SPA**, ikisi de CDN'den statik". Astro'yu kaldırmak B2'nin özünü (sunucusuz statik huni + Symfony çekirdek) değiştirmez.

## Ölçüm — Astro bugün ne yapıyor

| Konu | Bugün | Astro'ya özgü mü |
|---|---|---|
| Sayfalar | 3: `/keepchat/` (kabuk + tek adacık `client:only`), `/keepchat/basarili/`, `/keepchat/iptal/` (statik HTML + modül betiği) | hayır — üçü de düz HTML + betik |
| Sunucuda çizim | **yok** (`client:only`, içerik çalışma anında `GET /huni/{app}`) | Astro'nun asıl gücü kullanılmıyor |
| Erken tanım isteği | `<script is:inline set:html>` ile satır içi (H0) | küçük bir Vite eklentisiyle aynısı |
| Modül ön yükleme | kendi Astro entegrasyonumuz (`onyukleme.ts`, 60 satır + test) | Vite girdinin durağan içe aktarmalarına **kendisi** `modulepreload` yazar |
| JS (gzip, `dist/_astro/*.js`) | **20.606 B**, 9 dosya | bunun ≈ 4,5 KB'ı Astro'nun: adacık çalışma zamanı `client.js` (1.531 B) + `@preact/signals` (2.997 B, `@astrojs/preact` getiriyor) |
| Derleme aracı | `astro` 7.3.8 → içinde **Vite 8.3.4** | Vite zaten var |
| Bağımlılık | `node_modules` 340 MB, 329 paket | çoğu Astro + `@astrojs/check` |
| Tip denetimi | `astro check` | `tsc --noEmit` yeter (`.astro` dosyası kalmayınca) |
| Biçim | `prettier-plugin-astro` | gereksiz olur |
| Testler | Vitest + happy-dom, React→Preact **takma adıyla** (Babel/ön ayar yok) | Astro'dan bağımsız — aynen kalır |
| Geliştirme | `huni-dev` kabı `astro dev` :4321; `ekran` kabı ona bağlanır | `vite` :4321 ile aynı adres |
| Üretim | Caddy `dist/`'i statik sunar; Stripe dönüşü `/keepchat/basarili` ve `/keepchat/iptal` (eğik çizgisiz) | `dist` düzeni (`keepchat/basarili/index.html`) korunursa Caddy ve çekirdek **değişmez** |

## Seçenekler

| # | Seçenek | Artı | Eksi | Karar |
|---|---|---|---|---|
| 1 | **Vite çok sayfalı (MPA) + Preact** (React adları `preact/compat` takma adıyla — testlerdeki gibi) | 3 HTML girişi = bugünkü 3 sayfa, adresler ve `dist` düzeni aynı; ön yükleme Vite'ın kendisinde; ≈ 4,5 KB JS ve tüm Astro bağımlılıkları gider; ek eklenti yok | erken istek için küçük bir eklenti (≈ 15 satır); geliştirme sunucusunda eğik çizgisiz adres ölçülmeli | **öneri** |
| 2 | Vite MPA + **React** | panelle aynı çalışma zamanı | L20'de ölçüldü: JS 19,7 → 77,4 KB gzip (≈ +57 KB), bütçe 30 KB aşılır | reddedilir — Preact zaten ölçüldü (Ç81) |
| 3 | Tek sayfa SPA + istemci yönlendirme | tek giriş | ödeme dönüş sayfaları da ana pakete girer; Caddy'de yeniden yazma kuralı gerekir (dağıtım değişir) | reddedilir |
| 4 | Vike (Vite SSR/ön çizim) | sayfa başına ön çizim | sunucuda çizilecek veri yok; yeni çatı + öğrenme bedeli | reddedilir |
| 5 | `@preact/preset-vite` | Preact için resmî ön ayar, sıcak yeniden yükleme | Babel (`@babel/core`) eş bağımlılığı; testler ön ayarsız zaten çalışıyor | gerekmez (sorun çıkarsa yedek) |

**Öneri: Seçenek 1.** Sürümler (iki hafta kuralı, 2026-11-03 ölçüldü): `vite` 8.3.4 (2026-10-08, bugün zaten kullanılan), `preact` 11.0.1 (kalır).

## Plan (her adım ayrı `/blok`, ölç → kır → tarayıcı)

- **S1 · Prototip ve ölçüm** — `vite.config.ts` (MPA girdileri `keepchat/index.html`, `keepchat/basarili/index.html`, `keepchat/iptal/index.html`; `envPrefix: ["PUBLIC_"]`; React→Preact takma adları; `fs.allow` + tek-react bekçisi; port 4321). Ölç: aynı 3 dosya yolu, JS gzip (beklenen ≈ 16 KB), `modulepreload` sayısı ve **ilk JS turu** (H2/Ç52 ölçüsüyle), geliştirmede `/keepchat/basarili` eğik çizgisiz.
- **S2 · Sayfaları taşı** — `HuniKabugu.astro` + `index.astro` → `keepchat/index.html` + `src/giris/huni.tsx` (`render(<Huni app="keepchat"/>)`, yükleniyor metni HTML'de); erken istek eklentisi (`transformIndexHtml`, `tanimIstegi.toString()` + JSON kaçışı — bugünküyle aynı metin); `basarili`/`iptal` betikleri ayrı `.ts` dosyalarına (içerik aynen).
- **S3 · Araçlar** — `astro check` → `tsc --noEmit` (`tsconfig` Astro'nun "strictest"inden kendi seçeneklerimize), `prettier-plugin-astro` kalkar, `bin/kontrol` adımları (`funnel-onyukleme` Vite'ın ön yüklemesini denetler; `funnel-butce` `dist/assets`), `onyukleme.ts` + testi silinir (yerine Vite davranışını ölçen kontrol).
- **S4 · Geliştirme ortamı** — `huni-dev` kabı `vite --host --port 4321`; `ekran` kabı ve AI ekran görüntüsü aracı aynı adresle denenir; `docs/adresler.md`, `deploy/README.md`, `pre-setup.md` B2/B4 satırları.
- **S5 · Tarayıcıda eşlik** — bugünkü dağıtım ile yeni dağıtım yan yana: H3/L20 ölçüsü (öğe × hesaplanmış stil farkı 0), anket uçtan uca (önizleme, cevap, ödeme düğmesi, `basarili` giriş bağlantısı, `iptal`), reklam pikselleri izinle.
- **S6 · Kaldır** — `astro`, `@astrojs/*`, `prettier-plugin-astro` paketten; `package-lock` yenilenir; `node_modules` boyutu ve paket sayısı yeniden ölçülür.

## Riskler ve ölçülmesi gerekenler

- **Ön yükleme eşdeğeri:** Ç52'de ölçülen "ikinci JS turu" geri gelmemeli — Vite girişin durağan içe aktarmalarını önden yükler, tembel (`import()`) olanları yüklemez; oynatıcıda tembel içe aktarma var mı S1'de bakılır. Kırma: `modulePreload: false` → kontrol kırmızı.
- **Erken istek:** tanım isteği yine HTML ayrıştırılırken başlamalı (H0); eklenti satır içi betiği `<head>`'e yazar — S1'de ilk bildirim zamanı H2/ada-önyükleme tablosuyla karşılaştırılır.
- **Eğik çizgisiz adres:** üretimde Caddy bugünkü gibi; geliştirmede Vite `/keepchat/basarili`'yi bulamazsa küçük bir yönlendirme ara katmanı (yalnız geliştirme).
- **Gözden kaçan Astro işi:** CSS'in sayfaya bağlanması (`huni.css` + yazı tipleri) ve içerik özetli adlar (§30) Vite'ta da var — S1'de `dist` karşılaştırması.

## Açık soru (kullanıcı)

Ç81 (Preact onayı) hâlâ açık; öneri Preact ile sürdürmek. React istenirse Seçenek 2 + JS bütçesi yeniden konuşulur.
