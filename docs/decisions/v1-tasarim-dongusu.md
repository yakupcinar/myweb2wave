# V1 — AI tasarım yönergesi + ölçen görsel döngü (2026-11-03)

**İstek:** kullanıcı 2026-11-03: "önce V serisi; mümkünse quiz builder'ı
da iyileştirelim". Sıra ve gerekçe: `rakip-gorsellik-arastirmasi.md`.

## Ölçüm (karardan önce)

`ekran_goruntusu` aracı yalnız görüntü döndürüyordu; yönerge aracı
kullanmayı şart koşmuyordu. Yerleşimi ölçen betik yazılıp `uyku-ornek`in
16 adımında gerçek Chrome'da koşuldu: çoğunda altta %33–58 boşluk. Görüntü
+ hesaplanmış stil zinciri: **düğme telefonda alta yapışmıyordu** — Vite
geçişinde (9c90af2) bağlama kökü `div#huni` blok kutu üretip flex zincirini
koparmış (tuzak §45). Yani "boş alt" kusurunun büyük kısmı yazarın değil
**aracın** hatasıydı.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | `#huni` → `display: contents` (Astro'daki davranış) | sarmalayıcıya flex kuralları | Kutu üretmemek eski, ölçülmüş düzeni birebir geri getirir |
| 2 | Yükleme ekranı telefonda dikey ortalı | yazara blok ekletmek | Yüklemeye eklenecek blok yok; uyarı uygulanamaz olurdu |
| 3 | `ekran_goruntusu` görüntüyle birlikte **ölçüm + Türkçe uyarı** döndürür: içerik–düğme boşluğu, kaydırmada kalan düğme, yetim son satır, 44 px altı dokunma hedefi, yatay taşma | yalnız görüntü | Model görüntüde boşluğu/yetim kelimeyi her zaman fark etmez; sayı tartışmasız |
| 4 | Boşluk = içerik altı → yapışık düğmenin üstü (düğme yoksa ekran altı); yükleme ekranı muaf | ekran altına uzaklık | Düğme alta yapışınca alt boşluk hep ~%4; anlamlı boşluk araya kayar (ölçüldü) |
| 5 | Yetim satır yalnız **yaprak** metin öğesinde | başlık/paragraf/düğme seçicisi | Izgara seçeneğinde emoji metnin üstünde — iki "satır" sayılıp 4/4 yalancı uyarı verdi (ölçüldü) |
| 6 | Boşluk eşiği %35 (aşan uyarır) | %40 · %30 | uyku-ornek: zengin ekranlar 0,21–0,33, seyrek bilgi ekranları 0,37–0,49 |
| 7 | `TasarimYonergesi::METIN` (aşama düzeni, tek odak, ekranı doldur, yetim satır, simge tutarlılığı, dürüst içerik) + **zorunlu görsel döngü** (kaydet → değişen her ekranın görüntüsü → uyarıları düzelt → uyarısız olana dek); panel asistanı + MCP isteminde | ayrı skill dosyası | Panel asistanı skill okumaz; istem önbellekli önekte, ek maliyet tek seferlik |

Yönerge metni kendi cümlemiz; FunnelFox eklentisi tescilli (ölçüldü), ondan
satır alınmadı.

## Uçtan uca kanıt (AI rolünde, gerçek araçlarla)

Yönergeyi `uyku-ornek`e uyguladım (huni_oku → değiştir → taslak_kaydet →
ekran_goruntusu): 5 ekranda uyarı (bilgi %49 boşluk; kafein, telefon,
nefes, sosyal yetim satır) → içerik eklendi (madde/kutu, uydurma sayı yok),
metinler yeniden yazıldı → **16/16 adım uyarısız**. ⚠️ telefon ekranı %35
(eşikte) — görüntüde ortası hâlâ ferah; gerçek çözüm görsel (V3), Ç100.

## Kırma denemeleri (8/8 kırmızı)

| # | Bozulan | Düşen |
|---|---|---|
| V1 | `#huni` display: contents | yerlesim.test |
| V2 | eşik `>` → `>=` | EkranCekimiTest sınır |
| V3 | kaydırmada boşluk muafiyeti | EkranCekimiTest |
| V4 | düğmesiz ekranda düğme uyarısı | EkranCekimiTest |
| V5 | araç sonucunda uyarılar | HuniAraclariTest |
| V6 | asistan isteminde yönerge | PanelAiTest |
| V7 | MCP isteminde yönerge | McpTest |
| V8 | yetim satır uyarısı | HuniAraclariTest |

Tarayıcı içi ölçüm betiği (JS) süitte koşmaz (Chrome gerekir); gerçek
Chrome'da 16 adımda koşuldu, eşik ve yanlış pozitifler görüntüyle
karşılaştırıldı (yukarıda). Yükleme ortalama CSS'i yalnız görüntüyle ölçüldü.

## Ölçümler

`bin/kontrol` YEŞİL: 459 çekirdek testi / 3682 iddia (+ huni yerleşim testi).
