# A5a — Kişisel API anahtarları + `/mcp` (Streamable HTTP)

2026-10-27 · karar B6; araştırma `mcp-sunucu-arastirmasi.md`. Kullanıcı: "web2wave
personal API key veriyor, biz de öyle yapabiliriz; son ürün tek şirketin
web2wave'i".

## Ölçüm (önce)
- `mcp/sdk` 0.8.1 kaynağı: araç işleyicisine argümanlar ADLA eşlenir;
  `RequestContext` enjekte edilir (ham `arguments` buradan); `tools/call`
  argümanları önce **opis/json-schema ile inputSchema'ya göre doğrular**
  (uymayan → JSON-RPC `-32602`, araç çalışmaz); `CallToolResult(content,
  isError, structuredContent)`; DNS rebinding ara katmanı `allowedHosts`
  alır (varsayılan yalnız yerel).
- `psr/simple-cache` kurulu değildi (SDK'nın PSR-16 oturum deposu ister) →
  3.0.0 (2021) eklendi. `symfony/psr-http-message-bridge` require'a taşındı.

## Karar
- `api_anahtarlari` (göç `Version20261027140000`): anahtar `mw2w_` + 48 hex
  (192 bit), **yalnız SHA-256 özeti** (CHECK `^[0-9a-f]{64}$` — düz metin
  yazılamaz) + görünen önek (11); uygulamaya bağlı; son kullanım; iptal
  (geri alınamaz). Yavaş özet gerekmez: rastgele, yüksek entropi.
- `mcp_cagrilari`: anahtar, araç, hata, zaman.
- `/mcp` (GET/POST/DELETE): `Authorization: Bearer` → `ApiAnahtarlari::dogrula`;
  yoksa 401 `WWW-Authenticate: Bearer realm="MyWeb2Wave MCP"`; **sorgu
  dizgesinde anahtar kabul edilmez**; anahtar başına 120/dk (429). Panel
  güvenlik duvarı dışında. PSR köprüsüyle `StreamableHttpTransport`; CORS +
  DNS rebinding (`MCP_IZINLI_HOSTLAR`). Oturum (2025-11-25 çağı) Symfony
  önbelleği → PSR-16.
- `McpSunucusu`: araçlar A0'dan (aynı tanım ve çalıştırıcı; kapsam
  anahtarın uygulaması, yeni huni açılabilir; yayın/silme yok); her çağrı
  kayda. **Talimat** (instructions): çalışma biçimi + kurallar + **A4 ürün
  bağlamı** — Claude Desktop/Code da aynı bağlamı alır.
- Panel: `GET/POST /panel/api/uygulamalar/{app}/anahtarlar`, `DELETE …/{id}`
  (sözleşmede; CSRF `panel-api`); liste `mcpAdresi` (`MCP_GENEL_ADRES`) verir.
  React "API anahtarları" sayfası: anahtar + hazır `claude mcp add …`
  komutu BİR KEZ, kopyalama; iptal iki adımlı (tarayıcı onay penceresi yok).
  Menü React + Twig.
- **Reddedilen:** anahtarı düz/şifreli saklamak (yalnız doğrulama gerekir) ·
  `?api_key=` (web2wave'de var; MCP spec ve Claude belgesi yasaklıyor,
  günlüklere sızar) · `symfony/mcp-bundle` (beta, 0.9 ister — iki hafta
  kuralı) · SDK'nın `AuthorizationMiddleware`'i (OAuth kaynak sunucusu
  üst verisi ister; düz Bearer için denetleyicide doğrulama daha yalın —
  A5c OAuth'ta yeniden ele alınır).

## Test
`McpTest` (6): anahtarsız/yanlış/öneksiz/iptalli/sorgu dizgesi → 401 +
başlık; özet saklanır, düz metin yok; uçtan uca el sıkışma (oturum kimliği,
talimatta bağlam) → `notifications/initialized` 202 → araç listesi (şema
`$defs`) → oku → taslağa yaz (yayınlanmadı) → çağrı kaydı + son kullanım;
`isError` + Türkçe hata; şema dışı alan ve `yayinla` `-32602`, araca
ulaşmaz; izinsiz Host 403; hız sınırı 429. `PanelAnahtarTest` (3): girişsiz;
oluştur (201, sözleşme) → MCP'de 200 → liste önek, anahtar yok → iptal 204 →
MCP 401 → ikinci iptal 404; CSRF/ad/uygulama. Panel `Anahtarlar.test.tsx`
(2): anahtar + komut bir kez, kapatınca yok; iptal iki adımlı, jetonla.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | özet yerine anahtarın parçası | 🔴 — DB CHECK kendisi reddetti (ikinci katman); K1b geçerli biçimli yanlış özet → 🔴 6 |
| K2 | iptal edilen kabul | 🔴 2 |
| K3 | `?api_key=` kabul | 🔴 |
| K4 | DNS rebinding yok | 🔴 |
| K5 | hız sınırı yok | 🔴 |
| K6 | çağrı kaydı yok | 🔴 2 |
| K7 | talimatta bağlam yok | 🔴 |
| K8 | oluşturmada CSRF yok | 🔴 |
| K9 | iptal tek adım | 🔴 (ilk deneme prettier biçimi yüzünden koşulmadı; yeniden) |
| K10 | komutta `Bearer` yok | 🔴 |

## Gerçek (dev çekirdek)
curl: anahtarsız 401; `initialize` 200 + `Mcp-Session-Id` + talimat;
araç listesi; listele gerçek huniler; olmayan huni `isError`; şema dışı
`-32602`. **Resmi SDK istemcisiyle** (`Mcp\Client` + `HttpTransport`, kap
içinden): araçlar, `ai-ornek` oku (8 adım) → başlık değiştir → değişmişken
ESKİ imza → çakışma hatası (Türkçe) → geri al (özgün başlık doğrulandı).
Deney tasarımı hatası: önce "geri al"dan SONRA eski imzayla yazdım — imza
içerik özeti olduğundan eşleşti, çakışma sanılmadan sıra düzeltildi. Geçici
ölçüm anahtarı (id 1) iptal edildi → 401.
**Ölçülmedi:** Claude Code'un kendisiyle bağlantı (kullanıcının yapılandırmasını
değiştirir — kullanıcı panelden anahtar alıp komutu kendisi çalıştırır);
claude.ai (herkese açık adres — A5b).

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 265 çekirdek testi / 2.184 doğrulama.
