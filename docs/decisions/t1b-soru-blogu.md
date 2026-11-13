# T1b — ekranda soru bloğu

Kullanıcı 2026-11-02: "T1b ile devam et". T1a'dan kalan (Ç83 (2)): ekran bloklardan kurulabiliyordu ama cevap toplayamıyordu — açıklama, görsel ya da güvence notu gereken soru ayrı adımlara bölünüyordu.

## Karar

- Katalogda 8. blok **`soru`**: soru adımının yanıt alanları (`secimTuru`, `secenekler`, `duzen`, `yanitZorunlu`) — soru metni ekranın başlığı/blokları. Seçenek şeması sözleşmede ortak `SoruSecenegi`'ye çıkarıldı (adım ve blok aynı).
- **Ekranda en çok bir soru bloğu; cevap ekranın kimliğiyle kaydedilir.** Sunucu cevabı adım kimliğiyle saklıyor ve tanıma bakmıyor (`Ziyaretler::ilerle`) → uç, tablo, rapor değişmedi (Ç85 onay).
- **Tek seçim:** seçenek seçilince geçer; ekranda düğme ve ekran hedefi YOK (hedef seçenekte) — çekirdek ikisini de reddeder. **Çoklu / serbest yanıt:** ekranın düğmesi gönderir (seçim yokken / zorunlu yanıt boşken kapalı), ekranın hedefine gider. Sözleşmede `dugme` artık isteğe bağlı; kural çekirdekte.
- Ortak bileşen: soru adımının yanıt alanı `Yanit` olarak çıkarıldı; soru adımı ve soru bloğu aynı öğeyi çizer (soru adımının DOM'u değişmedi — önizleme testleri ölçtü).
- Oynatıcı: `sorusu(adim)` — soru adımı ya da ekrandaki soru bloğu aynı tanımlayıcıya iner; seçim, çoklu seçim, serbest yanıt ve bildirim tek yoldan.
- Puck: `SoruBlogu` yuvada; alanları soru adımınınki. Hedef listesi kökte bulunmayan bloğun ekranını bulur. Akış denetimi tek seçimli soru bloğunun seçenek hedeflerine dallanır; kimlik değişince yuvadaki seçenek hedefleri de taşınır.
- AI: `Tanim::EKRAN_YONERGESI` soru bloğunu ve "tekli seçimde dugme/hedef yazma" kuralını anlatır.

## Ölçüm

- Gerçek sistem: KeepChat `genel` taslağına (yedekle karşılaştırıldıktan sonra) ızgara + emoji seçenekli tek seçimli soru bloğu + not kutusu olan ekran yazıldı, `ekran_goruntusu` ile çekildi: düğme yok, seçenekler bloklar arasında. Taslak geri yüklendi: tanım ve imza yedekle AYNI.
- Bilinen fark: Puck önizlemesinde çoklu seçimli ekranın düğmesi açık çizilir (Ç84).
- Puck düzenleyicisi tarayıcıda ölçülmedi (Ç83 (4) sürüyor).

## Kırma denemeleri (17/17 kırmızı)

| # | Bozulan | Sonuç |
|---|---|---|
| B1 | ekranda iki soru serbest | 🔴 iki soru bloğu |
| B2 | tek seçimde düğme serbest | 🔴 |
| B3 | çoklu seçimde düğme zorunlu değil | 🔴 |
| B4 | tek seçimde ekran hedefi serbest | 🔴 |
| B5 | blok seçenek kuralları koşmaz | 🔴 hedef geriye |
| B6 | seçmelide seçenek zorunlu değil | 🔴 |
| B7 | blok seçenek görseli medya denetimine girmez | 🔴 (önce test yoktu → eklendi) |
| B8 | oynatıcı ekrandaki soruyu görmez | 🔴 2 test |
| B9 | çoklu ekran hedefi yok sayılır | 🔴 |
| B10 | ekran düğmesi kapanmaz | 🔴 2 test (ilk yama ödeme düğmesini de tuttu: KOŞULMADI → yama daraltıldı) |
| B11 | tek seçimde düğme çizilir | 🔴 |
| B12 | akış denetimi blok sorusunu bilmez | 🔴 |
| B13 | yeniden adlandırma yuvaya inmez | 🔴 |
| B14 | hedef listesi ekranı bulmaz | 🔴 (ilk yama derleme hatasıydı: SAHTE → düzeltildi) |
| B15 | boş düğme tanıma yazılır | 🔴 |
| B16 | çoklu blokta seçenek hedefi kalır | 🔴 |
| B17 | AI isteminde soru bloğu yok | 🔴 — ilk koşuda YEŞİL: test yalın adı arıyordu, "soru" kelimesi metnin başka yerinde de geçiyor; test "ad (" katalog biçimine sıkılaştırıldı |

Ortam: yerel Docker, macOS; her deneme dosyayı yedekleyip geri yükledi.

CI (bağımlılıklar kilit dosyasından sıfırdan): koşu 41432906740, SHA 75f7232, `KONTROL: YEŞİL` (core 354 test, panel + huni testleri dahil).
