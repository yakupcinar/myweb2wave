# Blok: H5 — huni dallanması (B4, A11) — 2026-10-21

## Ne yapıldı
Soru seçeneği ve tanıtım düğmesi isteğe bağlı bir **hedef** taşıyor:
tanımda daha sonraki bir adımın kimliği. Yoksa sıradaki adım. Zincirin her
katmanı:
- **Sözleşme** (`contract/openapi.yaml`): `AdimHedefi`; `TanitimAdimi.hedef`,
  seçenek `hedef`. Tipler yeniden üretildi (huni + düzenleyici).
- **Çekirdek** (`Tanim.php`): hedef var olan ve **daha sonraki** bir adım
  olmalı; kendisi, geriye, olmayan ya da dizge olmayan hedef reddedilir.
- **Oynatıcı** (`Huni.tsx`): `ileri(hedef?)`; geçmişe gerçek sıra yazılır,
  geri tuşu gerçekten gelinen adıma döner; bildirim sırası tanımdaki sıra
  (panel raporu adımı tanım sırasıyla sayıyor — atlanan adım daha az
  ziyaretçi görür, sayım anlamlı kalır).
- **Puck**: tanıtımda ve her seçenekte "Nereye gider (dallanma)" açılır
  listesi; seçenekleri `resolveFields` o adımdan **sonraki** adımların
  kimliklerinden kuruyor (`hedefSecenekleri`). Çeviri kayıpsız (`hedef`
  boş dize ↔ alan yok).

## Neden böyle
Kullanıcı kararı A11 (`docs/pre-setup.md`): seçenek → hedef adım, skor yok.
Rakip modelleri (seçenekten atlama: Landerlab, Airship; görünürlük koşulu:
FunnelFox; skora göre dallanma: Typeform, Interact, Qzzr — Interact
dallanmada geri tuşunu ve ilerleme çubuğunu kapatıyor) karşılaştırılarak
soruldu.

| Karar | Elenen | Neden |
|---|---|---|
| Tanıtım düğmesi de hedef alır | yalnız seçenek | Dallar birleşmeli: bir dalın son adımı tanıtımsa öteki dalın adımlarına düşerdi (test: "dalın sonu ortak adıma atlar") |
| Yalnız ileri | her yöne | Döngü olamaz; doğrulama tek geçiş; oynatıcıda sonsuz huni yok |
| Puck'ta açılır liste (`resolveFields`, `appState`'ten) | serbest metin | Yanlış kimlik yazılamaz; kendisi ve öncekiler listede yok. Sıra sonradan değişip hedef geriye düşerse çekirdek kaydı reddeder |
| Ulaşılamayan adım (hiçbir yol götürmüyor) **hata değil** | reddetmek | Bilerek "kapalı" bırakılmış adım olabilir; karar verilmedi → Ç55 |

## Yan bulgu: üst çubuk durumu (b1) ve tıklama anı denetimi
Tarayıcıda seçenek hedefi değiştirilip **Kaydet**'e basılınca bildirim
"Kaydedildi" dedi ama not 1,5 sn sonra hâlâ "Kaydedilmemiş değişiklik"
gösteriyordu; birkaç saniye sonra kendiliğinden düzeldi (sekme gizli —
zamanlayıcılar kısılıyor, §34). Not `onChange` ile güncellenen duruma bağlı.
**Yayınla** ve **Taslağa geri yükle** artık tıklama anında Puck **deposundan**
de denetliyor (`kaydedilmis`); farklıysa yapılmaz, "önce kaydet" der.

⚠️ **Ölçüm hatası (kayıt için):** ardından "alan değişti, aynı görevde
Yayınla → eski taslak yayınlandı (v3)" gözlemi yaptım ve bir zamanlayıcı turu
bekleyen düzeltme yazdım. Sonra gördüm ki sentetik `input` olayım Puck'a
**hiç ulaşmamıştı** (alanın iki kopyasından görünmeyenine yazmıştım; not
değişmedi, depo değişmedi) — v3 doğru biçimde kayıtlı taslağı yayınlamıştı.
"Depo görünen alanın gerisinde" iddiası ölçülmedi; bekleme düzeltmesi geri
alındı (kural 6a, tuzak §8/§10). Tıklama anı denetimi kaldı ama **testi
yok** (düzenleyicide etkileşim testi altyapısı yok — Ç53 (2)).

## Testler
- `core/tests/Controller/HuniTest.php`: +6 bozuk örnek (seçenek hedefi
  geriye / kendisi / olmayan / dizge değil; tanıtım hedefi kendisi; seçenekte
  bilinmeyen alan) + geçerli dallanma.
- `funnel/src/oynatici/Huni.test.tsx` +2: aile dalı (atla → dal sonu ortak
  adıma; geçmiş `{adim: 4}`; çubuk 5/5; bildirim 0 → 1 → cevap → 2 → 4), iş
  dalı (kendi adımından sıradakine).
- `core/editor/src/donustur.test.ts`: kayıpsız çeviri hedeflerle;
  `hedef.test.ts`: hedef listesi yalnız sonraki adımlar.

## Kırma denemeleri

| # | Deneme | Sonuç |
|---|---|---|
| U1 | hedef kendisi olabilir (`<=` → `<`) | 🔴 PHPUnit 2 |
| U2 | geriye hedef kabul | 🔴 PHPUnit 3 |
| U3 | tanıtım hedefi denetlenmez | 🔴 PHPUnit 1 |
| U4 | seçenekte `hedef` alanı yasak | 🔴 PHPUnit 5 |
| U5 | oynatıcı seçenek hedefini yok sayar | 🔴 1 test (iş dalı — aile dalının hedefi zaten sıradaki, ayırt edemez; iki dal bu yüzden) |
| U6 | oynatıcı tanıtım hedefini yok sayar | 🔴 1 test |
| U7 | Puck → tanım hedefi düşürür | 🔴 1 test |
| U8 | tanım → Puck seçenek hedefini düşürür | 🔴 1 test |
| U9 | hedef listesinde adımın kendisi | 🔴 1 test |

## Ölçüm
- `bin/kontrol` **EXIT=0, 149 sn**; çekirdek **180** (+7), huni 28 (+2),
  düzenleyici 15 (+1), bileşen 19. JS gzip 73.350 B (+44).
- Tarayıcı (Chrome, `b1deneme`): Puck'ta "Ailemle" seçeneğinin listesi
  `— sıradaki adım —, cihaz (Soru), oncelik (Soru), hazir (Tanitim), paywall
  (Paywall)` (önceki adımlar yok); `hazir` seçilip kaydedildi → veritabanında
  yalnız `aile → hazir`. Önizlemede: karsilama → kiminle → **Ailemle** →
  **hazir** (sıra 4; `cihaz`, `oncelik` atlandı; çubuk 5/6) → geri tuşu
  **kiminle**'ye.
- Ortam: önizleme bir kez "Yükleniyor…"da kaldı — konsolda `Failed to fetch
  dynamically imported module …/Huni.tsx`; kontrolün `npm ci`'si paylaşılan
  `node_modules`'u geliştirme sunucusu çalışırken yeniden kurmuştu.
  `docker restart huni-dev` düzeltti (Ç51 (1)).
