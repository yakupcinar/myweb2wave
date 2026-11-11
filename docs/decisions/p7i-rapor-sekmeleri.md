# P7i — Rapor sekmelerde (A27, 2026-11-11)

Kullanıcı: "Rapor kısmı çok uzuyor, ne yapabiliriz, web2wave'i görmüştün". Karar: **sekmeler (önerilen)**.

## Ne yapıldı

Rapor tek uzun sayfa olmaktan çıktı; web2wave'in Analytics sekmeleri gibi beş sekmeye bölündü. Sekme adreste
(`?sekme=`), gün seçicisi ile sekme birbirini korur, varsayılanlar adrese yazılmaz.

| Sekme | Bölümler |
|---|---|
| Genel bakış | Özet · Günlük grafikler · Ziyaretten aboneliğe · Reklam harcaması |
| Anket | Anket (tek huni, seçici) · Cevaplar (o huninin) · Serbest yanıtlar |
| Kaynaklar | Kaynaklar · Reklam ölçümü izni |
| Gelir | Kohort geliri |
| Ziyaretler | Ziyaretler (sayfalı) |

- **Anket sekmesi:** artık tek huni gösteriyor.
  - Varsayılan, ilk adımına en çok ziyaret gelen huni. Seçici "genel · 74 ziyaret" gibi çok ziyaretliden aza sıralı,
    seçim adreste (`huni=app/ad`).
  - Cevaplar da aynı huniye süzülür. Bunun için çekirdek cevapları huni başına gruplar: `PanelRaporu.cevaplar[].huni`;
    hunisiz ziyaret "—" (adımlarla aynı).
  - MCP `rapor_cevaplar` da artık huni başına satır veriyor.
- **Liste uçları** (kaynaklar, ziyaretler, serbest yanıtlar) yalnız açık sekmede istenir.

## Neden böyle

- **Ölçüm:** sayfa masaüstünde 6.604, telefonda 7.730 px idi. En büyük üç bölüm Anket (1.710; huniler alt alta),
  Ziyaretler (1.268) ve Cevaplar (796).
- **Reddedilen:** tek sayfa + katlanır bölümler. Sayfa yine uzun kalırdı, web2wave'in dilinden de ayrılırdı.
- **Sekmeler gezinme bağlantısı** (`nav` + `aria-current`), `tablist` değil. Sekmenin adresi var, geri tuşu ve yer
  imi çalışıyor. Aynı desen gün seçicisinde de kullanılıyor.
- **Genel bakışta grafikler tablodan önce:** web2wave'in Overview'u gibi KPI → zaman grafiği → ayrıntı.
- **Cevaplar huni başına (çekirdekte):** seçili huninin cevapları istemcide bütün hunilerin toplamından
  ayrıştırılamazdı. Sözleşme ve iki istemci tipleri birlikte güncellendi (kural 7).

## Kırma denemeleri — 7/7 kırmızı

| Deneme | Sonuç |
|---|---|
| I1 varsayılan sekme Anket | 9/14 |
| I2 gün bağlantısı sekmeyi düşürür | 3/14 |
| I3 huniler ziyarete göre sıralanmaz | 1/14 |
| I4 Cevaplar huniye süzülmez | 1/14 |
| I5 adresteki huni yok sayılır | 1/14 |
| I6 Genel bakışta liste ucu istenir | 1/14 |
| I7 çekirdekte cevaplar ziyaret başına bölünür | PanelTest 2 düştü |

Betik: `$TMPDIR/…/scratchpad/kir-p7i.py`.

## Ölçüm

**Ekran döngüsü** (`bin/panel-ekran rapor rapor-anket rapor-kaynaklar rapor-gelir rapor-ziyaretler`), sayfa
yüksekliği:

| Sekme | Masaüstü | Telefon |
|---|---|---|
| Genel bakış | 1.425 | 2.284 |
| Anket | 1.166 | 1.259 |
| Kaynaklar | 1.118 | 1.433 |
| Gelir | 900 | 844 |
| Ziyaretler | 1.521 | 1.599 |

Önce: 6.604 / 7.730. Hiçbir sekmede yatay kayma yok. Görüntüye açık ve koyu temada bakıldı.

**Testler:**

- Rapor testi 14 (2 yeni: sekmeler; Anket huni seçimi). Bölümler arası eski testler sekmelerine bölündü.
- Çekirdek PanelTest: cevaplar iki huniye ayrılıyor. Testte gerçek bir yayımlanmış huni sürümü kuruluyor; ziyaretin
  hunisi sürümsüz olamıyor (DB kısıtı).
- **Tam kontrol:**
  - İlk koşu KIRMIZI: `belge-baglanti`, Rapor.php değişince `Rapor::kohort` `#L353`→`#L355` kaydı (§58).
    `--duzelt` ile düzeltildi.
  - İkinci koşu YEŞİL: 748 çekirdek testi, panel 186.
