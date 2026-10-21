# Blok: ada modüllerini önden yükleme (Ç52 (1)) — 2026-10-21

## Ne yapıldı
`funnel/src/onyukleme.ts` küçük bir Astro entegrasyonu. Derleme bitince her
HTML sayfasındaki adanın modüllerini (`component-url`, `renderer-url`) ve
onların durağan içe aktarmalarını (özyinelemeli) bulup `</head>`'ten önce
`<link rel="modulepreload">` yazıyor. Anket sayfası 4 bağlantı alıyor
(`Huni`, `client`, `react`, `cekirdek`); adası olmayan başarı sayfası
değişmiyor.

## Neden
H2 ölçümü: `react.js` + `cekirdek.js`, `Huni.js`/`client.js` indikten ve
çözümlendikten sonra isteniyordu — ikinci sıralı JS turu, mobilde bir
gidiş-dönüş daha. Elenenler:
- **Parçaları birleştirmek** (`manualChunks`): `react` parçası ada ile
  renderer arasında, `cekirdek` başarı sayfasıyla paylaşılıyor; birleştirmek
  ya kopya ya da yine paylaşılan bir parça demek, tur kalmaz diye garanti yok.
- **Elle `<link>`**: dosya adları içerik özetli, her derlemede değişir.
- Dinamik içe aktarmalar bilerek önden yüklenmiyor (yalnız `from"./x.js"` ve
  `import"./x.js"`); bugün adada dinamik içe aktarma yok.

## Testler
- `funnel/src/onyukleme.test.ts` (2): derleme çıktısının biçiminde örnek
  dosyalarla — iki modül adı, durağan + yan etkili içe aktarma, iki seviye
  derinlik, adasız sayfa değişmez.
- `bin/kontrol` `funnel-onyukleme`: derlenmiş anket sayfasında
  `modulepreload` var mı (entegrasyon gerçekten koştu mu).

## Kırma denemeleri

| # | Deneme | Sonuç |
|---|---|---|
| R1 | entegrasyon `astro.config`'ten çıkarıldı | 🔴 `funnel-onyukleme` adımı (çıkış 1) |
| R2 | yalnız `from` içe aktarması (yan etkili `import"./x"` görülmez) | 🔴 2 test |
| R3 | özyineleme yok (yalnız ilk modül) | 🔴 2 test |
| R4 | `renderer-url` okunmaz | 🔴 2 test |

## Ölçüm (Chrome, yayın derlemesi `python3 -m http.server` `:4321`, sekme arka planda)
| | JS dosyaları | tanım | ilk adım bildirimi |
|---|---|---|---|
| H0 (düz TS) | 44→94, `cekirdek` 95→96 (zincir) | 102→212 | 230 |
| H2 (önyüklemesiz, soğuk) | `Huni`/`client` 187→248, **`react`/`cekirdek` 724→733** | 174→382 | 864 |
| Bu blok (soğuk çekirdek) | **dördü 18→46**, `initiatorType=other` | 22→1396 (Symfony dev önbelleği soğuk) | 1405 |
| Bu blok (ısınmış) | **dördü 14→36** | 18→142 | **142** |

İçerik artık yalnız tanımı bekliyor (bildirim tanım biter bitmez). Sekme
arka planda olduğundan mutlak süreler kaba (Ç52 (2)); sıralama kesin.
`bin/kontrol` **EXIT=0, 143 sn**; huni 24 test (+2), JS gzip 73.283 B (değişmedi).
