# T4 — olay deposu (ziyaret zaman çizelgesi + uygulama akışı, 2026-11-03)

Kullanıcı 2026-11-03: döngü, sıradaki görev. Plan: temel 4 (`web2wave-platform-yapisi.md`) — raporlar, grafikler (P4), deney sonuçları, pikseller, giden webhook ve e-posta tetikleyicileri **aynı olay akışını** okur.

## Ölçüm (başlangıç)

Kaynak tabloların hepsinde zaman var: `huni_ziyaretleri` (created_at, izin_at, izin_red_at), `huni_adimlari` (created_at), `huni_cevaplari`/`huni_metin_cevaplari` (updated_at), `odeme_oturumlari` (created_at, tamamlandi_at), `subscriptions` (updated_at). Ama cevap, izin ve abonelik **yalnız son hâli** tutuyor — geçmiş yazılmıyor.

## Karar

- **Görünüm, ikinci yazım yok** (T2 ile aynı gerekçe): `ziyaret_olaylari (ziyaret_id, app, ad, veri jsonb, zaman)`, göç `Version20261103100000`. Yazma yolları değişmedi.
- **Sözlük** (`App\Olay\Olaylar::SOZLUK`, ad değişmez, yalnız eklenir): `huni.ziyaret` {huni?, surum?} · `izin.verildi` · `izin.reddedildi` · `adim.goruldu` {adim, sira} · `cevap.verildi` {soru, secenekler?} · `odeme.baslatildi` · `odeme.tamamlandi` · `abonelik.durum` {durum}.
- **Serbest yanıt metni olaya girmez** (yalnız hangi soru) — kişisel veri olabilir, akış dışarı (webhook) gidecek (Ç90).
- **Okuma:** `ziyaretin(ziyaret)` zaman çizelgesi; `akis(app, imlec, sinir)` uygulamanın akışı, sıra `(zaman, ziyaret, ad, veri)` bayt sırasıyla — aynı andaki olaylar da ayrılır; imleç son olayın dört değeri (base64 JSON), "sonrası" kesin (`>`), bozuk imleçte null. Konsol: `app:olaylar <ziyaret> | --sozluk`.
- Kişi düzeyi yok (T2'deki `kisinin` gibi) — tüketicisi gelince (P5).

## Ölçüm (gerçek sistem, yerel veritabanı)

Göç uygulandı. 381 olay: adim.goruldu 181, huni.ziyaret 104, cevap.verildi 45, izin.verildi 36, odeme.baslatildi 13, odeme.tamamlandi 1, abonelik.durum 1 — hepsi sözlükte (izin.reddedildi geliştirme verisinde yok). Ödemeli bir ziyaretin çizelgesi `app:olaylar` ile okundu. Akışın ilk sayfası (101 satır) 1,0 ms (`EXPLAIN ANALYZE`). Yan gözlem: abonelik ve ödeme tamamlanması aynı anda yazılıyor → ad sırasıyla `abonelik.durum` önce gelir (belirlenimli ama neden → sonuç sırası değil, Ç92).

## Kırma denemeleri (7/7 kırmızı)

| # | Bozulan | Sonuç |
|---|---|---|
| O1 | serbest metin olaya girer (görünüm) | 🔴 |
| O2 | izin reddi olayı yok (görünüm) | 🔴 2 test |
| O3 | uygulama süzgeci yok | 🔴 |
| O4 | imleç "sonrası" değil "sonrası-dahil" | ilk koşu sonsuz döngü → bellek doldu (deneme gerçek ama sonuç okunamaz); teste sayfa sınırı → 🔴 2 test |
| O5 | aynı andaki olayları ayıran veri sırası yok | 🔴 |
| O6 | bozuk imleç baştan sayılır | 🔴 |
| O7 | sözlükte abonelik yok | 🔴 |

Görünüm denemeleri test veritabanında göç aşağı/yukarı ile koşuldu, sonra geri alındı. PHPStan düzeltmelerinden sonra yedisi yeniden koşuldu: yine kırmızı.

## Kontrol

`bin/kontrol` 203 s, EXIT=0, `KONTROL: YEŞİL` — core 413 test / 3.243 doğrulama (önceki koşu PHPStan'da kırmızıydı).
