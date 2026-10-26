# Blok: E1.2 — panelde huni düzenleme (A7) — 2026-10-20

## Ne yapıldı
Panelde `Huniler` (`/panel/huniler`): uygulama başına yayındaki sürüm ve
"yayınlanmamış değişiklik" işareti. Düzenleme ekranı
(`/panel/huniler/{app}`): sayfa başlığı; her adım için kimlik + metinler;
soru seçenekleri (ekle, sil, yukarı/aşağı); adım ekle (tanıtım, soru —
ödemenin hemen önüne), sil, yukarı/aşağı (ödeme hep sonda, taşınamaz);
"Kaydet" taslağı JSON olarak yollar, çekirdeğin doğrulama hataları sabit
alt çubukta görünür. "Yayınla" yeni değişmez sürüm açar (taslak yayındakiyle
aynıysa reddeder); sürüm tablosu ziyaret sayısıyla, her satırda "Taslağa
geri yükle" (geri alma = geri yükle + yayınla). Önizleme bağlantısı ekranda
(1 saat). Kaydedilmemiş değişiklikte yayın düğmesi kapanır ve sayfadan
çıkarken tarayıcı uyarır. Düzenleyici derleme adımsız düz JS
(`public/varliklar/huni-duzenleyici.js`), metni yalnız `textContent`/`value`
ile yazar; taslak sayfaya HTML özniteliği olarak kaçışlanıp gömülür.

## Neden böyle
- **A7, E1:** sürükle-bırak yok (E2'de Puck); düğmeyle sıralama yeter.
- **Doğrulama yalnız çekirdekte:** düzenleyici kuralları tekrar etmez,
  hatayı gösterir — iki kopya kural kayar (kural 7).
- **Derleme adımsız JS:** panel Twig; React paketi E2'de Puck'la gelecek,
  E1 için Node hattı kurmak erken.
- **Aynı taslağı ikinci kez yayınlamak reddedilir:** sürüm listesi anlamlı
  kalsın (aynı içerikli v4 = v5 gürültü).
- **Her yazma CSRF:** JSON kaydı üstbilgide (`X-CSRF-Token`), formlar `_token`.

## Kırma denemeleri

| # | Bozulan | Sonuç |
|---|---|---|
| Z1 | JSON kaydında CSRF denetimi yok | 🔴 `testKaydetCsrfJetonuIster` |
| Z2 | yayında CSRF yok | 🔴 `testYayinlaVeGeriYukleCsrfIster` |
| Z3 | aynı taslak yeniden yayınlanabiliyor | 🔴 `testYayinlaSurumAcar…` |
| Z4 | taslak özniteliğe `|raw` gömülüyor | 🔴 `testDuzenleyiciTaslagiKacislanmis…` |
| Z5 | geri yükleme en son sürümü alıyor | 🔴 `testEskiSurumTaslagaGeriYuklenir` |
| Z7 | `/panel/huniler` girişsiz | 🔴 `testGirisYapmadan…` |
| Z8 | `X-Frame-Options` yok | 🔴 |
| Z9 | "farklı mı" ters | 🔴 3 test |

Düzenleyici JS'inin otomatik testi yok (Ç39); davranışı tarayıcıda ölçüldü.

## Ölçüm
- `bin/kontrol` YEŞİL: 105 test (öncesi 94).
- **Bulgu 1 (testte):** şablona `app` adlı değişken geçirmiştim → Twig'in
  global `app`'i ezildi; testte 500, üretimde flaşlar **sessizce** boş
  (ayrı ölçüm: `strict_variables=false` → `[]`). → tuzak §29, `uygulama`.
- **Bulgu 2 (tarayıcıda):** hata listesi formun dibinde kalıyordu,
  "Kaydedilmedi" yazıp neyin yanlış olduğunu göstermiyordu → sabit alt
  çubuğa taşındı.
- **Bulgu 3 (tarayıcıda):** değişen düzenleyici JS'i sayfada **eski**
  hâliyle çalıştı (sayfadaki betik 7154 bayt, elle `fetch` 7253) — dosyada
  Cache-Control yok, tarayıcı tahminle önbellekten kullandı; her dağıtımda
  da olurdu → tuzak §30, adrese içerik özeti (`?v=`), testi var.
- **Tarayıcı akışı (yerel, kullanıcının yerel panel hesabı):** liste "v3,
  yayındakiyle aynı" → başlık boşaltıldı, Kaydet → "adım 1 baslik: 1–200
  karakter metin olmalı" alt çubukta → başlık yazıldı; "+ Soru" ödemenin
  önüne eklendi (aşağı düğmesi kapalı), dolduruldu, bir adım yukarı
  taşındı; Kaydet → "Kaydedildi"; Yayınla → "v4 yayında"; API v4'te 7 adım,
  yeni soru 5. sırada. Sürüm tablosundan v3 "taslağa geri yükle" → Yayınla →
  v5 (orijinal 6 adım; yerel huni kullanıcının bıraktığı içerikte).
  Kaydedilmemiş değişiklikle sayfadan çıkma tarayıcının "Leave site?"
  uyarısına takıldı.
- Yan gözlem: v3'e yazılmış 2 ziyaret, açık duran huni sekmesinin geliştirme
  sunucusunca (Vite) kendiliğinden yeniden yüklenmesinden — yalnız
  geliştirmede; üretimde dev sunucu yok.
- Tarayıcı aracında iki tıklama sayfaya ulaşmadı / yanlış koordinat
  (benim yakınlaştırma hesabım) — tekrarla doğrulandı.

## Açık
Ç39 (eşzamanlı düzenleme koruması yok, JS testi yok) · Ç38 · Ç36 (metin
artık panelden düzeltilebilir) · E2.


**2026-10-26 (L11, b2):** klasik form düzenleyicisi ve form uçları silindi; `/panel/huniler/{app}/{ad}` Puck'ı açar — `puck-tek-duzenleyici-b2.md`.
