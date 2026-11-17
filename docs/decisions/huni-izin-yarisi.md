# L2 — İzin yarışı: geri alma uçuştaki Kabul'ü bekler (Ç58)

2026-10-26 · karar `pre-setup.md` A13.

## Ölçüm (önce)
`izinBildir` Kabul'de POST'u beklenmeden atıyordu; `izinGeriAl` aynı
`ziyaretSozu`'nu bekleyip DELETE atıyordu. Kabul → Gizlilik ayarları →
Reddet hızlı yapılırsa iki istek aynı anda uçar; DELETE önce varırsa POST
`izin_at` + tıklama kimliklerini geri yazar → tarayıcı "hayir", çekirdek
izinli, satın alma Meta/TikTok'a gider. Yeni test düzeltmeden ÖNCE
kırmızıydı: POST yanıtı bekletilirken DELETE gönderildi
(`expected [ { yontem: 'DELETE', … } ] to deeply equal []`).

## Karar
`izinBildir` istek bitince çözülen söz döndürür; oynatıcı son Kabul
sözünü `useRef`'te tutar, geri alma DELETE'ten önce onu bekler.
**Reddedilen:** sunucuda istemci sıra numarası (sözleşme + göç; tek sekme
içi yarış istemcide çözülüyor, kural 6a) · Kabul'ü de beklemek (kullanıcıyı
ağ için bekletir, gereksiz).

## Test
`Huni.test.tsx` "Kabul isteği uçuştayken geri alınırsa silme ondan SONRA
gider (Ç58)": sahte ağ artık söz dönen yanıtı bekletebiliyor; POST
bekletilir, Reddet'ten sonra DELETE yok; POST bitince tam bir DELETE,
tercih "hayir". Huni 40/40.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `await izinIstegi.current` kaldırıldı | 🔴 1 test (Ç58 testi, adıyla okundu) |
| K2 | söz saklanmıyor (`void izinBildir`) | 🔴 1 test |
| K3 | `izinBildir` istek bitmeden çözülüyor | 🔴 1 test |
| geri | — | 🟢 40/40 |

## Gerçek istek (tarayıcı, yerel çekirdek)
Sayfada betikle Kabul → Gizlilik ayarları → Reddet art arda. Çekirdek
günlüğü: POST `izin_at = coalesce` yazdı, DELETE 25 ms SONRA eşleşti ve
`izin_at = NULL`; DB son durum `izin_at` boş, `fbclid` boş. Uzantının ağ
kaydı iki isteği de 503 gösterdi — sunucu ikisini de işledi (tuzak §11;
`ilerleme`de de aynısı görüldü). Sayfa `fetch`'ini sarmalamak isteği
görmedi: istemci `fetch`'i yüklenirken yakalıyor (test dosyası notu).

Kalan: farklı sekmeler arası yarış (bir sekmede Kabul, ötekinde Reddet)
kapsam dışı — Ç56 (4) ile aynı sınır (tarayıcı kimliği yok).
