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
