# Dışarıya açık MCP sunucusu — araştırma (2026-10-27, B6)

Kullanıcı: "araştırmaya geç; web2wave personal API key veriyor, biz de öyle
yapabiliriz — istediğimiz son ürün web2wave'in SaaS olmayan, tek şirkete ait
hali". Karar B6 (`pre-setup.md`). Kod alınmadı; belgeler okundu.

## Kaynaklar
- claude.com/docs: `connectors/custom/remote-mcp`, `connectors/building/authentication`,
  `connectors/mcp-tunnels/overview`; support makalesi 11175166.
- modelcontextprotocol.io (spec 2025-11-25 ve 2026-07-28).
- `modelcontextprotocol/php-sdk` @ v0.8.1: README, `docs/run/{http,authorization,
  framework-integration,sessions,server-builder}.md`, `docs/servers/tools.md`.
- Packagist sürüm/tarih listeleri.
- web2wave MCP belgesi (`web2wave-platform-yapisi.md` #18): Bearer proje
  anahtarı, claude.ai'de OAuth PKCE (yetki sayfasında anahtar yapıştırılır),
  yıkıcı araçlar barındırılan uçta kapalı.

## Bulgular

### Claude istemcileri nasıl bağlanır
| İstemci | Nereden bağlanır | API anahtarı (Bearer) | Not |
|---|---|---|---|
| **Claude Code** | kullanıcının makinesi | **Evet** — `claude mcp add --transport http … --header "Authorization: Bearer …"` | `localhost` adresi de olur → **yerelde, tünelsiz ölçülebilir** |
| **claude.ai / Desktop / mobil (özel bağlayıcı)** | **Anthropic bulutu** (`160.79.104.0/21`) | Yalnız "Request headers" ile — **beta, sınırlı kuruluşlar**; yoksa **OAuth** | Herkese açık HTTPS adresi şart; Streamable HTTP; Free planda 1 özel bağlayıcı |

claude.ai OAuth gereksinimleri (Claude'un istemcisi spec'ten katı):
`401` + `WWW-Authenticate: Bearer resource_metadata="…"` (200'deki başlık yok
sayılır); korumalı kaynak üst verisi (RFC 9728), `resource` = girilen URL;
`authorization_servers`'ın İLK elemanı; yetki sunucusu üst verisi (RFC 8414);
PKCE S256 zorunlu; istemci kaydı CIMD (`client_id_metadata_document_supported`
+ `none` kimlik yöntemi) ya da DCR (`registration_endpoint`); geri dönüş
`https://claude.ai/api/mcp/auth_callback` ve Claude Code için port bağımsız
`http://localhost/callback` + `127.0.0.1`; `/token` form-urlencoded; yenileme
belirteci döndürülmeli (rotation), geçersizde `invalid_grant`; keşif/kayıt/
token 10 sn, yenileme 30 sn içinde yanıtlanmalı; `client_credentials` yok.

### Herkese açık adres
- **MCP tünelleri** (Anthropic + Cloudflare): yalnız **Enterprise**, araştırma
  önizlemesi → **bize uymaz**.
- Seçenekler: (a) dağıtım (`deploy/` hazır; sunucu + alan adı bekleniyor —
  ertelenmişti) (b) geçici **Cloudflare hızlı tüneli** (`cloudflared tunnel
  --url …` → `*.trycloudflare.com`, hesapsız, adres her açılışta değişir) —
  claude.ai denemesi için; indirme kullanıcı onayıyla.
- Claude Code yerelde adres gerektirmez → ilk ölçüm oradan.

### PHP SDK (`mcp/sdk`)
- Sürümler: 0.9.0 (2026-10-18, 9 gün — kural dışı) · **0.8.1 (2026-08-29) —
  seçilecek** · `symfony/mcp-bundle` 1.0.0-BETA1 (5 gün, `mcp/sdk ^0.9`) →
  elendi. SDK "experimental" (1.0 öncesi).
- `StreamableHttpTransport` PSR-7 istek alır/yanıt döner; Symfony'de PSR
  köprüsüyle denetleyiciden (`symfony/psr-http-message-bridge` bizde
  require-dev → require'a taşınır; `nyholm/psr7` var). İki protokol çağı:
  2025-11-25 (oturumlu, `Mcp-Session-Id`) + 2026-07-28 (oturumsuz).
- Oturum deposu: `Psr16SessionStore` (Symfony önbelleği) ya da dosya.
- Varsayılan ara katman: CORS + **DNS rebinding koruması (yalnız localhost
  Host'u kabul)** → herkese açık adreste izinli Host listesi ayarlanmalı.
- Araçlar: `addTool(handler, name, description, inputSchema…)` → şema
  sözleşmeden (A0) elle verilir.
- Kimlik: SDK yalnız **kaynak sunucusu** (belirteç doğrular, RFC 9728 üst
  verisi, 401 başlığı); **belirteç üretmez** (yetki sunucusu kapsam dışı).
  `AuthorizationTokenValidatorInterface` ile kendi API anahtarı doğrulayıcımız
  (belgede örnek: `ApiKeyValidator`).

## Öneri — adımlar
- **A5a · Kişisel API anahtarları + `/mcp` (Bearer)** — tek şirket, SaaS
  değil: panelde anahtar oluştur/iptal et (bir kez gösterilir, SHA-256 özeti
  saklanır, uygulama kapsamlı, son kullanım zamanı); `/mcp` Streamable HTTP
  (`mcp/sdk` 0.8.1), araçlar A0 (`HuniAraclari`, kapsam anahtarın
  uygulaması), doğrulayıcı anahtarı özetle karşılaştırır, hız sınırı, araç
  çağrısı kaydı. Panel güvenlik duvarı dışında (kendi kimliği). Ölçüm:
  Claude Code + `localhost` + `--header`, gerçek araç çağrısıyla taslak.
- **A5b · Herkese açık adres**: geçici Cloudflare tüneli (onayla) ya da
  dağıtım; claude.ai özel bağlayıcı: "Request headers" hesapta varsa anahtar
  yapıştırılır.
- **A5c · claude.ai için OAuth (gerekirse)**: web2wave gibi — kendi küçük yetki
  sunucumuz: `/authorize` sayfası kişisel anahtar ister (ya da panel girişi),
  kısa ömürlü erişim + döndürülen yenileme belirteci; CIMD + DCR, PKCE S256,
  RFC 9728/8414 üst verisi. SDK üretmediği için ayrı iş (`league/oauth2-server`
  ya da küçük el yazımı — o adımda ölçülerek karar).
- Sonra: MCP **istemleri** (web2wave'deki hazır senaryolar: "brief → huni",
  "huniyi incele") ve önizleme bağlantısı aracı zaten var.

## Açık sorular
- claude.ai hesabında "Request headers" görünüyor mu? (kullanıcı bakar:
  Customize → Connectors → Add custom connector) — A5c gerekip gerekmediğini
  belirler.
- Dağıtım (sunucu + alan adı) ne zaman — kalıcı URL buna bağlı.
