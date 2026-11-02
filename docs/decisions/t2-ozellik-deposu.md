# T2a — özellik deposu (ziyaret ve kişi, tek ad uzayı)

Kullanıcı 2026-11-02: "T2 ile devam et". Plan: temel 2 (`web2wave-platform-yapisi.md`) — her cevap, UTM, izin, ödeme, abonelik **aynı ad uzayında özellik**; koşul motoru (T3), kişiselleştirme, CRM (P5), webhook hep bunu okur.

## Ölçüm (başlangıç)

Veri beş tabloya dağınık: `huni_ziyaretleri` (huni, sürüm, UTM, izin), `huni_cevaplari`, `huni_metin_cevaplari`, `odeme_oturumlari`, `subscriptions`; kişi (`kisiler`) ziyarete yalnız ödemeyle bağlanıyor (oturum → abonelik → e-posta → kişi).

## Karar

- **Görünüm, ikinci yazım yok:** `ziyaret_ozellikleri (ziyaret_id, ad, deger jsonb, zaman)` kaynak tablolardan (göç `Version20261102130000`). Eş tutulacak kopya olmadığı için tutarsızlık olamaz; yazma yolları değişmedi.
- **Sözlük** (`App\Ozellik\Ozellikler::SOZLUK`, ad değişmez, yalnız eklenir): `huni`, `huni.surum`, `utm.source|medium|campaign|content|term`, `izin.reklam` (evet/hayir — son karar), `cevap.<adım>` (seçmelide **her zaman liste**, tekli de — T3'te "içerir" tek biçim; serbest yanıtta metin), `adim.son` (ulaşılan en İLERİ adım), `odeme.durum` (baslatildi/tamamlandi), `abonelik.durum` (Stripe durumu).
- **Kişi:** ödemeyle bağlanan ziyaretlerin birleşimi, her adda en son değer (`kisinin`).
- **Bilerek yok:** tıklama kimlikleri, IP, tarayıcı (kişisel eşleme verisi; koşul/CRM gerekmiyor); cihaz bilgisi toplanmıyor (Ç88).
- Okuma yolu: servis + `bin/console app:ozellikler <ziyaret> | --kisi <kişi> | --sozluk` (ölçüm/hata ayıklama; P5 aynı servisi kullanacak).

## Ölçüm (gerçek sistem, yerel veritabanı)

103 ziyaret, 441 özellik; dağılım: adim.son 98, huni/huni.surum 92, cevap.* 45, izin.reklam 36, utm.source 27, utm.campaign 21, utm.medium 17, odeme.durum 12, abonelik.durum 1 — hepsi sözlükte. Örnek ziyaret (fadc1eda…): huni genel s5, utm meta/paid_social/deneme, cevap.kiminle [aile], cevap.cihaz [iphone], cevap.oncelik [hiz], adim.son paywall, odeme baslatildi. Tek ziyaret sorgusu 0,37 ms (`EXPLAIN ANALYZE`).

## Kırma denemeleri (6/6 kırmızı)

| # | Bozulan | Sonuç |
|---|---|---|
| G1 | adim.son en geri adım | 🔴 |
| G2 | tekli cevap liste değil | 🔴 2 test |
| G3 | izin reddi "evet" sayılır | 🔴 |
| G4 | kişide en eski değer kazanır | 🔴 2 test |
| G5 | kişiye bağlanmamış ziyaret karışır | 🔴 2 test |
| G6 | sözlükte cevap.* yok | 🔴 2 test |

Görünüm denemeleri test veritabanını yeniden kurarak koşuldu; sonda yeniden kuruldu ve yeşil.

Yan iş: PHPStan aynı çağrının sonucunu hatırlayıp ikinci okumayı "hep yanlış" saydı → okuma yöntemleri `@phpstan-impure` (canlı veri).
