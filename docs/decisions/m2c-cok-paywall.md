# M2c — birden çok paywall, cevaba göre paywall (2026-11-04)

Karar `pre-setup.md` A15 ("cevaba göre paywall = hunide birden çok paywall adımı +
mevcut koşullu yönlendirme; ayrı paywall varlığı reddedildi") · önceki: `m2a`, `m2b`.

## Ölçüm (önce)

- Tanım kuralı "paywall tam bir kez ve EN SONDA" (`Tanim.php`). Tek varsayım bu.
- Oynatıcı paywall'ı zaten uç adım sayıyor (paywall'dan "ileri" yok); gösterim
  koşuluyla ekran atlayan döngü son adımın koşulsuz paywall olmasına dayanıyor.
- Düzenleyicinin ulaşılabilirlik denetimi (`akis.ts`) paywall'da duruyor — çok paywall'ı
  zaten doğru sayıyor.
- İlerleme çubuğu `(sıra + 1) / adım sayısı` — öndeki paywall'da dolu değil.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | "Tam bir kez" kalktı; **son adım paywall olmalı** | kuralı tümden kaldırmak | Gösterim koşuluyla atlama döngüsü sonda koşulsuz bir adımda durmalı; yoksa huni boş bir sıraya düşerdi |
| 2 | Öndeki paywall'a seçenek hedefi ya da koşullu yönlendirmeyle gidilir; paywall'dan sonraki adıma sıradan geçilmez | yeni "paywall seçimi" alanı | T3'ün mevcut araçları yetiyor; düzenleyici yönlendirilmeyen paywall'ı "ulaşılamaz" diye zaten uyarıyor |
| 3 | Her paywall'da ilerleme çubuğu **dolu** | sıraya göre | Ziyaretçi için her paywall huninin sonu |
| 4 | Hangi paywall'dan ödendiği ayrıca kaydedilmiyor | ödeme oturumuna paywall kimliği | Adım olayları (T4) ziyaretçinin hangi paywall'a vardığını zaten taşıyor; rapor M5'te → Ç112 |

## Test

`CokPaywallTest` (2): seçenek hedefiyle ve koşullu yönlendirmeyle iki paywall geçerli;
son adım ekran → "son adım paywall olmalı". `HuniTest`/`PanelHuniTest` hata metni
güncellendi. Oynatıcı: aile → sondaki yıllık paywall, iş → öndeki aylık paywall; ikisinde
de o paywall'ın planı ödemeye gider, çubuk 4/4. Düzenleyici: yönlendirilmeyen sondaki
paywall ulaşılamaz, yönlendirilen ulaşılır.

## Kırma (4 deneme, hepsi kırmızı)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | eski kural (tam bir kez, sonda) | 🔴 |
| K2 | son adım kuralı yok | 🔴 4 |
| K3 | öndeki paywall'da çubuk dolmaz | 🔴 |
| K4 | düzenleyici paywall'dan sıradakine geçer sayar | 🔴 |

## Gerçek istek

- AI aracıyla (`taslak_kaydet`, gerçek doğrulama) fitness şablonuna ikinci paywall
  `pw-kas` (yıllık, son adım) + özet ekranına koşullu yönlendirme `cevap.hedef icerir kas`.
- Chrome, önizleme, her adım betikle cevaplandı (16 adım): "Kas kazanmak" →
  **"Kas planına başla" €59,99 / yıl**, çubuk 16/16; "Kilo vermek" → öndeki
  **"Kişisel planına başla"** (yıllık + aylık seçici), çubuk 16/16.
- İlk deneme "Önizleme açılamadı": 1 saatlik önizleme bağlantısının süresi kullanım
  arası beklerken dolmuştu (doğru davranış); yeni bağlantıyla yürüdü.

## Kontrol

`bin/kontrol` **YEŞİL**: 561 çekirdek testi / 4751 doğrulama.

## Açık

Ç112.
