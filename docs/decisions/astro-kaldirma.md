# Astro kaldırıldı → Vite çok sayfalı + Preact (2026-11-03)

Kullanıcı 2026-11-03: "Astro'yu kaldır, en iyisi neyse onu kullan." Araştırma ve seçenekler: `astro-kaldirma-arastirmasi.md` (Seçenek 1 uygulandı).

## Ne değişti

- `funnel/` artık düz Vite 8.3.4: üç HTML girişi `keepchat/index.html`, `keepchat/basarili/index.html`, `keepchat/iptal/index.html` (adresler ve `dist` düzeni aynı; Caddy ve çekirdek değişmedi). Giriş betikleri `src/giris/{huni.tsx,basarili.ts,iptal.ts}` — `basarili`/`iptal` betikleri Astro sayfalarından **aynen** taşındı.
- Oynatıcı tek Preact kökü (`render(<Huni app=…/>)`, "Yükleniyor…" HTML'de, kurulunca yerini alır). React adları `preact/compat` takma adıyla; Vitest yapılandırması `vite.config.ts`'e birleşti (tek yerde).
- Erken tanım isteği (H0): `<!-- erken-istek:keepchat -->` işaretini `tanimIstegi`'nin kaynağıyla değiştiren küçük Vite eklentisi (`src/erken-istek-eklentisi.ts`) — Astro'daki `set:html` yerine.
- Ön yükleme: Vite kendisi yazıyor; kendi Astro entegrasyonumuz (`onyukleme.ts`) **denetime** dönüştü (`onyukleme-denetimi.ts` + `scripts/onyukleme-denetimi.ts`, `bin/kontrol funnel-onyukleme`): girişin bütün durağan bağımlılıkları önden yükleniyor mu + erken istek sayfada mı.
- `resolve.dedupe: ["preact"]` — eskiden `@astrojs/preact` ekliyordu; yokken tek-kopya bekçisi derlemeyi durdurdu (ölçüldü).
- Geliştirme sunucusu: `huni-dev` kabı `vite --host` :4321 (aynı imaj, bağlamalar, ağ). **Yalnız geliştirmede** eğik çizgisiz adres yönlendirmesi: Stripe dönüşü `/keepchat/basarili` Vite'ta 404 veriyordu (Astro kabul ediyordu); üretimde Caddy `file_server` zaten yönlendiriyor.
- Araçlar: `astro check` → `tsc --noEmit` (Astro'nun "strictest" seçenekleri aynen), `prettier-plugin-astro` ve `@astrojs/*` kalktı, `@types/node` 24.19.2 doğrudan (eskiden dolaylı), `bin/kontrol` bütçe yolu `dist/assets`.

## Ölçüm

| | Astro | Vite |
|---|---|---|
| JS gzip (`*.js` toplam) | 20.606 B, 9 dosya | **16.850 B**, 5 dosya (−%18) |
| `node_modules` | 340 MB, 329 paket | **135 MB, 111 paket** |
| Ön yükleme | 6 bağlantı (entegrasyon) | girişin bütün bağımlılıkları (3 sayfa "tamam") |
| Ölü bağlantı | 0 | 0 (3 sayfa) |
| Testler | 84 | 87 (+3: erken istek eklentisi; ön yükleme denetimi testleri değişti) |

Tarayıcı (Chrome): geliştirmede uçtan uca karşılama → kiminle → cihaz → öncelik → hazır → ödeme; **üretim derlemesi** (`vite preview`, :4321 — CORS listesi aynı adres): ana JS, iki ön yükleme ve CSS **aynı anda** (43–44 ms), tanım isteği 47 ms'de — JS bitmeden (89 ms) başladı (H0 korunuyor); `iptal` temalı, `basarili` betiğiyle 200.

## Kırma denemeleri (6/6 kırmızı; 3'ü ilk koşuda geçersizdi → düzeltildi)

| # | Bozulan | Sonuç |
|---|---|---|
| V1 | `dedupe` yok | 🔴 bekçi: "ikinci kopya pakete giriyor: …/huni-bilesenleri/node_modules/preact/jsx-runtime" |
| V2 | erken istek eklentisi yok | ilk koşu KOŞULMADI (prettier satırı bölmüştü, §8) → 🔴 sayfada `window.huniTanimi` yok |
| V3 | `modulePreload: false` | 🔴 ön yükleme denetimi |
| V4 | `PUBLIC_` öneki okunmaz | ilk koşu sahte (denetim kabın dışında koştu) → 🔴 çekirdek adresi pakette yok |
| V5 | React takma adı yok | 🔴 testler |
| V6 | eklenti işareti değiştirmez | 🔴 eklenti testi |

İlk test koşusunda 2 serbest yanıt testi kırmızıydı, sonraki koşularda tekrarlanmadı (kurulum sırasında) — `bin/kontrol` koşusunda yeniden ölçüldü.
