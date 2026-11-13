# R1b — Rapor süzgeç satırı: panel (2026-11-11)

R1'in ikinci yarısı. Çekirdek (R1a) süzgeci ve seçenekleri verdi; bu blok panelde kullanıyor.

## Ne yapıldı

- **Süzgeç satırı:** Rapor'da sekmelerin altında, hap biçiminde seçim kutuları: Huni · Kaynak · Kampanya · Ülke ·
  Deney (seçilince Varyant).
  - Seçenekler `/panel/api/rapor/secenekler`'den, dönemde görülen değerler; ülkeler Türkçe adıyla
    (`Intl.DisplayNames`: TR → "Türkiye").
  - Seçili hap vurgu halkasıyla görünür.
  - "Süzgeçleri temizle" yalnız bir süzgeç seçiliyken çıkar.
- **Adres:** süzgeç adreste. Gün seçicisi, sekmeler ve Anket hunisi süzgeci korur. Adres sabit sırayla yazılır (gün
  → sekme → anket → süzgeçler); varsayılanlar (30 gün, Genel bakış, boş) yazılmaz.
- **İstekler:** rapor ve üç sayfalı liste aynı süzgeçle istenir; süzgeç sorgu anahtarında.
- **Davranış ayrıntıları:**
  - Deney değişince varyant sıfırlanır.
  - Dönemde görülmeyen seçili değer listede kalır; "Tümü" görünüp yanıltmaz.
- **Ad çakışması:** Anket sekmesinin huni seçimi adreste `huni=` idi, süzgecin `huni`'siyle çakışıyordu. `anket=`'e
  taşındı, seçicinin adı "Anket hunisi" oldu. Süzgeçteki huni zaten Anket dahil bütün raporu o huniye indiriyor.

## Neden böyle

- **Hap ve seçim kutusu.** Web2wave'deki gibi her süzgeç tek hap. Açılır panel ya da "+ süzgeç ekle" bu beş alan için
  fazla adım; yerel `<select>` klavye ve ekran okuyucuyla hazır geliyor.
- **Seçili hap halkayla (`ring`), çerçeve rengi değişmeden.** Ortak alan denetimi (P7e) ortak stili `border-kenar-2`
  ile ölçüyor. Çerçeve rengini değiştirmek denetimi bozardı; halka ayrı katmanda.
- **Adres sabit sırada.** Bağlantı karşılaştırması ve yer imi tek biçimde olsun; `URLSearchParams.set` var olanı
  yerinde, yeniyi sonda bırakıyordu.

## Kırma denemeleri — 7/7 kırmızı (B2 ilk koşuda tutmadı)

| Deneme | Sonuç |
|---|---|
| B1 rapora süzgeç gitmez | 1 |
| B2 listeye süzgeç gitmez (Kaynaklar) | **ilk koşuda YEŞİL** → test üç listeyi de denetler hâle getirildi → 1 |
| B3 bağlantılar süzgeci düşürür | 1 |
| B4 deney varyantı sıfırlamaz | 1 |
| B5 "Temizle" bir şey yapmaz | 1 |
| B6 dönemde olmayan seçili değer kaybolur | 1 |
| B7 ülke kodu ham | 1 |

**B2 notu:** test yalnız Ziyaretler listesinin isteğine bakıyordu, bozulan Kaynaklar'dı. Test süzgeç seçiliyken
Kaynaklar ve Anket sekmelerine de geçiyor. İlk yeniden koşu test henüz kırmızıyken yapıldı ve sayılmadı; test
yeşilken yeniden koşuldu. Betikler: `$TMPDIR/…/scratchpad/kir-r1b.py`, `kir-r1b-2.py`.

## Ölçüm

- **Panel:** Rapor testi 15 (yeni: süzgeç satırı; Anket testi `anket=`'e göre güncellendi).
- **Ekran döngüsü:** yeni `rapor-suzgecli` durumu (`kaynak=meta&deney=baslik`). Masaüstünde süzgeç satırı, seçili
  haplar, varyant hapı ve "Süzgeçleri temizle" görüldü. Telefonda sayfa 390 px, yatay kayma yok.
- **Tam kontrol:** YEŞİL (755 çekirdek testi).
