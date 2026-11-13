# V6 — niş şablonları (2026-11-04)

**İstek:** V serisinin son maddesi (kullanıcı 2026-11-04: "start with V6";
öneri `rakip-gorsellik-arastirmasi.md`: "AI'ın en iyi öğretmeni iyi örnek").
Rakiplerde (FunnelFox, RevenueCat Funnels) niş başına hazır şablon var.

## Ölçüm (önce)

- Yeni huni yalnız iki yoldan açılıyordu: **boş** (`bosTaslak`: başlıksız
  karşılama + paywall) ya da başka bir hunin **kopyası**. Panel formunda
  "Kopyala" seçicisi, AI'da `yeni_huni` (`kaynak`).
- Tek iyi örnek `docs/decisions/ornek-uyku-hunisi.json` (uyku-ornek) — depoda
  ama hiçbir kod yolu onu okumuyor; içinde bu uygulamanın görsel kimliği,
  uydurma "örnek yorum" ve `example.com` bağlantıları var. Akışında kusur:
  `aliskanlik` → `kafein` yönlendirmesi var ama `kafein` sıradaki adım
  olduğu için kafein seçmeyen de görüyordu.

## Ne yapıldı

Dört şablon: **uyku** (Gece teması), **fitness** (Enerji), **dil** (Okyanus),
**finans** (Lacivert) — `core/config/huni/sablonlar/<anahtar>.json`
(`ad`, `aciklama`, `hazirTema`, `tanim`). Her biri 12–16 adım: kolay ilk soru
→ hedef → koşullu bilgi ekranı (cevaba göre metin/başlık) → `goster`
koşullu ekran (kafein, ekipman, sakatlık, konuşma, abonelik) → 1–5 ölçek →
yükleme → e-posta (izin kutusu) → kişiye göre başlıklı özet + projeksiyon
grafiği → paywall. Panel "Yeni huni" formunda **Başlangıç** seçicisi
(boş / Şablon / Kopyala grupları); API `GET …/huniler` → `sablonlar`,
`POST …/huniler` → `sablon`; AI `yeni_huni` → `sablon` (enum + açıklama).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Şablon depoda JSON dosyası, `Sablonlar` sınıfı okur | veritabanı tablosu; PHP sabiti | Kodla birlikte sürümlenir, insan ve AI okuyabilir; Fluent dizini de `config/huni/`'de |
| 2 | Seçim tanımı **kopyalar** | huniye `sablon` başvurusu | V2 karar 2 ile aynı: şablon değişince açılmış huniler sessizce değişmesin |
| 3 | Tema dosyada yalnız **hazır temanın adıyla** (`hazirTema`), açılışta `Tema::HAZIR_TEMALAR`'dan doldurulur | temanın değerlerini dosyaya yazmak | Bir olgu tek yerde (kural 7); uyku-ornek'in teması zaten `gece` + `hareket` idi |
| 4 | Plan dosyada boş, açılışta uygulamanın ilk planı | şablonda `monthly` | Plan uygulamaya ait (M1a); boş şablonla aynı kural (`ilkPlan`) |
| 5 | Şablonda **görsel yok** | uyku-ornek'in kapağını taşımak | Medya uygulamaya ait (E2.3): başka uygulamada yayın denetimi "görsel yok" der. Kapak/özet görselini AI stoktan ekler (yönerge V1) |
| 6 | **Yorum, istatistik, önce/sonra yok** | "Örnek yorum — yayından önce değiştir" | Değiştirilmesi unutulursa uydurma kanıt yayına çıkar (yanıltıcı reklam); yayın denetimi bunu YAKALAMIYOR (K6c ölçtü) — kural yalnız dosya testinde |
| 7 | `kaynak` ve `sablon` birlikte reddedilir | biri öncelikli | Sessiz öncelik yöneticinin beklemediği taslağı açardı |
| 8 | AI'da şablon listesi aracın şemasında (enum + açıklama) | ayrı `sablonlari_listele` aracı | Model ayrı çağrı harcamasın; liste kısa |
| 9 | Soru ekranlarına "neden soruyoruz" kutusu, girişlere güvence kutusu | ekranları boş bırakmak | İlk ölçümde 57 ekranın 24'ü boşluk uyarılıydı; uyarılı örnek AI'ı her ekranı tek tek düzeltmeye iter |

Kapsam dışı: tasarım yönergesi (`TasarimYonergesi`) değişmedi — aracın
açıklaması "metni ürüne göre yeniden yaz, görsel ekle" diyor. `ornek-uyku-hunisi.json`
kayıt olarak kaldı; şablon onun düzeltilmiş hâli (kafein → `goster`,
yorum ekranı yok, `uyanma-olcek` yalnız gece uyananlara).

## Ölçümler

- **Geçerlilik:** dört şablon da ilk denemede şablondan açıldı ve
  **yayınlandı** (`yayinla` → sürüm; yayın denetimi: kontrast, koşul
  başvuruları, plan) — `SablonlarTest`.
- **Simgeler:** şablonlardaki 40'tan fazla emojinin hepsi Fluent dizininde
  (cihazdan bağımsız görünür, V3a).
- **Gerçek araç yolu** (geliştirme DB'si, `app:huni-arac keepchat yeni_huni`
  `{"sablon": …}` → `ekran_goruntusu`, huni-dev + ekran kabı): dört huni
  açıldı, **57 adımın hepsi çekildi**.
  - İlk tur: uyku 16/16 uyarısız; toplam 33/57 uyarısız (24 boşluk, 2 yetim satır).
  - Kutular eklenince: 41 adımlı üç şablonda 24/41 uyarısız; yetim satırlar 0.
  - Kalan 17 uyarının hepsi "boşluk %36–44": Okyanus/Lacivert temaları
    `ferah` değil (aynı içerik Gece'de uyarısız — V2 bulgusuyla aynı) ve
    3 paywall (paywall'a blok eklenemiyor → M2). Görüntülerde metin okunur,
    tema doğru. → Ç107
- Panel formu yalnız birim testinde (Huniler.test 8/8) ölçüldü; **tarayıcıda
  açılmadı** (Puck dışı düz `<select>`/`<optgroup>`; tuzak §43/§42 alanı değil).
- `bin/kontrol` YEŞİL: 503 çekirdek testi / 4263 iddia; panel, huni,
  bileşen testleri ve derlemeler yeşil.

## Kırma denemeleri (15/15 kırmızı; K6 ve K13 ilk yamada KOŞULMADI)

| # | Bozulan | Düşen |
|---|---|---|
| K1 | paywall'a plan yazılmaz (sonra: birleşimde dosyadaki boş plan kazanır) | SablonlarTest 2 (iki biçimde de) |
| K2 | tema hazır temadan eklenmez | SablonlarTest + PanelHuniTest |
| K3 | kaynak + şablon birlikte kabul | SablonlarTest |
| K4 | bilinmeyen şablon → boş taslak | SablonlarTest + PanelHuniTest |
| K5 | şablona görsel | SablonlarTest 2 (dosya + yayın) |
| K6 | kutu → yorum bloğu | SablonlarTest 1 — ilk yama girinti yüzünden tutmadı (KOŞULMADI, §8); ikinci yama başka alanı bozuyordu (yanlış neden) → kutunun türü değiştirilerek yeniden |
| K6c | aynı yorum, yalnız yayın testi | **yeşil** — yayın denetimi uydurma yorumu geçirir; kural yalnız dosya testinde (karar 6) |
| K7 | Fluent'te olmayan simge (bayrak) | SablonlarTest |
| K8 | GET'te `sablonlar` yok | PanelHuniTest (sözleşme doğrulaması) |
| K9 | POST `sablon`'u geçirmez | PanelHuniTest |
| K10 | araç şemasında enum yok | HuniAraclariTest |
| K11 | araç `sablon`'u geçirmez | HuniAraclariTest |
| K12 | araçta `sablon` kimlik denetimi yok | HuniAraclariTest |
| K13 | şablonda olmayan adıma koşul | SablonlarTest (yayın) — ilk yama iki yerde eşleşti (KOŞULMADI) |
| K14 | panel isteğinde `sablon` yok | Huniler.test |
| K15 | panelde grup etiketi | Huniler.test |

PHPStan ilk koşuda `StokGorselTest`'in `HuniAraclari`'yı elle kurduğunu
yakaladı (yeni parametre) — filtreli koşumda o test yoktu.
