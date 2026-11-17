# T3a — koşul motoru: kural tabanlı yönlendirme (2026-11-03)

Kullanıcı 2026-11-02: "contiune with next" (T2'den sonra T3). T3 büyük; T1 gibi dilimlendi: **T3a** koşullu yönlendirme (bu blok) · T3b koşula göre adım göster/atla · T3c koşullu değer. Girdi T2 özellikleri (`t2-ozellik-deposu.md`).

## Karar

- **Biçim düz** (düzenleyici ve AI kolay yazsın): ekranda isteğe bağlı `yonlendirme: [{baglac?: hepsi|herhangi, kosullar: [{ozellik, islem, deger?}], hedef}]` — 1–10 kural, kural başına 1–5 koşul. JS koşulu yok (bildirimsel).
- **Öncelik:** ilk tutan kural → seçeneğin hedefi → ekranın hedefi → sıradaki adım.
- **Çekirdek denetler** (`Tanim.php`): özellik T2 sözlüğünde; `odeme.*`/`abonelik.*` reddedilir (hunide tarayıcı bilmez, ödemeden sonra); `cevap.<adım>` yalnız bu ya da önceki ekranın sorusu; işlem türe uygun (seçmeli cevap: icerir/icermez, diğerleri: esit/esit_degil, hepsi: var/yok); seçmelide değer gerçek seçenek kimliği (yazım hatası sessizce hiç tutmayan kural olurdu); `huni.surum` tam sayı; hedef yalnız ileri.
- **Yokluk:** esit tutmaz, esit_degil tutar; icerir tutmaz, icermez tutar (`huni-bilesenleri/kosul.ts`).
- **Oynatıcı** çekirdeğe giden olayların kopyasını tutar; karar anında T2b'nin `ozellikler()` işleviyle aynı özellikleri hesaplar (ikinci model yok). Çizim tetiklemez (`useRef`).
- **Puck:** ekranda "Koşullu yönlendirme" dizi alanı; özellik seçenekleri sabit liste + bu ve önceki ekranların soruları. Akış denetimi kural hedefini yol sayar; kimlik değişince kural hedefi ve koşuldaki `cevap.<eski>` taşınır.
- **AI:** `Tanim::EKRAN_YONERGESI` kuralları anlatır, "yalnız kişiselleştirme gerçekten gerekiyorsa".

## Ölçüm

- Gerçek çekirdek: yerel `genel` taslağına `kiminle` için kural (`cevap.kiminle icerir is → paywall`) `app:huni-arac taslak_kaydet` ile kabul edildi.
- Tarayıcı (Chrome, önizleme adresi, `huni-dev` yeniden başlatıldıktan sonra — §38): "İş için" → paywall (sıra 1 → 5, 2–4 atlandı); geri tuşu → kiminle; "Ailemle" → sıradaki adım (cihaz). Ölçümden sonra taslak yedekten geri yüklendi (kural kaldı mı: hayır).
- Yan bulgu (ölçüm aracında): `/i[şs]/i` "İş" düğmesini bulamadı — §2'nin JS düzenli ifade hâli; ölçüm tam metinle yapıldı.

## Kırma denemeleri (11/11 kırmızı; 2'si ilk koşuda tutmadı → test düzeltildi)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `cevap.<sonraki adım>` reddi kalktı | ilk koşu 🟢 — test fault: örnekteki `ara` adımında soru yoktu, "soru yok" dalı reddediyordu; `ara`'ya soru konunca 🔴 |
| K2 | odeme/abonelik reddi yok | 🔴 |
| K3 | seçenek değeri denetimi yok | 🔴 |
| K4 | işlem türü ayrımı yok | 🔴 2 test |
| K5 | herhangi = hepsi | 🔴 |
| K6 | son tutan kural kazanır | 🔴 |
| K7 | icermez yoklukta tutmaz | 🔴 |
| K8 | seçenek hedefi kuraldan önce | ilk koşu 🟢 — hiçbir testte seçeneğin kendi hedefi yoktu; yeni test "tutan kural seçeneğin hedefinden önce gelir" → 🔴 |
| K9 | olaylar koşula gitmiyor | 🔴 |
| K10 | kural hedefi yol sayılmıyor (akış denetimi) | 🔴 |
| K11 | `cevap.<eski>` yeniden adlandırmada taşınmıyor | 🔴 |

Her deneme betikle: eşleşme sayısı 1 değilse KOŞULMADI (§8), sonra dosya geri yazıldı; `git diff --stat` öncesiyle aynı.

## Kontrol

`bin/kontrol` yerelde (macOS, Docker) 251 s, EXIT=0, `KONTROL: YEŞİL` — core 388 test / 3.007 doğrulama, panel 81, bileşen 91, huni 79.

## Açık

Ç91: düzenleyicinin özellik listesi sözlüğün elle kopyası (eşlik testi yok) · Puck kural alanları tarayıcıda denenmedi.

# T3b — koşullu gösterim: ekranı göster / atla (2026-11-03)

Kullanıcı 2026-11-03: döngü, "a'dan j'ye hepsini sırayla yap". T3a'nın koşul biçimi aynen; yeni olan yalnız ne zaman hesaplandığı.

## Karar

- Ekranda isteğe bağlı `goster: {baglac?, kosullar}` (sözleşmede `KosulGrubu`; `Yonlendirme` = grup + hedef). Tutmazsa ekran atlanır, sıradaki adıma geçilir; ardışık atlananlar da (döngü). Paywall son adım ve alanı yok → döngü hep biter.
- **Cevap yalnız ÖNCEKİ adımlardan** (gösterim ekran çizilmeden hesaplanır): çekirdekte `kosul(..., $i - 1)`, Puck'ta özellik listesi bu ekranı içermez.
- **İlk adımda yok:** hep gösterilir; ilk sıra + geri tuşu (`replaceState`) mantığı değişmedi.
- **Geçmiş:** atlanan ekran `pushState`'e girmez — geri tuşu gösterilen son ekrana döner.
- Ortak değerlendirici `grupTutar` (kosul.ts); `yonlendir` onu kullanır.
- Puck: düz iki alan `gosterBaglac` + `gosterKosullar` (boş liste = alan tanıma yazılmaz). Akış denetimi: koşullu ekrandan sonraki adım da yol; yeniden adlandırmada koşuldaki `cevap.<eski>` taşınır.
- AI yönergesine bir cümle.

## Ölçüm

- Yerel `genel` taslağında `oncelik` ekranına `cevap.kiminle icerir aile` (çekirdek kabul etti). Chrome, önizleme, `huni-dev` yeniden başlatıldıktan sonra: "İş için" → cihaz → **hazir** (oncelik atlandı); geri → cihaz, geri → kiminle (atlanan geçmişte yok); "Ailemle" → cihaz → **oncelik**. Sonra taslak yedekten geri yüklendi (goster kaldı mı: hayır).
- Puck alanı tarayıcıda denenmedi: panel girişi kullanıcının kendi hesabı (Ç91).

## Kırma denemeleri (10/10 kırmızı; 1'i deneme kusuru → düzeltildi)

| # | Bozulan | Sonuç |
|---|---|---|
| B1 | ilk adımda goster serbest | 🔴 (göster ilk adımda) |
| B2 | bu ekranın cevabı serbest | 🔴 (göster kendi cevabı) |
| B3 | goster bağlacı denetimsiz | 🔴 |
| B4 | grup herhangi = hepsi | 🔴 2 test |
| B5 | oynatıcı atlamıyor | 🔴 |
| B6 | yalnız bir ekran atlanır | 🔴 (ardışık iki ekran) |
| B7 | akış: atlanan ekranın ardı yol değil | 🔴 |
| B8 | gösterim koşulunda cevap taşınmıyor | 🔴 |
| B9 | boş gösterim tanıma yazılır | 🔴 18 test |
| B10 | AI yönergesinde goster yok | ilk deneme 🟢 — yama yalnız cümlenin başını silmişti, testin aradığı metin duruyordu (§8); cümle tamamen silinince 🔴 |

## Kontrol

`bin/kontrol` yerelde 207 s, EXIT=0, `KONTROL: YEŞİL` — core 395 test / 3.045 doğrulama. İlk iki koşu kırmızıydı: biçim (huni-bilesenleri'nde `format` betiği yok, yalnız `format:check` — prettier doğrudan) ve PHPStan (test yardımcısı `mixed` üzerinde).

# T3c — koşullu değer (2026-11-03)

Platform planı (`web2wave-platform-yapisi.md` §4c): "her blok ve ekran özelliği kurala bağlanabilir — kural = koşullar + değer".

## Karar

- Ekranda ve blokta isteğe bağlı `degerler: [{baglac?, kosullar, alan, deger}]` (sözleşmede `KosulluDegerler`, 1–20 kural). **Kural başına tek alan** — Puck dizisi ile tanım bire bir (gidiş-dönüş aynı); alan başına ilk tutan kural, hiçbiri tutmazsa alanın kendi değeri.
- **Alan öğede VAR olmalı** (yokken değer vermek görünmeyen alan açardı) ve dize olmalı: ekran `baslik, dugme, gorsel, gorselMetni`; blok için `BLOKLAR`'dan türetilir (uzunluk kuralı olan alanlar + `gorsel`, `simge`). Liste/sayı alanları (maddeler, puan) yok.
- **Değer alanın kendi kuralıyla** denetlenir (uzunluk, görsel kimliği, tek emoji) — mevcut denetleyiciler.
- **Soru bloğunda yok:** seçenek kimlikleri koşulların dayanağı; değiştirilebilir dize alanı da yok. Açık denetim yalnız ileti için: kaldırılınca da "değiştirilemez ()" reddi kalıyor (C4, §10).
- Cevap yalnız önceki adımlardan (ortak `kosulGrubu`, sınır `$i - 1`); ilk adımda serbest (UTM vb. ilk ekranda da bilinir).
- Oynatıcı çizimde uygular (`kisisel`, `degerleriUygula`); her adım değişimi yeniden çizer. Bileşenler `degerler`'i hiç görmez.
- Puck: ekran ve altı blokta "Koşullu değerler" dizisi (koşullar + alan seçimi + yeni değer); yeniden adlandırmada `cevap.<eski>` taşınır. Blokların özellik listesi T3a'nın sabit listesi (yuvadaki bloğun ekranını Puck vermiyor) — yanlış cevap adı kayıtta çekirdekte reddedilir (Ç91).

## Ölçüm

`genel` taslağında `hazir` ekranının başlığı ve paragrafı `cevap.kiminle icerir is` ile. Chrome önizleme: "İş için" → hazir başlığı "Ekibin için hazırız", paragraf kuralın metni; geri → kiminle, "Ailemle" → hazir kendi metinleri (yeni cevap eskisini değiştirdi, kural tutmadı). Taslak geri yüklendi.

## Kırma denemeleri (9/9 kırmızı)

C1 alanın öğede var olması · C2 değer alanın kuralıyla · C3 bu ekranın cevabı serbest · C4 soru bloğunda değer (ileti farkıyla düştü, bkz. yukarı) · C5 son tutan kazanır · C6 oynatıcı uygulamıyor · C7 blok değerleri uygulanmıyor · C8 Puck blok değerleri tanıma yazılmıyor · C9 yeniden adlandırmada cevap taşınmıyor. Test yardımcısı (PHPStan için) değişince C1–C4 yeniden koşuldu: yine kırmızı.

## Kontrol

`bin/kontrol` 212 s, EXIT=0, `KONTROL: YEŞİL` — core 406 test / 3.109 doğrulama. Önceki koşu PHPStan'da kırmızıydı (test yardımcısı `mixed` üzerinde).

# Ç91 — düzenleyicide koşul özellikleri sunucudan, koşullu değerde cevaplar (2026-11-03)

- **Elle kopya kalktı:** düzenleyicinin özellik listesi (`SABIT_OZELLIKLER`) silindi; açılış ucu (`/panel/api/uygulamalar/{app}/huniler/{ad}/duzenleyici`, sözleşmede `ozellikler`) sözlükten gelir — `Tanim::kosulOzellikleri()`: `Ozellikler::SOZLUK` sırasıyla, `odeme`/`abonelik` (hunide bilinmez) ve `cevap.*` (ekranlardan eklenir) hariç. Etiket "açıklama (ad)".
- **Koşullu değerlerde cevaplar:** ekranda ve altı blokta (`DEGERLI_BLOKLAR`) önceki ekranların `cevap.<kimlik>`'i. Blok kendi ekranını Puck'ın `resolveFields` → `parent`'ından bulur (Puck 0.23 türlerinden ölçüldü).
- **Bulunan kusur (testle):** ekranı bulunamayan blokta `findIndex` −1 → `slice(0, -1)` "sondan biri hariç bütün ekranlar"ı veriyordu; artık cevap yok.
- **Tarayıcı (kullanıcının oturumu):** `genel` taslağında `hazir` ekranının paragrafına kaydedilmemiş bir koşullu değer eklendi: özellik listesi sunucunun 9 adı + `cevap.kiminle`, `cevap.cihaz`, `cevap.oncelik` (kendi ekranı yok), etiket "reklam kaynağı (utm.source)"; geri al ile taslak "Yayındakiyle aynı" — hiçbir şey kaydedilmedi.
- **Kırma (4/4 kırmızı):** Ç1 sunucu listesi kullanılmaz (ilk koşu KOŞULMADI — prettier satırı bölmüştü, §8) · Ç2 hunide bilinmeyen özellik listede · Ç3 blokta `parent` yok sayılır · Ç4 −1 koruması yok.
