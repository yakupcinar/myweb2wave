# M9c — yeni huni görünümü: varsayılan tema, logo ve paywall (2026-11-10)

Karar `pre-setup.md` A23 (kullanıcı: yeni huniye kopyalansın) · önceki `m9b2-yasal-baglantilar.md`.

## Ne yapıldı

- **Huniler** sayfasında her huninin yanında **"Yeni hunilere örnek yap"**: o huninin teması, logosu ve **son**
  paywall adımı `uygulama_ayarlari.varsayilan`'a kopyalanır (göç `Version20261110240000`); satırda "yeni huni örneği"
  rozeti görünür.
- **Ayarlar**'da "Yeni huni görünümü" kartı: hangi huniden, ne zaman, neler; "Kaldır".
- **Yeni huni açılırken** ([`Huniler::yeniHuni`](../../core/src/Huni/Huniler.php) → [`Varsayilanlar::uygula`](../../core/src/Huni/Varsayilanlar.php)):
  - boş huni temayı, logoyu ve paywall'ın tamamını alır;
  - şablondan açılan huni temayı, logoyu ve paywall'ın uygulama alanlarını alır (`plan`, `planlar`, `onerilen`,
    `guvenceler`, `sartOnayi`, `sartlar`, `gizlilik`, `iptal`, `downsell`). Metinler şablonun kalır; varsayılanda
    olmayan uygulama alanı şablondan silinir;
  - kopya huni hiçbir şey almaz.
- AI'ın `yeni_huni` aracı da aynı yoldan geçer.
- **Uçlar:** `POST …/huniler/{ad}/ornek`, `GET` ve `DELETE …/varsayilanlar` (sözleşme `YeniHuniVarsayilani`).

## Ölçüm (önce)

- Tema ve logo huni tanımında; paywall huninin adımı (A15: ayrı paywall varlığı reddedilmişti); son adım paywall (M2c).
- Boş taslak bilerek eksik başlar: başlıklar boş, sonra yazılır.
- Şablonların hiçbiri paywall'da uygulama alanı taşımıyor (4 şablon denetlendi).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Kopya modeli | çalışırken miras | Kullanıcı; yayınlanmış sürüm donmuş kalır, "kim neyi gördü" ve deney sonuçları bozulmaz |
| 2 | Örnek bir huniden alınır | Ayarlar'da ayrı tema / paywall düzenleyicisi | Puck zaten düzenleyici; ikincisi kopyası olurdu |
| 3 | Son paywall | ilk paywall | Son adım paywall'dır (M2c); öndekiler cevaba göre özel paywall |
| 4 | Şablondan: yalnız uygulama alanları | paywall'ın tamamı | Şablonun niş metni kalsın; fiyat / güvence / yasal uygulamanın |
| 5 | Yalnız varsayılanın getirdiği **yeni** hatalar | taslağın tamamını doğrulamak | İlk yazımda bütün taslak doğrulanıyordu; boş taslak eksik olduğu için her boş huni reddedildi (testler yakaladı) |
| 6 | Geçersiz örnek huniyi açtırmaz, gerekçe söyler | sessizce atlamak | §12: yedek yol hatayı gizlerdi |
| 7 | "Varsayılan huni"den ayrı ad ve rozet | aynı kelime | İkisi farklı şey (yönlendirme ↔ başlangıç görünümü) |

## Test

- `VarsayilanlarTest` (9):
  - örnek alma (girişsiz, 404, jetonsuz 403, ayar satırı yokken 422) ve özet sözleşmeye uygun;
  - boş huni temayı, logoyu ve paywall'ın tamamını alıyor;
  - şablondan huni uygulama alanlarını alıyor, metinler şablonun;
  - kopya huni almıyor;
  - örnek değişince eski huni değişmiyor; "Kaldır"dan sonra boş taslak;
  - geçersiz örnekte huni açılmıyor;
  - şablonda olup varsayılanda olmayan alan siliniyor;
  - son paywall alınıyor (iki paywall'lı huni);
  - DB kısıtı.
- Panel: `Huniler.test.tsx` +1 (rozet yalnız kaynakta, eylem jetonla), `Ayarlar.test.tsx` +1 (kart, Kaldır jetonla
  DELETE).
- Tuzak §28 yeniden görüldü: tema JSONB'den anahtar sırası değişmiş döndü; karşılaştırma içerikle yapıldı.

## Kırma (13/13 🔴)

V1 uygulanmaz · V2 şablonda da tam paywall · V3 kopya da alır · V4 tüm taslak doğrulanır · V5 doğrulama yok · V6 eksik
alan silinmez · V7 tema yok · V8 logo yok · V9 ilk paywall · V10 kaldırmak silmez · V11 ayar satırı yokken başarılı ·
P1 rozet yanlış satırda · P2 Kaldır gitmez — hepsi 🔴. **V6 ve V9 ilk koşuda 🟢:** şablonlarda uygulama alanı yoktu ve
örnek hunide tek paywall vardı. Önce test suçlandı; servis düzeyi test ve iki paywall'lı test eklenince ikisi de 🔴.

## Gerçek sistem (2026-11-10, dev)

- `GET …/varsayilanlar` ve `POST …/huniler/genel/ornek` girişsiz 302.
- Dev'de `sablon-fitness`'ın tema + son paywall'ı varsayılan olarak SQL ile yazıldı (örnek alma ucu giriş istiyor,
  testlerde ölçüldü). `app:huni-arac keepchat yeni_huni` ile açılan huni:
  - tema birebir aynı;
  - paywall "Kas planına başla" = kaynağın **son** (16.) paywall'ı;
  - ilk adım boş taslağın `karsilama`'sı.
  - Ölçüm hunisi silindi, varsayılan boşa döndü.
- Panel sayfaları tarayıcıda denenmedi (giriş).
- Rehberdeki 5 satır bağlantısı bu bloğun kodu yüzünden kaydı (tuzak §58). Yeni `bin/belge-baglantilari` denetimi
  `bin/kontrol`'e eklendi (`belge-baglanti`); `--duzelt` numaraları tanım satırına taşıdı. Kırması: kırık dosya ve
  kaymış satır, ikisi de çıkış 1.
- `bin/kontrol` YEŞİL, core 732 test.
