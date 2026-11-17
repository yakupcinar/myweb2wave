# A0 — AI araç katmanı (HuniAraclari)

2026-10-27 · plan `PLAN.md` yol haritası; araştırma `ai-asistan-arastirmasi.md`.
Kullanıcı: "A0 ile başlayalım; API anahtarı yok, boş kalacak" → A0/A1
anahtarsız çalışır (yalnız çekirdek); anahtar ilk kez A2'de gerekir,
boşsa A2 "AI kapalı" der.

## Ölçüm (önce)
- Üretim imajında sözleşme YOK (`core/Dockerfile` yalnız `core/`); geliştirmede
  çekirdek kabına `contract/openapi.yaml` salt okunur bağlı (compose).
- Tam tanım doğrulaması (biçim + plan + görselin bu uygulamaya ait olması)
  `Huniler::hatalar` — `private`'tı; `Tanim::hatalar` görsel sahipliğini
  bilmez → araç onu kullansa kural ailesi bölünürdü.

## Karar
- `App\Huni\Arac\HuniSemasi` + `app:huni-semasi`: sözleşmedeki `HuniTanimi`
  ve erişilebilen bileşenler → kendi kendine yeten JSON Şeması (`$defs`,
  `#/components/schemas/` → `#/$defs/`), `core/config/huni/tanim-semasi.json`
  depoda; `bin/kontrol` `huni-sema` adımı `--kontrol` ile güncelliğini ölçer
  (TypeScript tipleriyle aynı düzen). 11 bileşen.
- `HuniAraclari::tanimlar(kapsam)` (Claude `tools` / MCP `tools/list`
  biçimi) + `calistir(ad, girdi, kapsam)` → `AracSonucu{icerik, hata}`.
  Araçlar: `huniler_listele`, `huni_oku` (tanım + imza), `tanim_dogrula`
  (kaydetmeden, tam doğrulama), `taslak_kaydet` (imza ZORUNLU),
  `onizleme_adresi`, `yeni_huni` (yalnız huniye bağlı olmayan kapsamda).
- **Yalnız taslak:** yayınla / sil / kural aracı yok.
- `AracKapsami{app, huni?}`: uygulama girdide YOK (yapı gereği başka
  uygulamaya erişilemez); huni verilmişse başka huniye dokunulamaz.
- Girdi modelden gelir → güvenilmez: nesne mi, bilinmeyen/eksik alan, huni
  adı deseni, imza deseni (64 hex) çekirdekten önce denetlenir.
- `Huniler::hatalar` public (aynı aile).
- **Reddedilen:** şemayı çalışma anında `/contract`'tan okumak (üretimde yok)
  · imzayı isteğe bağlı bırakmak (null = çekirdekte "denetleme" → panelde
  biri değiştirdiyse AI ezer) · uygulamayı araç girdisi yapmak (kapsam
  denetimine bağlı kalırdı; yapı daha güçlü).

## Test (`tests/Huni/HuniAraclariTest.php`, 8 test)
Araç listesi kapsamla (yayın aracı yok); şema = sözleşmedeki HuniTanimi
(YAML bağımsız okunur) ve her `$ref` çözülür; şema dosyası güncel (komut);
promptla yazılmış dallanmalı fitness hunisi oku → kaydet → aynı tanım,
yayınlanmadı; geçersiz tanım yazılmaz, `tanim_dogrula` ile aynı Türkçe
hatalar, taslak imzası değişmez; eski imza paneldeki değişikliği ezmez
(CAKISMA), `imza: null` reddedilir; kapsam dışı huni, fazla alan, kapsam
dışı araç, `yayinla`, bozuk ad, eksik alan; yeni huni + liste + önizleme
anahtarı geçerli. Servis henüz tüketicisiz (A1/A2) → kap derlemede çıkarıyor;
test aynı bağımlılıklarla kurar.

Testin kendi hatası: anahtar sırası — JSONB (tuzak §28) → `assertEquals`.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | imza denetimi yok | ilk koşu 🟢 — **test ölçmüyordu** (`''` çekirdekte zaten çakışma); test `null`'a çevrildi → 🔴 |
| K2 | kapsam huni denetimi yok | 🔴 |
| K3 | fazla alan kabul | 🔴 |
| K4 | `yayinla` aracı eklenir | 🔴 |
| K5 | doğrulama hep geçerli | 🔴 |
| K6 | şema dosyası eskimiş | 🔴 2 |

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 240 çekirdek testi / 1.746 doğrulama
(ara koşu phpstan: testte `mixed` erişimleri).
