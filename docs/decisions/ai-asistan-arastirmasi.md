# Panel içi AI asistanı + MCP — rakipler nasıl yapmış, bizde nasıl kurulur (2026-10-27)

Kullanıcı: "MCP'yi içine gömeceksin; prompt iç arayüzde bir yere yazılacak.
Nasıl yapmışlar araştır, sonra sıralamayı ayarla". Önceki ölçüm: Ç67
(makine erişimi yok; ama promptla yazılmış tanım `Tanim::hatalar` → `[]`).
**Kod alınmadı** (kural 12) — belgeler okundu.

## Rakipler

| Ürün | Panel içi | Dışarıdan (MCP) | Dikkat çeken |
|---|---|---|---|
| **web2wave** | **Wavy AI** (Beta): düzenleyicide düğme → doğal dil isteği → açık quiz/paywall'a ekran üretir ya da yeniden yazar; sonuç **sıradan düzenleyici içeriği**, yayınlanmadan ziyaretçiye gitmez, sürüm geçmişiyle geri alınır. Önerileri: kopya üzerinde dene, tek seferde tek değişiklik, yayından önce para/mantık/mobil kontrolü | `https://mcp.web2wave.com/mcp`, proje API anahtarı (Bearer) ya da OAuth PKCE; **70+ araç** (içerik, abonelik, analitik, deney, editör şeması — 410+ blok tipi, `validate_quiz_json`, Playwright ekran görüntüsü, belge arama); hazır **istem senaryoları** (tasarımdan quiz: brief → plan → bloklar → JSON → doğrula → içe aktar); **yıkıcı işlemler (iptal, iade, silme, ücret) barındırılan uçta kapalı** | "Wavy AI mı MCP mi": aynı huniler, iki kapı |
| **FunnelFox** | **AI Chat in Editor**: tuvalde öğe/ekran seç → odak; istem → ajan tuvali canlı değiştirir; **tur başına Geri Al**; görsel referans ekle; **proje bağlamı** (uygulama kimliği, kategori, marka, ses, kitle — mağaza bağlantısından otomatik, düzenlenebilir) + sektör kalıpları | MCP (Beta): Claude Code / Cursor huni okur, toplu düzenler | bağlam olmadan "sonuçlar belirgin şekilde kötü" |
| **Puck** (bizim düzenleyici) | `@puckeditor/plugin-ai`: sol çubuğa sohbet eklentisi; arka uç `puckHandler` → **Puck Cloud** (ücretli, OpenAI modelleri); `generate({prompt, config, pageData})` başsız üretim; "design mode" yeni bileşen + isteğe bağlı betik üretir | — | **Reddedildi** (aşağıda) |

## Bizde kurulum — tek araç katmanı, iki kapı

```
 Panel (React)                         Claude Desktop / Claude Code
  └─ AI sohbet kutusu (Puck eklentisi)    └─ MCP istemcisi
        │ SSE akışı                              │ stdio (yerel) / HTTP (sunucuda)
        ▼                                        ▼
 /panel/api/ai/sohbet (oturum+CSRF)     bin/console mcp:server  /  /mcp
  └─ Claude API (PHP SDK, tool runner)          │
        └──────────────┬─────────────────────────┘
                       ▼
          HuniAraclari (Symfony servisi) — TEK araç tanımı
          huniler_listele · huni_oku · tanim_dogrula · taslak_kaydet · onizleme_adresi
          girdi şeması = sözleşmedeki HuniTanimi (openapi.yaml'dan, elle kopya yok)
                       ▼
          Huniler + Tanim (mevcut doğrulama, taslak/imza/sürüm)
```

- **Araç şeması sözleşmeden üretilir:** `HuniTanimi` blok kataloğu büyüdükçe
  AI'ın yapabildiği de kendiliğinden büyür (temel 1 ile birlikte).
- **Doğrulama döngüsü:** `taslak_kaydet` hata dönerse `tool_result` +
  `is_error` ile hatalar modele geri gider, model düzeltir (Türkçe, açık
  mesajlar zaten var — ölçüldü). Tur sınırı var.
- **Yalnız taslak:** panel sohbetinde **yayınla aracı yok**; yayın insan
  düğmesi. Her AI turu taslağı değiştirmeden önce mevcut taslak sürüm
  geçmişine alınır → "Geri al" = önceki taslağa dön.
- **Model:** `claude-opus-5-5`, adaptive thinking (Opus 5.5'te kapatılamaz),
  `effort` açıkça yazılır (varsayılan `medium`), akış (streaming) +
  `eager_input_streaming` (istemci şemaya karşı yeniden doğrular — biz zaten
  çekirdekte doğruluyoruz), `strict: true` araçlar, araç + sistem istemi
  önbelleğe (prompt caching), `refusal` durdurma nedeni + sunucu tarafı
  `fallbacks`. Zorunlu `tool_choice` Opus 5.5'te 400 → `auto` + istem.
- **SDK:** `anthropic-ai/sdk` (resmi PHP, tool runner `BetaRunnableTool`).
  MCP: resmi `mcp/sdk` (PHP, Symfony ile birlikte yürütülüyor) +
  `symfony/mcp-bundle`. **Sürüm ölçümü (Packagist, 2026-10-27):**
  `anthropic-ai/sdk` 1.3.0 (4 gün), `mcp/sdk` 0.9.0 (9 gün),
  `symfony/mcp-bundle` 1.0.0-BETA1 (5 gün) → iki hafta kuralı: kurarken
  daha eski kararlı sürüm seçilir; bundle beta → önce yalnız `mcp/sdk`.
- **MCP yerelde sunucusuz çalışır:** stdio aktarımı — Claude Desktop
  `docker compose run --rm -T core bin/console mcp:server` başlatır. HTTP
  `/mcp` + belirteç dağıtımdan sonra (sunucu ertelendi). Anthropic'in MCP
  connector'ı (sunucudan bağlanma) yerel çekirdeğe ulaşamaz → panel
  sohbeti MCP üzerinden değil, araç katmanını doğrudan çağırır.
- **Bağlam (FunnelFox dersi):** uygulama başına ürün profili (ad, kategori,
  kitle, ton, yasaklar) panelde düzenlenir, sistem istemine girer.
- **Güvenlik:** anahtar yalnız sunucuda (`core/.env.local`); araçlar
  yalnız açık uygulama/huniye yetkili; yıkıcı araç yok (sil, yayınla, kural
  değiştirme yok); ziyaretçi verisi (ziyaret, e-posta, serbest yanıt) AI'a
  **gitmez** — yalnız huni tanımı; istek başına tur/jeton sınırı + oturum
  başına hız sınırı; her istek kayıt tablosunda (jeton, maliyet, süre,
  sonuç) — panelde aylık maliyet.

## Reddedilenler
- **Puck AI eklentisi** — Puck Cloud'a (üçüncü taraf, ücretli) huni
  içeriğini gönderir, OpenAI modelleri; "design mode" yeni bileşen ve betik
  üretir → D1 (tanım HTML/JS taşımaz) ve çekirdek doğrulamasının dışında
  kalır. Bizim sohbet arayüzü Puck'ın eklenti çubuğuna **kendi eklentimiz**
  olarak girer.
- **Panel sohbetini kendi MCP sunucumuz üzerinden Anthropic MCP connector'la**
  — çekirdeğin herkese açık HTTPS adresi gerekir (dağıtım yok); doğrudan
  araç çağrısı daha az parça.
- **AI'a yayınlama yetkisi** — web2wave'de de yıkıcı işlemler kapalı;
  reklam ve deney bağı olan huniyi insan yayınlar.
- **Managed Agents** — sandbox/dosya gerekmez; döngü kısa, araçlar bizim
  sunucuda → Claude API + tool runner yeterli ve basit.

## Karar değişikliği — 2026-10-27 (B6)
Kullanıcı: ilk aşama yalnız dışarıya açılan MCP sunucusu (URL). Yukarıdaki
"panel sohbeti önce, MCP sonra; MCP önce stdio" sırası değişti: **MCP HTTP
önce**. Neden: MCP'de model kullanıcının Claude aboneliğinde çalışır, API
anahtarı gerekmez. A2/A3 kapalı kalır (anahtar yok), silinmez.
