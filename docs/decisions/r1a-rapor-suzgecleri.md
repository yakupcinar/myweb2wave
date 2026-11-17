# R1a — Rapor süzgeçleri: çekirdek (2026-11-11)

Plan sırası (kullanıcı 2026-11-10: M9 → R1 → E1 …). R1'in tanımı (web2wave farkı): süzgeçler huni, UTM, ülke, deney
varyantı; önceki dönem P7b'de yapılmıştı. R1a çekirdek, R1b panel.

## Ne yapıldı

- **Ziyarette ülke:** `huni_ziyaretleri.ulke` (iki harf, CHECK). Ziyaret açılırken fiyatla aynı kaynaktan yazılıyor:
  IP, test için gövdedeki `ulke` (huni sayfası adresteki `?ulke=DE`'yi iletir). IP saklanmaz; eski ziyaretlerde boş.
- **[`RaporSuzgeci`](../../core/src/Panel/RaporSuzgeci.php):**
  - Alanlar: uygulama, huni, kaynak, kampanya, ülke, deney (+ varyant).
  - Her sorguya aynı koşulu ekliyor (ziyaret `z` üzerinden, birlikte VE).
  - Kaynak, Kaynaklar sütunuyla aynı tanım (`Rapor::KAYNAK`); "doğrudan" ve "meta (fbclid)" de seçilebilir.
  - Eski `?string $app` parametresinin yerini aldı; MCP `RaporSuzgeci::uygulama($app)` ile aynı davranıyor.
- **Süzgecin uygulandığı yerler:** raporun bütün bölümleri. Sayımlar ve önceki dönem, gelir, günlük grafik (olay
  deposu ziyaretle birleşerek), izin, anket adımları, cevaplar, kaynaklar, Meta kampanyaları, kohort; ayrıca üç
  sayfalı liste ve toplamları.
- **Uçlar:**
  - `/panel/api/rapor` ve `/panel/api/rapor/{liste}` `huni, kaynak, kampanya, ulke, deney, varyant` alıyor;
    biçimsiz değerde 422 dönüyor.
  - Yeni `/panel/api/rapor/secenekler?gun=` dönemde görülen değerleri veriyor: çok ziyaretliden aza, en çok 50;
    deneyler varyantlarıyla.

## Neden böyle

- **Tek süzgeç nesnesi.** On iki sorgu ayrı ayrı süzülseydi biri unutulur, bölümler birbirini tutmazdı (kural 7, blok
  ailesi). Kırma her bölümü ayrı ayrı bozdu.
- **Biçimsiz değerde 422, sessiz yok sayma değil.** Süzülmemiş sayıyı "süzülmüş" sanmak yanlış karar verdirir.
- **Ülke ziyarette, IP değil.** Fiyat zaten IP'den ülke çıkarıyor; iki harf kişisel veri sayılmaz, IP saklanmaz.
- **Seçenekler veriden.** Sabit liste (bütün ülkeler) yerine dönemde görülenler; boş sonuç veren seçenek
  sunulmaz.
- **Ölçümle bulunan, hata veren bir PostgreSQL kuralı (sessiz değil, tuzak değil):** `ORDER BY 1 COLLATE "C"` konumu
  sabit tamsayı sayar ("collations are not supported by type integer"); `ORDER BY d COLLATE "C"`'de takma ad ifade
  içinde tanınmaz. İfadenin kendisi yazıldı. 500'ün sebebi yanıt gövdesi okunarak bulundu, tahminle değil.

## Kırma denemeleri — 11/11 kırmızı (S4 ilk koşuda tutmadı)

| Deneme | Sonuç |
|---|---|
| S1 huni süzülmez | 2 |
| S2 kaynak `utm_source` ("doğrudan" kaybolur) | 1 |
| S3 varyant yok sayılır | 1 |
| S4 önceki dönem süzülmez | **ilk koşuda YEŞİL** (aşağıda) → test güçlendirildi → 1 |
| S5 günlük grafik süzülmez | 2 |
| S6 izin süzülmez | 2 |
| S7 liste toplamı süzülmez | 1 |
| S8 küçük harf ülke kabul edilir | 1 |
| S9 ziyarete ülke yazılmaz | 1 (AtifTest) |
| S10 huni sayfası `?ulke=`'yi iletmez | 1 (huni) |
| S11 MCP uygulama süzgeci düşer | 1 (McpTest) |

**S4 notu:** test, `huni=genel` önceki dönemi için yalnız "reklam" sütununa bakıyordu. Önceki dönemdeki tek ziyaret
organikti, yani o sütun süzgeçli de süzgeçsiz de 0'dı. İddia toplam ziyarete çevrildi; S4 yeniden koşuldu ve
kırmızı. Betikler: `$TMPDIR/…/scratchpad/kir-r1a.py`, `kir-r1a-2.py`.

## Ölçüm

- **Testler:**
  - RaporSuzgeciTest 3 test, 70+ doğrulama. Her süzgeç tek başına ve birlikte; önceki dönem, günlük, izin, listeler;
    seçenekler; 422.
  - Fikstür notu: 35 gün önceye alınan ziyaretin olayı bugün tarihli (olay deposu yalnız eklemeye açık); günlük
    iddiası o ziyareti içermeyen süzgeçle.
  - AtifTest +1 (ülke yazılır / biçimsiz yok sayılır), huni +1.
- **Tam kontrol:**
  - İlk koşu statik analizde KIRMIZI: süzgeçte gereksiz tip denetimi; testte karışık tip ve PHPStan'in `json()`'u
    saf sayması (`@phpstan-impure`).
  - Düzeltilince YEŞİL, 755 çekirdek testi.
