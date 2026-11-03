# V3b — stok fotoğraf, lisans denetimiyle (2026-11-03)

**İstek:** kullanıcı 2026-11-03: "stok fotoğraf kullan, lisans denetimiyle".

## Ölçüm: sağlayıcılar (belgelerden, 2026-11-03)

| Sağlayıcı | Lisans / kural | Sonuç |
|---|---|---|
| **Pexels** | Lisans: ticari kullanım ve değiştirme serbest, atıf zorunlu değil. Yasak: tanınabilir kişiyi kötü/saldırgan göstermek, kişi ya da markanın ürünü **onayladığını ima etmek**, değiştirmeden satmak, stok sitesinde yeniden dağıtmak, marka/logo olarak kullanmak. API: anahtar gerekli (hesap), 200 istek/saat · 20.000/ay; "aramanın yanında Pexels'e görünür bağlantı", "mümkünse fotoğrafçıya atıf"; yeniden barındırma yasaklanmamış | **Seçildi** |
| Unsplash | API görseli **kendi adresinden sunmayı** (hotlink) ve indirme ucunu tetiklemeyi şart koşuyor | Elendi: ziyaretçi IP'si üçüncü tarafa gider, medya hattımızla (yeniden kodlama) çelişir |
| Openverse | Anahtarsız, görsel başına lisans (CC0/PDM) | Elendi: 3 sorgu 60 sn'yi aştı, ikinci deneme hatayla bitti; "sleeping woman bed" için resim/düğün fotoğrafı — pazarlama hunisine uygun değil |

## Kararlar

| # | Karar | Neden |
|---|---|---|
| 1 | Yalnız Pexels; `medya.kaynak` CHECK ile yalnız `pexels` | Lisansı ölçülmemiş kaynak giremez |
| 2 | İçe almada görsel bilgisi **Pexels API'sinden** (istemciden değil); indirme yalnız `https://images.pexels.com/` (yönlendirme yok) | İstemci başka adres/lisans yazamaz; SSRF kapalı |
| 3 | İndirilen görsel yüklenen gibi yeniden kodlanır (`Medyalar::yukle`: içerikten tür, boyut sınırı, GD → WebP, EXIF atılır) | Tek güvenli hat |
| 4 | Kaynak, sayfa, fotoğrafçı, lisans ve lisans adresi görselle saklanır (kısıt: kaynaklıysa hepsi dolu); panel görsel alanında "Foto: X · Pexels Lisansı" bağlantılı | "Bu nereden, hangi lisansla?" her an cevaplanır; fotoğrafçıya atıf (API kuralı) |
| 5 | Panel aramasının yanında "Fotoğraflar: Pexels" bağlantısı | API kuralı |
| 6 | AI araçları `stok_gorsel_ara` / `stok_gorsel_al` yalnız anahtar varken listelenir; tasarım yönergesi: ilk ekrana kapak, özet ekranına görsel; **stok kişiyi müşteri/yorum sahibi/önce-sonra gibi gösterme** | Lisanstaki "onay ima etme" yasağı mekanik denetlenemez — yönerge + araç açıklaması |
| 7 | Anahtar yalnız `core/.env.local`'de (kullanıcı yazar); boşsa panel 503 + açıklama, araçlar gizli | Hesap açmak ve anahtar girmek kullanıcının işi |

## Ölçümler

- Testler sahte Pexels'le (Pexels belgesindeki foto nesnesi biçimi): arama
  sorgusu ve `Authorization` başlığı, 1880×2820 JPEG → 1067×1600 WebP,
  kaynak/lisans listede, yabancı görsel adresi indirilmez, jetonsuz 403,
  Pexels 500 → 422 ve kayıt yok, kısıt (§4), anahtarsız araç yok, AI aracı
  tam sayı kimlik ister. Panel: arama → tıklama → jetonlu içe alma → alan
  yeni kimlik; atıf bağlantıları.
- **Gerçek Pexels ile KOŞULMADI** — anahtar yok (Ç103).

## Kırma denemeleri (12/12 kırmızı; ikisi testi düzeltti, biri KOŞULMADI'dan tekrar)

T1 görsel sunucusu süzgeci — ilk denemede **yeşil**: yabancı adrese giden
indirme başka sahte fabrikaya düşüp bozuk yanıt olarak reddediliyordu, test
"kayıt yok"u ölçüyordu, "istek gitmedi"yi değil → yabancı adrese hiç istek
gitmediği eklendi, kırmızı. · T2 yeniden kodlama · T3 lisans kaydı · T4
`Authorization` başlığı (ilk yama biçimlendirici yüzünden tutmadı, §8) ·
T5 sorgu sınırı · T6 CSRF · T7 araç görünürlüğü · T8 kimlik türü · T9
listede kaynak · T10 panel jetonu · T11 Pexels bağlantısı · T12 yönergede
onay kuralı — ilk denemede yeşil (yönerge testi bu cümleyi aramıyordu),
eklendi, kırmızı.

## Ortam notu

`bin/kontrol` bir kez 10 dakikada bitmedi (adımlar yeşil, süreç çıkmadı;
artık kap 17 dk açıktı); adımlar tek tek 8 sn ve 19 sn'de çıktı, yeniden tam
koşu 192 sn YEŞİL. Sebep bulunamadı — disk %98 (4,2 GB boş, §14) → Ç105.
