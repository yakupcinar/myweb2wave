# M7c1 — MCP rapor araçları ve olay akışı API'si (2026-11-09)

Karar `pre-setup.md` A21 (kullanıcı: okuma + yazma; yazma yalnız izinli anahtar → M7c2).

## Ne yapıldı

- **MCP'de 6 salt-okur araç** (`App\Mcp\RaporAraclari`, `readOnlyHint`): `rapor_ozet` (sayımlar, izin, günlük), `rapor_kaynaklar`,
  `rapor_kohort`, `rapor_kampanyalar` (Meta ROAS), `rapor_cevaplar`, `deney_sonuclari` (`ad` verilirse tek deney). Hepsi
  **anahtarın uygulamasıyla sınırlı**. Satır listesi ve serbest yanıt yok: kişisel veri MCP'ye gitmiyor. Sunucu talimatında
  "tutarlar para birimi başına, toplama" notu var.
- **Rapor'a uygulama süzgeci:** `sayimlar`, `izinler`, `gunluk`, `utm`, `cevaplar`, `kampanyalar`, `kohort` → `?string $app = null`.
  Panel null ile çağırıyor (değişmedi, tüm uygulamalar). Meta harcaması reklam hesabı düzeyinde ve uygulamaya bölünmüyor.
  Ziyaret ve gelir bölünüyor.
- **`GET /apps/{app}/olaylar?imlec=&sinir=`** (uygulama anahtarı, sözleşmede, en çok 500): `Olaylar::akis`. İmleç her yanıtta
  son olayın yeri, `devam` ayrı. Boş veri JSON nesnesi olarak dönüyor. Bozuk imleçte 422.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Araçlar yalnız toplu veri | satırlar (maskeli e-posta) | MCP istemcisine kişisel veri gitmesin |
| 2 | Süzgeç Rapor'un kendisinde (isteğe bağlı) | MCP için ayrı sorgular | Panel ve MCP aynı hesabı yapsın (kural 7) |
| 3 | Harcama bölünmez | uygulamaya oranlamak | Meta hesabı uygulama bilmiyor; oran uydurma olurdu |
| 4 | Şema dışı girdi SDK'da reddedilir (JSON-RPC -32602) | araçta denetim | Araç çalışmadan; araçta da `gun` ikinci kez denetleniyor |

## Test

- `McpTest` +1 (araç listesi güncellendi): iki uygulamalı veri, `keepchat` anahtarıyla her araç yalnız kendi uygulamasını sayıyor
  (sayım, izin, günlük, kaynak geliri, kohort ziyareti **ve geliri**, cevap, kampanya ziyareti + ROAS 0,5 = 20 / 40, harcama bölünmüyor),
  salt-okur işareti var, bilinmeyen deney `isError`, şema dışı aralık -32602.
- `OlayAkisiTest` (2): sayfalama (`devam`), başka uygulama yok, son sayfadan sonra yeni olay aynı imleçle geliyor (Ç119), boş veri `{}`,
  401, bozuk imleç 422 (sözleşme).

## Kırma (12/12 🔴)

R1 sayım · R2 izin · R3 günlük · R4 kaynak · R5 kohort ziyareti · **R5b kohort geliri: ilk koşu 🟢**. Test yalnız ziyaret sayısına
bakıyordu, taze ziyaretin ilk 24 saati de dolmamıştı → ziyaretler 2 gün önce, gelir ölçüldü → 🔴 · R6 cevap · R7 kampanya ·
R8 MCP uygulamasız çağırır · R9 salt-okur işareti yok · R10 akış yetkisiz · R12 veri nesne değil.

## Gerçek sistem (2026-11-09, dev)

- `GET /apps/keepchat/olaylar?sinir=3` (dev uygulama anahtarı) → 3 olay, `devam=true`, imleç var.
- Geçici kişisel API anahtarıyla gerçek HTTP MCP: `initialize` → `tools/call rapor_ozet` → reklam 23, organik 93 ziyaret. Paneldeki
  rapor servisinin aynı veride verdiğiyle aynı. Anahtar sonra iptal edildi. Anahtar değerleri çıktıya yazılmadı.
