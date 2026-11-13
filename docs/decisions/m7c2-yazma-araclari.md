# M7c2 — MCP yazma araçları, yalnız izinli anahtar (2026-11-10)

Karar `pre-setup.md` A21 · önceki `m7c1-okuma-araclari.md`.

## Ne yapıldı

- **Anahtar izni:** `api_anahtarlari.yonetim` (göç `Version20261109235000`, varsayılan false; var olan anahtarlar izinsiz).
  Panel "API anahtarları"nda oluştururken "Abonelik ve deney yönetimi" kutusu var (varsayılan kapalı); listede "yönetim" rozeti
  görünüyor. Uç yalnız `yonetim: true`'yu (boolean) izin sayıyor.
- **MCP araçları** (`App\Mcp\YonetimAraclari`) yalnız izinli anahtarın oturumunda kuruluyor. İzinsiz anahtar araçları listede görmüyor,
  çağıramıyor:
  - `abonelikler_listele` (salt-okur; maskeli e-posta, iptal planı, dönem sonu, son fatura; yalnız bu uygulama —
    `Kullanicilar::liste`'ye uygulama süzgeci eklendi)
  - `abonelik_iptal_planla` (yıkıcı), `abonelik_iptal_geri_al`, `abonelik_son_fatura_iade` (yıkıcı) — panelle aynı
    `AbonelikYonetimi`. Başka uygulamanın aboneliği "bilinmeyen abonelik" sayılıyor ve Stripe'a gidilmiyor.
  - `deney_kaydet` (taslak), `deney_durum` (yıkıcı; `bitti` geri alınamaz) — `Deneyler` servisi, hata metni modele dönüyor.
  Her çağrı `mcp_cagrilari`'na yazılıyor.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Yetki yapıyla: araç izinsiz oturumda hiç kurulmaz | yalnız `destructiveHint` / istemci onayı | İstemci onayı istemcinin elinde; iade gerçek para (kullanıcı kararı) |
| 2 | İzin yalnız boolean `true` | doğruluk değeri ("true" metni) | Yanlışlıkla izin verilmesin |
| 3 | Başka uygulamanın aboneliği "yok" | "yetkisiz" | Varlığı da sızmasın |
| 4 | Panelle aynı servisler | MCP'ye özel kod | Kural tek yerde (dönem sonu, kalan tutar, geçişler) |

## Test

`McpTest` +3: izinsiz anahtar yazma araçlarını görmüyor ve çağıramıyor; izinli anahtar 6 aracı görüyor, iade ve iptal yıkıcı, listeleme salt-okur.
Abonelik: liste yalnız bu uygulama ve maskeli. İptal planlanıyor (Stripe'a giden değer **ve** yazılan durum ölçülüyor). Başka uygulamanın
aboneliğinde iptal ve iade hata veriyor, Stripe'a istek çıkmıyor. Faturasız iade hata veriyor, çağrı kaydediliyor. Deney: taslak kaydediliyor,
payı 100 olmayan reddediliyor, olmayan deney hata veriyor, yayında olmayan huni hatası metniyle dönüyor, yayınlanınca aktif oluyor.
`PanelAnahtarTest` +1 (izin yalnız `true`, listede). Panel `Anahtarlar.test.tsx` +1 (varsayılan kapalı, işaretlenince gövdede, rozet).

## Kırma (12/12 🔴)

Y1 izinsiz oturumda da araçlar (2) · Y2 izin aktarılmaz (2) · Y3 izin gevşek · Y4 başka uygulamanın aboneliği · Y5 liste uygulamasız ·
Y6 `Kullanicilar` süzgeci yok · Y7 yıkıcı işareti yok · **Y8 iptal ters: ilk koşu 🟢**. Sahte Stripe yanıtı hep `true` döndüğü ve panel
Stripe'ın yanıtını yazdığı için yazılan durum gönderilen değeri ölçmüyordu → istek parametresi ölçüldü → 🔴 ·
Y9 deney hatası yutulur · Y10 e-posta çıkarılırsa · Y11 doğrulama izni okumaz (3) · Y12 panel varsayılanı açık.

## Gerçek sistem (2026-11-10, dev)

Geçici iki anahtarla gerçek HTTP MCP: izinsiz → 13 araç, yazma aracı yok. İzinli → 19 araç, 6 yazma aracı var.
`abonelikler_listele` 7 abonelik döndü, açık e-posta yok. Yıkıcı araç dev'de çağrılmadı (aynı servis M6a'da gerçek test modunda ölçüldü).
Anahtarlar iptal edildi. **Panel anahtar ekranı tarayıcıda açılmadı** (giriş kullanıcıda).
