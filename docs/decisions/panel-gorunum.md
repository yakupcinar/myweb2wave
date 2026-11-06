# Panel görünümü — rapor, huniler, giriş (web2wave'den fikir)

2026-10-27 · kullanıcı: "panel ve panel/huniler basit siyah ekran;
web2wave ve diğerlerine bak, geliştir".

## Ölçüm (önce)
Panelin kendi tasarımı yoktu: `color-scheme: light dark` + tarayıcı
varsayılanları → koyu sistemde siyah zemin, düz bağlantılar, çıplak
tablolar, varsayılan düğme/form. Menü sayfa başında düz bağlantılar.

## Referans (yalnız görsel fikir, kod yok)
web2wave.com sitesindeki panel ekran görüntüleri (panelin kendisi
Cloudflare 403, `rakip-arastirmasi.md` S4): koyu zemin, solda simgeli menü,
büyük sayılı özet kartları, adım adım daralan "Quiz Funnel" şeridi, filtre
satırı + koyu başlıklı tablolar. Kapalı kaynak; yalnız düzen fikri.

## Karar
- `panel/duzen.html.twig`: solda menü (marka, Rapor, Huniler, altta Çıkış;
  `aria-current` rotadan — `panel_huni*`/`panel_kural*` → Huniler), sağda
  başlık + alt başlık + sayfa eylemleri + bildirimler. ≤900 px menü üstte.
  `_ust.html.twig` kalktı (düzene taşındı).
- Renk değişkenleri açık ve koyu iki takım (`prefers-color-scheme`); koyu
  web2wave'e yakın lacivert-siyah.
- Rapor: üstte **özet** (Sayfayı gördü / Ödemeye geçti / Ödedi / Dönüşüm =
  reklam + organik toplamı; yeni sorgu yok, aynı sayımlar), gün seçimi düğme
  grubu, adım tablosunda **huni şeridi** (genişlik = ilk adıma göre oran,
  hücre metni değişmez), aşama/kaynak rozetleri, ziyaretler kart içinde.
- Huniler: her uygulama iki kart (huniler + yeni huni formu; kurallar +
  kural formu). Düzenle birincil düğme, Sil/Arşivle kırmızı.
- Giriş: ortada markalı kart.
- Dar ekran: grid sütunları `minmax(0,1fr)` / `min(…,100%)` (235 px'te yana
  taşma ölçüldü, düzeltildi); uzun adres `overflow-wrap`.
- Eski klasik düzenleyici stilleri (`#duzenleyici`, L11'de silinen ekran)
  kaldırıldı.
- **Reddedilen:** dış CSS/ikon kütüphanesi (panelde üçüncü taraf kod yok —
  base yorumu) · yalnız koyu tema (açık sistemde de okunmalı) · önceki döneme
  göre değişim yüzdesi (web2wave'de var; sorgu yok, uydurulmaz — gerekirse
  ayrı blok).

## Test
`PanelTest`: özet = `['3','2','1','33,3 %']` (elle 2+1, 2+0, 1+0); menüde
yalnız Rapor işaretli; şerit genişlikleri `100.0% / 66.7% / 33.3%`.
`PanelHuniTest`: Huniler sayfasında işaretli menü "Huniler". Mevcut 31 test
(kart `dd`, tablo hücreleri, `data-*`, düğme adları) değişmeden geçti.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | özet ziyareti yalnız ilk kaynağı sayar | 🔴 1 test (ilk deneme `ad` tanımsız → şablon 500, 8 kırmızı: **sahte**, §8; geçerli bozmayla yeniden koşuldu) |
| K2 | şerit hep %100 | 🔴 1 test |
| K3 | etkin menü hep Rapor | 🔴 1 test |

## Gerçek (tarayıcı)
Panel şifresi bende yok → geçici test (commit edilmedi) test veritabanına
24 ziyaret + adım + ödeme yazıp oturumlu üç sayfayı HTML'e döktü; yerel
statik sunucudan açıldı. Masaüstü 1280 px (ölçekli çerçeve) ve 235 px; koyu
ve açık (koyu blok çıkarılmış kopya). **Ölçülmedi:** gerçek oturumla canlı
çekirdekte görünüm (kullanıcıda), Puck düzenleyici sayfası (dokunulmadı).

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 233 çekirdek testi / 1.650 doğrulama.
