# V3a — Fluent simgeler (2026-11-03)

**İstek:** kullanıcı 2026-11-03: "V3'e simgelerle başla, stok fotoğraf
lisans denetimiyle" (fotoğraf V3b). Rakip araştırması: emoji cihazın kendi
renkleriyle çizilir, temayı izlemez.

## Ölçüm (önce)

Fluent Emoji (microsoft/fluentui-emoji, **MIT — ölçüldü**, commit
`1ffb34c…`): Color SVG 3145 dosya / 132 MB (ort. 42 KB), 3B PNG 3145 /
110 MB, High Contrast SVG 1595 / 6,4 MB (gh api ağaç boyutları). Depoya
koymak ve ekran başına indirmek ikisi de ağır. Çekirdekte GD + WebP var.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Depoda yalnız DİZİN (`config/huni/fluent-simgeler.json`, 1594 simge, 314 KB; `app:simge-dizini <sha>` üretir) | görselleri depoya koymak | 110–132 MB |
| 2 | `GET /simge/{renkli|tekrenk}/{kod}`: ilk istekte sabit commit'ten alınır, 3B PNG → 128 px WebP (ay simgesi ~35 KB → 1,76 KB, ölçüldü), önbellek dizininde saklanır, `immutable` 1 yıl | CDN'e (jsDelivr) doğrudan bağlamak | Ziyaretçinin IP'si üçüncü tarafa gitmez; içerik sabit commit'e bağlı |
| 3 | Tema `simgeler`: cihaz (varsayılan, bugünkü) · renkli (3B) · tekrenk (yüksek kontrast SVG, CSS maskesi, **ana renkte**) | Lucide simgeleri | Emoji → Lucide eşlemesi yok; tek renk Fluent temayı izliyor ve yazar/AI aynı emoji sözcüğünü kullanmaya devam ediyor |
| 4 | Görsel yoksa (bayrak, ten tonu, ağ hatası, 404) cihaz emojisi — img `onError`; maske hata vermediği için aynı adresi gizli img yoklar | boş kare | Huni hiçbir durumda simgesiz kalmaz |
| 5 | SVG yalnız betik/olay/dış başvurusuz ise sunulur; `CSP default-src 'none'`, `nosniff`; maske CORS ister → `*` (herkese açık varlık) | sabit commit'e güvenmek | Savunma derinliği; doğrudan açılan SVG betik çalıştıramaz |
| 6 | Anahtar kuralı (kod noktaları, FE0F atılır) PHP ve TS'de; ortak örnek dosyası, beklenenler Unicode'dan elle (§3) | tek dilde | İki taraf ayrışırsa simge sessizce cihaza düşerdi |
| 7 | Bazı hazır temalar simge biçimi taşır (gece/şeker/enerji/okyanus renkli; kod/lacivert/sade tekrenk) | — | Seçici tek tıkla göstersin |

MIT bildirimi: `config/huni/fluent-simgeler.LICENSE` (sabit commit'ten).

## Ölçümler

- Gerçek GitHub'dan: `renkli/1f319.webp` 200 image/webp 1760 B; `tekrenk/1f319.svg` 200 2803 B; bilinmeyen (🇹🇷) ve yanlış uzantı 404; başlıklar `immutable`, `ACAO *`.
- uyku-ornek'in 27 simgesinin 27'si dizinde.
- Gerçek Chrome: renkli ve tekrenk ekranları çekildi, uyarı yok. **Hata bulundu:** tekrenk simgeler siyahtı — `.simge img { display:block }` yoklama img'sinin `[hidden]`'ını ezdi (tuzak §46); düzeltildi, yeniden çekimde ana renkte.
- Panel önizlemesi (gerçek oturum): 27 img'nin 27'si yüklü, göreli adres.
- `bin/kontrol` YEŞİL: 470 çekirdek testi / 3776 iddia.

## Kırma denemeleri (11/11 kırmızı)

S1 PHP FE0F kuralı · S2 TS FE0F kuralı · S3 önbellek okuma · S4 betikli SVG
reddi · S5 küçültme · S6 CORS · S7 stil–uzantı eşleşmesi · S8 renkli
`onError` dönüşü · S9 `[hidden]` CSS kuralı · S10 düzenleyici eşlemesi ·
S11 önbelleğe yazma.
