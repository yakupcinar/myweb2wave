# Blok: E2.4a — sürükle-bırak huni düzenleyicisi (Puck) — 2026-10-21

## Ne yapıldı
Panelde `/panel/huniler/{app}/{ad}/surukle`: Puck (MIT) ile sürükle-bırak
düzenleyici. Bloklar: Tanıtım (kapak görseli), Soru (seçenekler + görsel),
Ödeme; kök alanlar: sayfa başlığı ve tema (renk seçiciler, yazı tipi, köşe).
Önizleme huni sayfasının görünümünü (tema, görseller) taklit eder; okunmayan
temada uyarı. "Publish" taslağı çekirdeğin MEVCUT ucuna yollar
(`panel_huni_taslak`) — çekirdek, sözleşme ve doğrulama değişmedi; Puck
verisi ↔ huni tanımı çevirisi düzenleyicide (`core/editor/src/donustur.ts`).
Düzenleyici ayrı paket: `core/editor/` (React 19.3.0, Vite 8.3.3, Vitest
5.0.3, TypeScript 6.0.3); tipler sözleşmeden üretilir (R2,
`contract npm run uret` iki yere yazar, `bin/kontrol` ikisini karşılaştırır).
Çıktı `public/varliklar/editor/` (içerik özetli adlar + Vite manifesti;
sayfa manifestten okur — tuzak §30). `bin/kontrol`: editor-npm, -bicim,
-tip, -test, -derle (çekirdek testinden önce). Üretim imajında Node derleme
aşaması; imaja yalnız çıktı girer. Klasik düzenleyici yerinde (bağlantıyla).

## Neden böyle
- **A7/E2:** sürükle-bırak hedefti; Puck MIT, veri düz JSON, bileşenleri biz
  tanımlarız (araştırma `huni-editoru-arastirmasi.md`).
- **Çekirdek değişmedi:** doğrulama tek yerde kalır; düzenleyici yalnız
  giriş kolaylığı. Çeviri kayıpsız olmalı → birim testi.
- **Klasik düzenleyici şimdilik kalır:** Puck sayfası gerçek kullanımda
  kanıtlanana kadar yedek (Ç45).
- **Sürümler:** iki haftadan yeni sürüm alınmadı (tedarik zinciri). React'in
  14 Ekim'de tüm hatlarda (19.0–19.3) eşzamanlı yaması var; nedeni
  bulunamadı — geçmiş örnekler sunucu bileşeni (RSC) açıklarıydı, düzenleyici
  yalnız tarayıcıda. 19.3.0 seçildi, iki hafta dolunca 19.3.x (Ç45).

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| P1 | çeviri kapak görselini düşürüyor | 🔴 vitest 1 |
| P2 | çeviri temayı her zaman ekliyor (uyduruyor) | 🔴 vitest 2 |
| P3 | adım kimliği yerine Puck kimliği yazılıyor | 🔴 vitest 3 |
| P4 | manifest girişi yanlış okunuyor | 🔴 sayfa testi |
| P5 | taslak özniteliğe `|raw` | 🔴 sayfa testi (öznitelik kırıldı, hata) |
| P6 | düzenleyicinin sözleşme tipleri kaydı | 🔴 `sozlesme-guncel` (`cmp` farkı yakaladı) |

## Ölçüm
- `bin/kontrol` YEŞİL: çekirdek 155 test + düzenleyici 5 test; derleme
  686 KB (gzip 193 KB) — yalnız panel.
- Üretim imajı: manifest + 16 dosya; `/app/editor` (kaynak) imajda yok.
- **Tarayıcı:** fitness hunisi Puck'ta tema ve görsellerle açıldı; Bloklar'dan
  "Tanıtım" önizlemenin en üstüne **sürüklendi**, kimlik/başlık değiştirildi,
  Publish → "Kaydedildi"; DB taslağı `[fitness-giris, karsilama, …, paywall]`,
  tema ve iki görsel korunmuş. Kimlik `karsilama` yapılınca çekirdeğin
  "adım 2: kimlik 'karsilama' tekrar ediyor" hatası düzenleyicide göründü.
  Deneme taslağı sonra v3'e geri alındı.
- **Bulgular:** (1) biçimlendiriciyi `src/` üzerinde koşunca ÜRETİLEN
  `sozlesme.ts` de biçimlendi → `sozlesme-guncel` kırmızı; `.prettierignore`
  (huni tarafındaki gibi). (2) Puck özel alanın etiketini kendisi çizmiyor —
  renk alanları etiketsizdi (tarayıcıda görüldü) → `FieldLabel` ile sarıldı,
  yeni derleme önbellek sorunu olmadan geldi.

## Açık
Ç45 (React 19.3.x yükseltmesi; "Publish" düğmesi = kaydet, Türkçe değil;
klasik düzenleyiciyi kaldırma kararı; var olan adımları önizlemede
yeniden sıralamak tarayıcıda denenmedi — yalnız yeni blok ekleme; paket
boyutu) · E2.4b yeni bileşen türleri.
