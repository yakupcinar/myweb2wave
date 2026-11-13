# M5a — yalnız ekleme olay deposu (Ç92, 2026-11-09)

Karar `pre-setup.md` A18 (kullanıcı: M5 önce olay deposuyla başlar) · önceki `t4-olay-deposu.md`.

## Ne yapıldı

T4'teki `ziyaret_olaylari` görünümü olayları kaynak tabloların **son hâlinden** türetiyordu.
Bu yüzden cevap değişimi, izin geri alma ve abonelik durum geçmişi kayboluyordu. Şimdi
`olaylar` tablosu yalnız ekleme alıyor (göç `Version20261109120000`). Yazma yolları olayı
kaynak satırla **aynı işlemde** `Olaylar::ekle()` ile yazıyor. `ziyaret_olaylari` aynı adla bu
tablonun görünümü olarak kaldı (Rapor P4 grafiği değişmedi). Sözlük ve veri biçimi aynı.

| Olay | Yazan | Ne zaman |
|---|---|---|
| `huni.ziyaret` (+ `izin.*` şeritte seçildiyse) | `Ziyaretler::kaydet` | ziyaret açılınca |
| `izin.verildi` / `izin.reddedildi` | `izinVer` / `izinGeriAl` | yalnız durum değişince (PG18 `RETURNING old.*`) |
| `adim.goruldu` | `ilerle` | adıma ilk ulaşınca |
| `cevap.verildi` | `ilerle` | seçenekler (bayt sırasıyla) ya da metin değişince; boş cevap olay değil |
| `odeme.baslatildi` | `oturumBagla` | oturum ziyarete bağlanınca |
| `odeme.tamamlandi` | webhook (`paid`) | oturumda ilk kez |
| `abonelik.durum` | webhook | durum değişince, bağlı her ziyarete; abonelik olayı oturumdan önce geldiyse bağlanma anında |

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Tablo, yazma yolunda PHP'den | DB tetikleyicisi | "Değişti mi" kararı (silip yeniden yazılan cevaplar) tetikleyicide satır satır görülmez; komşu kod açık SQL |
| 2 | Yalnız durum değişince olay | her istek olay | Geri gelip aynı cevabı seçmek ve tekrar izin vermek geçmiş değil, gürültü |
| 3 | Sıra `id`; aynı işlemdeki olayların zamanı aynı (`now()`) | zaman + ad sırası (T4) | T4'te `abonelik.durum`, `odeme.tamamlandi`'dan önce geliyordu; id yazılış sırası, yani neden önce |
| 4 | Akış yalnız şu an açık en eski işlemden önce bitenleri okur (`islem xid8`) | düz `id >` imleci | Bitmemiş işlemin küçük id'si sonradan görünür, imleç onu geçmiş olur (tuzak §53). Bedeli: uzun açık işlem akışı geciktirir. **⚠️ M7a'da yetersiz çıktı:** imleç `id` kaldıkça eski işlemin büyük id'li olayı yarım işleminkini atlatıyordu — imleç ve sıra `(islem, id)` oldu (`m7a-giden-webhook.md`) |
| 5 | Güncelleme tetikleyiciyle reddedilir; silme açık | silmeyi de yasaklamak | Ziyaret silinince (CASCADE) olay da gitmeli |
| 6 | Eski olaylar görünümden bir kez kopyalandı, T4 sırasıyla | boş başlamak | Rapor grafiği geçmişsiz kalırdı; geçmiş zaten yoktu, son hâl korunur |
| 7 | İmleç biçimi değişti (`[id]`) | eskisini okumak | Akışın dış tüketicisi henüz yok (M7) |

## Test

`OlaylarTest` 12 test. Bunlar gerçek yazma yollarından ve Stripe olaylarından (`SubscriptionSync::apply`) koşuyor:
çizelge sırası, serbest metin olaya girmiyor, geçmiş korunuyor, tekrar olay sayılmıyor
(cevap aile → iş → iş → boş, metin a → a → b, izin ver → ver → geri al → geri al → ver),
abonelik `active → past_due → canceled` geçiyor (sıra dışı eski olay yok sayılıyor),
oturumdan önce gelen abonelik ve iki kez gelen tamamlanma tek olay oluyor, güncelleme
reddediliyor (DB kısıtı, §4), **bitmemiş işlem**. Bu son testte ikinci bağlantıda açık işlem
var; arkasındaki biten olay da bekleniyor ve commit'ten sonra ikisi sırayla geliyor.
`PanelTest` günlük grafiği olayları kendisi yazıyor. 27 test dosyasının TRUNCATE listesine
`olaylar` eklendi.

## Kırma (18/18 🔴)

| # | Bozulan | Düşen |
|---|---|---|
| E1 | izin verme her seferinde olay | geçmiş |
| E2 | izin geri alma her seferinde olay | geçmiş |
| E3 | adım her seferinde | geçmiş |
| E4 | aynı cevap yine olay | geçmiş |
| E5 | boş cevap olay | geçmiş |
| E6 | aynı metin yine olay | geçmiş |
| E7 | serbest metin olaya girer | çizelge + geçmiş |
| E8 | seçenekler sırasız | çizelge |
| E9 | kayıtta izin olayı yok | 7 test |
| E10 | `odeme.baslatildi` yok | 6 test |
| E11 | tamamlanma her seferinde | oturumdan önce |
| E12 | aynı durum yine olay | abonelik geçmişi |
| E13 | bağlanınca durum yok | oturumdan önce |
| E14 | bağlanma tekrarında da durum | oturumdan önce |
| E15 | akış bekçisi yok | bitmemiş işlem |
| E16 | çizelge zaman + ad sırasıyla | 4 test |
| E17 | imleç "dahil" | 3 test |
| E18 | güncelleme tetikleyicisi yok (test DB'de düşürüldü, sonra kuruldu) | güncellenemez |

Hepsi iddia hatası; derleme ya da SQL hatası yok. E11 için önce test yoktu: aynı oturumun
iki kez gelen tamamlanması ilk koşudan ÖNCE teste eklendi. Kırmadan önce gözden geçirirken fark edildi.

## Gerçek sistem (2026-11-09, dev)

- Göçten önce görünüm: 488 olay, 7 ad. Göçten sonra tablo: 488 olay ve **aynı md5 parmak
  izi** (görünüm T4 sırasıyla, tablo id sırasıyla). id sırası zaman sırasına ters düşen çift: 0.
  `UPDATE olaylar …` → `olaylar yalnız ekleme alır`.
- Gerçek HTTP: `POST /huni/ziyaretler`, ilerleme aile → iş → iş, izin POST → DELETE → POST
  (hepsi 204). `app:olaylar` çizelgesi: `huni.ziyaret, adim.goruldu, cevap aile, cevap iş,
  izin.verildi, izin.reddedildi, izin.verildi`. T4 görünümü bunların yalnız son hâlini
  (cevap iş + izin.verildi) gösterirdi. Test ziyareti sonra silindi (olayları CASCADE ile gitti: 0).

## Açık

- Ç119: akış sonunda imleç `null` dönüyor ("bitti"). Canlı bir tüketici (M7 giden webhook)
  son konumunu tutmak isteyecek; bu, M7'de karar.
