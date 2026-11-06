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

CI (bağımlılıklar kilit dosyasından sıfırdan): koşu 41445596923, SHA 8f02d92, `KONTROL: YEŞİL` (core 369 test, görünüm göçü dahil).

Yan iş: PHPStan aynı çağrının sonucunu hatırlayıp ikinci okumayı "hep yanlış" saydı → okuma yöntemleri `@phpstan-impure` (canlı veri).

# T2b — oynatıcıda aynı adlar (sunucu ↔ tarayıcı eşliği, 2026-11-02)

Kullanıcı: "T2b ile devam et". Koşul motoru (T3) hunide tarayıcıda çalışacak; tarayıcı sunucuyla AYNI özellikleri üretmeli.

- **Girdi = oynatıcının zaten gönderdiği olaylar** (`/ilerleme` gövdesi `{sira, adim, cevap|cevaplar|cevapMetni}`) + huni, sürüm, adres, izin. Saf işlev `huni-bilesenleri/ozellikler.ts` (ortak kitaplık; panel önizlemesi de kullanabilir). Sunucunun kuralları aynen: aynı sırada ilk adım kalır, yeni cevap eskisini değiştirir, boş liste / boş serbest yanıt siler, `adim.son` en ileri sıra.
- **Eşlik ölçülür:** ortak örnek dosyası `huni-bilesenleri/ozellik-ornekleri.json` (tema-ornekleri.json deseni). `OzellikEsligiTest.php` olayları GERÇEK uçlardan (`POST /huni/ziyaretler`, `/ilerleme`) geçirip görünümü okur; `ozellikler.test.ts` aynı olayları işleve verir; ikisi de elle yazılmış beklenene eşit olmalı.
- **Bulunan tuzak:** veritabanı sıralaması en_US — seçenek listesi "a-1 a_1 a1", JS "a-1 a1 a_1". Sunucu testi ilk koşuda tam bu yüzden kırmızıydı. Göç `Version20261102140000` görünümü `COLLATE "C"` ile değiştirir (eski göç aynı kalır; sorgusu `sorgu()` ile paylaşılır).
- Tarayıcıda yalnız hunin kendi verisi: `odeme.*`, `abonelik.*` yok (ödeme sonrası sunucuda).
- Oynatıcıya henüz bağlanmadı: tüketicisi T3 (kullanılmayan durum eklenmedi).

Kırma 6/6 kırmızı: H1 sunucu en_US sıralaması · H2 oynatıcı sıralamaz · H3 boş serbest yanıt silinmez · H4 adim.son son olay · H5 seçilmemiş izin yazılır · H6 tekli cevap liste değil. Gerçek veritabanında göç sonrası 441 özellik aynı, örnek ziyaret değişmedi.
