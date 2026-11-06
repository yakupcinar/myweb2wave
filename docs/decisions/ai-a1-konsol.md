# A1 — AI araçları konsoldan (`app:huni-arac`)

2026-10-27 · plan `PLAN.md` yol haritası; A0 `ai-a0-arac-katmani.md`.

## Karar
- Plandaki `app:huni taslak` yerine **genel araç çalıştırıcı**:
  `bin/console app:huni-arac <app> <araç> [--girdi JSON | stdin] [--huni ad] [--liste]`.
  Girdi = aracın JSON girdisi, çıktı = sonucun JSON'u. Panel sohbeti (A2)
  ve MCP (A5) ile AYNI araçlar, aynı doğrulama; konsola özel eşleme yok.
- Çıkış kodu: 0 başarı · 1 araç hata döndürdü (`hatalar`) · 2 araç hiç
  çalışmadı (bilinmeyen uygulama, girdi JSON değil).
- Uygulama komut sınırında `%uygulamalar%`'a karşı denetlenir (kapsamı
  güvenilir kod kurar; konsol argümanı güvenilir değil).
- `--huni` panel sohbetiyle aynı dar kapsam (yeni huni aracı yok).
- **Reddedilen:** `taslak`/`oku`/`dogrula` alt komutları (her araç için
  ikinci eşleme, A2/A5 ile kayar).

## Test (`HuniAracCommandTest`, 3) + A0 testi artık kaptaki servisi kullanır
Liste kapsamla; yeni → oku (imza) → kaydet, çıktı JSON, 0; araç hatası 1 +
JSON hata; bozuk JSON ve bilinmeyen uygulama 2. Stdin yolu CommandTester'la
ölçülemez → gerçek koşuda ölçüldü (aşağıda).

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | araç hatasında da 0 | 🔴 |
| K2 | uygulama denetimi yok | 🔴 |
| K3 | `--huni` yok sayılır | 🔴 |
| K4 | bozuk JSON yakalanmaz (araç "nesne olmalı" der, kod 1) | 🔴 |

## Gerçek (dev veritabanı, stdin, Claude Code)
Prompt: "fitness için dallanmalı quiz: hedefe göre farklı soru, çoklu
seçim, serbest yanıt, yorum, faydalı paywall". `yeni_huni` → `huni_oku`
(imza) → `taslak_kaydet < istek.json` (8 adım: tanıtım, dallanan tekli
soru → 3 hedef, iki tekli, çoklu, serbest yanıt, yorum, paywall + 3 fayda)
→ hepsi KOD=0 → `onizleme_adresi`. Tarayıcıda önizleme: karşılama,
"Formda kalmak" → doğrudan `engeller` (dallanma atladı), çoklu seçimde kare
işaretler. **Panelde `keepchat/ai-ornek` taslağı duruyor** (yayınlanmadı,
ziyaretsiz → panelden "Sil" ile kalkar); yorum adımı "Örnek yorum —
yayından önce gerçeğiyle değiştir" diye işaretli.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 243 çekirdek testi / 1.774 doğrulama.
