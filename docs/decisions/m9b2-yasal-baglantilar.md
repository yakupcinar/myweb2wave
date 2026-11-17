# M9b2 — huni yasal sayfalara bağlanır (2026-11-10)

Karar `pre-setup.md` A23 · önceki `m9b1-yasal-metinler.md`.

## Ne yapıldı

- `GET /huni/{app}` ve önizleme (`/huni/{app}/taslak`) yanıtında yeni `yasal` alanı: **yalnız yayındaki** (kaydedilmiş)
  belgelerin mutlak adresi, huninin dilinde (`tr`). Hiç yoksa alan yok. Sözleşme `HuniYayini.yasal`.
- Huni: tanım yüklenince paywall adımında `sartlar` / `gizlilik` yoksa uygulamanın sayfası yerleşir (`yasalAdresleri`).
  Adımın kendi adresi önce gelir. Paywall alt bağlantıları, şart onayı kutusu ve downsell'deki şart onayı aynı adresi görür.
- İzin şeridi: "Çerez ve izleme bildirimi" ve "Gizlilik politikası" bağlantıları (varsa).

## Ölçüm (önce)

- Paywall bileşeni bağlantıyı yalnız adımdaki adresten çiziyordu; adres yoksa bağlantı yoktu.
- İzin şeridinde hiç bağlantı yoktu.
- Huni sayfası `lang="tr"`.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Geri dönüş tanım yüklenirken, tek yerde | paywall bileşeninde | Paywall, şart onayı ve downsell aynı adresi görsün; panel önizlemesi (Puck) değişmesin |
| 2 | Yalnız yayındaki belgeler | her zaman adres | Kaydedilmemiş belge 404; kırık bağlantı verilmesin |
| 3 | Dil `tr` sabit | ziyaretçi dili | Huni metinleri tek dilde; dile göre seçim M9d / M11 |
| 4 | `iptal` geri dönüş almaz | şartlar adresi | Ayrı iptal belgesi yok; aynı bağlantı iki adla görünmesin |

## Test

- `HuniTest` +1: yayında belge yokken alan yok; `sartlar/tr` + `cerez/tr` + `gizlilik/en` → yalnız tr olanlar,
  sözleşmeye uygun; önizleme de aynı.
- Huni `Huni.test.tsx` +3: adres yoksa uygulamanınki (şart onayı dahil), adımınki önce; yayında yoksa bağlantı yok;
  izin şeridi bağlantıları var / yok.

## Kırma (7/7 🔴)

F1 geri dönüş uygulanmaz · F2 öncelik ters · F3 şeride çerez adresi gitmez · F4 kaydedilmemiş belge de verilir ·
F5 dil süzülmez · F6 önizlemede alan yok · F7 boşken de alan var — hepsi 🔴. F4, PHPStan düzeltmesinden sonra desen
eşleşmediği için önce KOŞULMADI (§8), yeni desenle 🔴.

## Gerçek sistem (2026-11-10, dev)

- `curl /huni/keepchat`: önce `yasal` yok.
- Geçici `cerez/tr` + `sartlar/en` satırıyla yalnız `{"cerez": "http://localhost:8081/yasal/keepchat/cerez/tr"}`;
  satırlar silinince yine yok.
- Tarayıcıda görülmedi (Chrome uzantısı bağlı değil; önizlemede izin şeridi gizli).
- `bin/kontrol` YEŞİL, core 723 test.
