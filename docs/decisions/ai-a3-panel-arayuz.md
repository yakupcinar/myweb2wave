# A3 — Düzenleyicide AI sohbeti (Puck eklentisi)

2026-10-27 · plan `PLAN.md` yol haritası; arka uç A2 (`ai-a2-panel-arka-uc.md`).

## Ölçüm (önce)
- Puck 0.23 eklenti tipi `{name, label, icon, render}` (tipler okundu):
  sol çubukta sekme, panel tüm eklentiler için bağlı kalır, yalnız etkin
  olan görünür.
- Düzenleyici sunucudan gelen taslağı zaten yeniden yükleyebiliyor
  (sürüm geri yükleme: `setIlk` + `key` ile Puck'ı baştan açar) — bu yol
  eklenti panelini de sıfırlardı.

## Karar
- `AiSohbeti.tsx` Puck'tan bağımsız (veriyi al / koy / kayıtlı mı dışarıdan)
  → tek başına test edilir; `AiEklentisi` (main.tsx) Puck deposuna bağlar.
  Yeni taslak tuvale **`setData`** ile — Puck yeniden açılmaz, sohbet kalır.
- Taslak, A2'nin `taslak` olayında **tanımıyla** gelir (sözleşme
  `PanelAiOlayi` güncellendi) → ek uç ve ek istek yok.
- Kaydedilmemiş değişiklik varken istek GİTMEZ ("önce kaydet"): AI kayıtlı
  taslakla çalışır, panelde yazılmamış iş ezilmesin.
- "Bu değişikliği geri al": istekten önceki veri tuvale + mevcut Kaydet yolu
  (imza zorunluluğu korunur). Eklenti nesnesi bir kez kurulur; düzenleyicinin
  güncel durumuna `ref` ile ulaşır (yoksa AI'dan sonraki imza eski kalır →
  çekirdek çakışma der — tarayıcıda ölçüldü, K6).
- SSE okuyucu (`ai.ts`): tampon "\n\n"e kadar bekler, UTF-8 akış çözücüsü,
  olaylar akış SÜRERKEN bildirilir. Tipler sözleşmeden.
- AI kapalıysa panel nedenini ve nasıl açılacağını söyler, form yok.
- Görünüm: `huni-surukle.html.twig` stil bloğuna `.ai-*` (düzenleyici
  sayfasının mevcut üslubu; Tailwind panel uygulamasında).
- **Ertelendi:** Huniler sayfasında "AI ile yeni huni" — sayfa hâlâ Twig;
  P2 (Huniler React) ile, A0'daki `yeni_huni` aracı + kapsamsız sohbet ucu.
- **Reddedilen:** `key` ile yeniden açma (sohbet ve panel seçimi sıfırlanır) ·
  `findLastIndex` için TS kitaplığını ES2023'e yükseltmek (tek kullanım; döngü).

## Test (`AiSohbeti.test.tsx`, 6)
SSE: olay ve UTF-8 baytları 1/3/5/64 baytlık parçalarda bölünse de doğru;
olay akış bitmeden bildirilir. Bileşen: kapalı → neden + form yok, tek istek;
kaydedilmemiş → istek gitmez; akış → istek başlığı/gövdesi, `taslakGeldi(tanim,
imza)`, satırlar (istem, hata→düzeltiliyor, ✓, metin, özet + maliyet), geri
al önceki veriyle; 429 → neden, geri al yok.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | kayıtlı kontrolü yok | 🔴 |
| K2 | taslak tuvale gelmez | 🔴 |
| K3a | olaylar akış sonuna bekletilir | ilk deneme 🟢 — **test canlı teslimi ölçmüyordu**; test eklendi → 🔴 |
| K3b | tampon yok (parça parça) | 🔴 2 |
| K3c | UTF-8 akış çözücüsü yok | 🔴 2 |
| K4 | geri al kaydedilmeden de çıkar | 🔴 |
| K5 | CSRF başlığı yok | 🔴 |
| K6 | `ref` güncellenmez (tarayıcı) | 🔴 geri al eski imza `ilk-imza` yolladı |

## Gerçek (tarayıcı, derlenmiş düzenleyici)
Panel şifresi yok → düzenek: derlenmiş `main.tsx` aynen, sunucu yanıtları
sahte (`fetch` saplaması: AI durumu, 40 baytlık parçalarla SSE, kaydet,
medya). 1280 px çerçevede: sol çubukta ✦ AI; panel açık kalarak akış: 5 satır,
tuval AI taslağıyla güncellendi (yeni karşılama, "Ana hedefin ne?" + 2 seçenek,
"Planın hazır"), üst çubuk "Kaydedildi, yayında değil"; geri al →
`/kaydet` {Eski başlık, 2 adım, imza **ai-imza**}, tuval eski hâline döndü.
Düzeneğin kendi hatası: medya saplaması dizi döndü (gerçek uç `{gorseller}`)
→ düzenleyici çöktü; düzeltildi. İlk tıklama denemesi sekmeyi seçmemişti
(panel 0×0 iken JS ile dolduruluyordu) — "sekme kayboluyor" sanılmadan
boyut ölçülerek bulundu.
**Ölçülmedi:** gerçek oturum + gerçek Claude (anahtar yok).

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 250 çekirdek testi.
