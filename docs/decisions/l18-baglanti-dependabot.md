# L18 — ölü bağlantı denetimi (Ç23) ve Dependabot (Ç14) (2026-11-02, döngü)

## Ç23 · Huni derlemesinde ölü iç bağlantı
- **Sorun:** taşınan iptal sayfası `/`'a (404) bağlanıyordu; tip denetimi ve derleme yakalamadı.
- **Karar:** `funnel/src/baglanti.ts` saf fonksiyon (`oluBaglantilar`): `href`/`src` içinde `/` ile başlayan her bağlantı, sorgu/çapa atılıp `yol`, `yol/index.html` (ya da `/` ile bitiyorsa `yol/index.html`) olarak bir **dosyaya** çözülmeli; dış, `//`, `#`, şema bağlantıları (`mailto:`, `keepchat://`) denetlenmez. `funnel/scripts/baglanti-denetimi.ts` `dist`'i tarar (Node 24 TS'yi doğrudan koşar); `bin/kontrol` adımı **`funnel-baglanti`** (derlemeden sonra; CI aynı komut).
- Kendi kusurumu yakaladım: ilk sürüm `existsSync` kullanıyordu — dizin de "var" sayılırdı, `index.html`'siz yol geçerdi → yalnız `isFile()`.
- Testler: `baglanti.test.ts` (çözülenler, ölüler sayfasıyla, denetlenmeyenler).
- **Gerçek kırma:** Ç23'ün eski hatası geri getirildi (iptal → `href="/"`): denetim `keepchat/iptal/index.html → /` yazıp **1** ile çıktı; geri alınınca 0. Bugünkü çıktı: 3 sayfa, 11 çözüm denemesi, 0 ölü.

## Ç14 · Dependabot
- **Karar:** `.github/dependabot.yml` — composer (`/core`), npm (`/funnel`, `/core/panel`, `/contract`), docker (5 Dockerfile dizini), docker-compose (`/`), github-actions; haftalık; **`cooldown: default-days: 14`** = projenin iki hafta kuralı (yeni sürüm hemen önerilmez); küçük + yama güncellemeleri ekosistem başına tek PR (gruplar). Birleştirme insanda, CI `bin/kontrol`.
- **Ölçüm:** SchemaStore resmi şeması (`dependabot-2.0.json`, draft-07; `cooldown`, `docker-compose`, `directories` içeriyor) + `ajv` → **GEÇERLİ**. Kırma: `default-days: "iki hafta"` → GEÇERSİZ ("must be integer"), çıkış 1.
- Ölçüm sırasında iki kendi hatam: şema adresi yönlendirme döndürdü (`curl -L` yoktu, "Document Moved" HTML'i şema sanıldı); doğrulama çıkış kodu `| grep` ile yutuldu ve `EXIT=0` göründü (**tuzak §1** — yeniden borusuz ölçüldü).
- **Ölçülmedi / sınır (Ç80):** Dependabot yapılandırmayı **yalnız varsayılan daldan (`main`)** okur; iş `h6-olay`'da (PR #40) — birleşene kadar hiçbir PR açılmaz. GitHub'ın yapılandırmayı gerçekten kabul ettiği birleşmeden sonra "Insights → Dependency graph → Dependabot"tan okunur.

`bin/kontrol` yeşil (313 çekirdek testi + `funnel-baglanti`).
- CI: koşu 41404083397, SHA ff12c23, `KONTROL: YEŞİL` (`funnel-baglanti` CI'da da koştu), 313 test.
