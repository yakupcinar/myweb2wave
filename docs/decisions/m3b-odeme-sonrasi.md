# M3b — ödeme sonrası ekran hunide düzenlenebilir (2026-11-04)

Karar `pre-setup.md` A16 ("ödeme sonrası sayfa = hunide düzenlenebilir ekran").

## Ölçüm (önce)

- `/keepchat/basarili/` sabit HTML: "Teşekkürler!", çekirdekten tek kullanımlık giriş
  bağlantısı (webhook geç gelirse 8 deneme), satın alma pikseli, yedek metin; tema oturum
  deposundan. İçerik hiçbir yerden düzenlenemiyor; ödeme dönüşü hangi huniden gelindiğini
  taşımıyor.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Tanımın kökünde `sonrasi: {baslik, bloklar, dugme?}` — adım değil | yeni adım türü | Ödeme sonrası ayrı sayfa (Stripe dönüşü); adım akışına/ilerleme çubuğuna karışmasın |
| 2 | Giriş bağlantısı alanı (düğme, kurulum notu, yedek metin) sayfanın **sabit** parçası; yönetici yalnız başlığı, blokları ve düğme metnini değiştirir | giriş düğmesini blok yapmak | Yanlışlıkla silinirse müşteri uygulamaya giremezdi |
| 3 | `sonrasi`'da soru bloğu ve koşullu değer yok | izin vermek | O sayfada cevap yok; koşul hep varsayılana düşerdi |
| 4 | Ödeme dönüş adresi ziyaretin hunisini taşır (`?huni=`); huni yalnız ziyaret bu uygulamanınsa | huniyi istemciden almak | Sunucu ziyaret kaydından bilir; başka uygulamanın hunisi açılmaz |
| 5 | Sayfa huninin YAYINDAKİ tanımını okur; `sonrasi` yoksa ya da okunamazsa varsayılan metin | sürümü sabitlemek | Basit; yayın değişmişse yenisi görünür (Ç115) |
| 6 | Düzenleyicide "Ödeme sonrası ekran" bileşeni (Ekran'ın blok yuvası), listenin sonunda; tanımda adımlardan ayrılır | kök alanları | Bloklar Ekran'la aynı düzenleme deneyimi |
| 7 | Bloklar görsel sahipliği denetimine girer (`Tanim::gorseller`) | — | Başka uygulamanın görseli kullanılamaz (E2.3) |

## Test

`SonrasiTest` (3): geçerli (düğmeli/düğmesiz); eksik alan, fazla alan, boş başlık, soru
bloğu, koşullu değer, bilinmeyen blok türü; blok görselleri sayılır. `GomuluOdemeTest`:
yayınlanmış hunideki ziyaretle dönüş adresi `?huni=fitness-2&session_id=…`, tanınmayan
ziyarette huni yok; servis başka uygulamanın ziyaretine huni vermez. Huni: ekran çizimi
(başlık, bloklar, düğme metni; giriş alanı yerinde; düğme metni yoksa varsayılan; HTML
yorumlanmaz). Puck: gidiş-dönüş, adımlara karışmaz, boş düğme metni yazılmaz; düzenleyici
kategori testi güncellendi.

## Kırma (9 deneme; K6 ilk koşuda TUTMADI → test eksikti)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | soru bloğu kabul | 🔴 |
| K2 | koşullu değer kabul | 🔴 |
| K3 | alan kümesi denetimi yok | 🔴 |
| K4 | `sonrasi` görselleri sayılmaz | 🔴 |
| K5 | dönüş adresi huni taşımaz | 🔴 |
| K6 | başka uygulamanın ziyaretinden huni | ilk koşu **🟢**: yapılandırmada tek uygulama, uçtan ölçülemiyordu → servisi `baska` uygulamayla çağıran iddia → 🔴 |
| K7 | başlık yazılmaz | 🔴 2 |
| K8 | düğme metni hep yazılır (boşsa da) | 🔴 |
| K9 | Puck'ta ekran adımlara karışır | 🔴 |

## Gerçek istek

- Fitness hunisine `sonrasi` (AI aracı) → `app:huni yayinla` v4; gerçek ziyaret
  (`POST /huni/ziyaretler`, huni + sürüm) → gerçek `POST /checkout/sessions` → Stripe'taki
  oturumun `success_url`'i `…/basarili?huni=sablon-fitness&session_id={CHECKOUT_SESSION_ID}`
  (oturum sonra kapatıldı).
- Chrome: `basarili/?huni=sablon-fitness&session_id=cs_test_…` → başlık "Antrenman planın
  hazır!", metin + iki madde, düğme metni "Uygulamayı aç"; ödenmemiş oturum → giriş düğmesi
  gizli, yedek metin görünür (giriş akışı değişmedi). İlk açılışta eski hâl göründü
  (tarayıcı modül önbelleği — sayfa aynı tarayıcıda M2d'de açılmıştı); yenilemede doğru.
- Tema doğrudan açılışta varsayılan (oturum deposu boş); gerçek akışta aynı sekme.

## Kontrol

`bin/kontrol` **YEŞİL**: 575 çekirdek testi / 4802 doğrulama.

## Açık

Ç115.
