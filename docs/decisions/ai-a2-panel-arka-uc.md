# A2 — Panel AI arka ucu (Claude API, sohbet ucu, SSE)

2026-10-27 · plan `PLAN.md` yol haritası; araştırma `ai-asistan-arastirmasi.md`;
araçlar A0 (`ai-a0-arac-katmani.md`). Kullanıcı: API anahtarı yok, boş kalacak.

## Ölçüm (önce)
- PHP SDK: Packagist'te 1.x serisi son 7 günde; iki hafta kuralına uyan en
  yeni **`anthropic-ai/sdk` 0.57.0** (2026-10-09) sabitlendi.
- SDK HTTP için PSR-18 + PSR-17 ister: `symfony/http-client` + `nyholm/psr7`
  zaten üretim bağımlılığı. `RequestOptions::with(transporter:)` ile kendi
  istemcimiz verilebiliyor → testte Symfony'nin sahte HTTP fabrikası.
- SDK kaynağı (belge yerine): beta `create` `fallbacks` (string "default" ya
  da liste) + `betas`; `BetaToolResultBlockParam` `isError`; `outputConfig`
  `effort` (`Effort` enum); hata sınıfları `Core\Exceptions\*`.

## Karar
- `App\Ai\HuniAsistani::calistir(app, huni, istem, olay)`: döngü bizde, tur
  başına AKIŞSIZ `beta->messages->create` (maxTokens 16000 — SDK zaman aşımı
  altında; claude-api rehberi). Araçlar A0, kapsam = açık huni. Taslak +
  imza ilk mesajda (okuma turu harcanmaz). Doğrulama hatası `is_error` ile
  modele döner, model düzeltir. Sınır: `ai.azami_tur` 8.
- Model `claude-opus-5-5`, **efor `medium` açıkça** (Opus 5.5'te düşünme
  kapatılamaz, varsayılan medium; A6'da ölçülerek ayarlanır). Sunucu tarafı
  ret yedeği `fallbacks: 'default'` + `server-side-fallback-2026-07-01`.
  Sistem istemi `cache_control: ephemeral` (araçlar + sistem önbellek öneki;
  yalnız uygulama/huni adı değişir).
- Asistan yanıtı geri giderken SDK *Param* sınıflarıyla kurulur; **düşünme
  blokları imzasıyla AYNEN** (Opus 5.5 "preserved thinking"); beklenmeyen
  blok türü → açık hata (araç sunucusu yok).
- Araç şeması: SDK'nın `InputSchema` tipi JSON Şemasından dar; `$defs`,
  `additionalProperties` olduğu gibi gider (testte istek gövdesinden ÖLÇÜLDÜ:
  SDK camelCase→snake çevirisi şema içine dokunmuyor) → tip `@var` ile.
- Uçlar (sözleşmede): `GET /panel/api/ai/durum` {acik, model};
  `POST /panel/api/ai/huniler/{app}/{huni}/sohbet` {istem ≤4000} →
  `text/event-stream`, olaylar `metin · arac · arac_sonucu · taslak · hata ·
  bitti` (şema `PanelAiOlayi`, data `tur` alanını taşır). Sıra: huni yok 404
  → CSRF (`X-CSRF-Token`, huni-<app>) 403 → **anahtar boş 503 (istek
  gitmez)** → istem 422 → saatlik sınır (`ai_sohbet` 30/sa, yönetici başına)
  429. `X-Accel-Buffering: no`.
- `ai_istekleri` (göç `Version20261027120000`): tur, jetonlar (girdi, çıktı,
  önbellek okuma/yazma), maliyet mikrodolar, sonuç (CHECK), kaydedildi, süre.
  **İstem metni tutulmaz**; ziyaretçi verisi AI'a gitmez (yalnız tanım).
  Maliyet tablosu `App\Ai\Maliyet` (opus-5-5: 4 / 20 / 0,20 / 5 $ MTok).
- Hata: kimlik 401 → "anahtar geçersiz", 429 → "yoğun", diğer durum →
  kodlu, bağlantı → "bağlanılamadı" (en özelden genele).
- **Reddedilen:** SDK tool runner (döngüde tur sınırı, olay ve kayıt bizde
  daha açık) · akışlı (streaming) tur — PHP SDK'da birikmiş son mesaj
  yardımcısı belgede yok; araç girdisi parça parça birleştirilmeli; panele
  tur düzeyinde olay yeterli (ilk sürüm) · `strict: true` araçlar — şemada
  isteğe bağlı alanlar ve desen/uzunluk var, katı mod desteği ÖLÇÜLMEDİ (anahtar
  yok); çekirdek doğrulaması + düzeltme döngüsü zaten var → A6'da anahtarla
  denenir · `tool_choice` zorlama (Opus 5.5'te 400).

## Test
`PanelAiTest` (7): girişsiz yönlendirme; **anahtar boş → durum kapalı, 503,
SahteClaude'a 0 istek, kayıt yok**; ana yol: geçersiz kayıt → `is_error`
+ "yalnız ileri" modele döner → düzeltilmiş kayıt → özet; olay sırası; her
olay `PanelAiOlayi` şemasına göre doğrulanır; taslak değişti, yayınlanmadı;
düşünme bloğu imzasıyla geri gitti; kayıt 3 tur, 3000/600 jeton, maliyet
elle 24.000 mikro$; istek gövdesi (model, efor, yedek + beta başlığı,
sistem önbelleği, araç adları kapsamla, şema anahtarları bozulmamış, ilk
mesajda istem + imza); tur sınırı 8 istek; ret ve 401 açık mesaj, taslak
değişmez; kapsam dışı huni, CSRF 403, boş istem 422, yok huni 404, sınır 429
— reddedilenlerde Claude çağrılmaz.
`Sozlesme::semayaGore` eklendi (SSE verisi için). Doğrulayıcı JSON nesnesini
dizi bekler (stdClass "geçersiz şema" verdi — önce `const` sanıldı, yanlış
tahmin; `required` kodu okunarak bulundu).

Testin kendi hataları: sınırlayıcı deposu önceki koşulardan sayım taşıyordu
(kalan 23 → `consume(29)` hiç tüketmedi) → her testte `cache.rate_limiter`
temizlenir; ayar servisi ilk istekte kurulduğu için kapalı test ayarı ilk
istekten önce değiştirir.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | araç hatası modele hata değil | 🔴 |
| K2 | tur sınırı yok | 🔴 |
| K3 | anahtar boşken istek gider | 🔴 |
| K4 | sistem önbelleği yok | 🔴 |
| K5 | ret yedeği yok | 🔴 |
| K6 | SSE verisinde `tur` yok | 🔴 5 |
| K7 | kapsam açık huni değil | 🔴 2 |
| K8 | maliyet çıktı fiyatı yanlış | 🔴 |
| K9 | saatlik sınır yok | 🔴 |
| K10 | CSRF yok | 🔴 |
| K11 | olaya şema dışı alan | 🔴 (şema doğrulaması devrede) |
| K12 | düşünme bloğu geri gitmez | 🔴 |

## Gerçek
Dev çekirdek: iki uç girişsiz 302 → `/panel/giris`; `HuniAsistani` kapta;
dev `ANTHROPIC_API_KEY` boş → AI kapalı. Dev veritabanına göç uygulandı.
**Ölçülmedi:** gerçek Claude ile tek istek (anahtar yok) — SDK'nın gerçek
API'ye bağlanması, gerçek yanıt süresi/maliyeti, katı mod; anahtar gelince
A6 ile.

## Bağımlılık notu
Composer tarifi `config/packages/http_discovery.yaml` ekledi: PSR-17
arayüzlerini başka fabrikaya bağlıyordu; `nyholm_psr7.yaml` sonra yüklenip
kazanıyor (`debug:container` → Nyholm) → etkisiz, kaldırıldı (SDK keşfi
kabı kullanmıyor). Kaldırınca kontrol yeniden yeşil.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 250 çekirdek testi / 1.976 doğrulama
(ara koşu phpstan 33 hata: SDK param tipleri + testte `mixed` erişim →
Param sınıfları ve test yardımcıları `yol()`/`metin()`).
