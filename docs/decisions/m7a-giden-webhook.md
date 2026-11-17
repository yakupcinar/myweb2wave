# M7a — giden webhook motoru (2026-11-09)

Karar `pre-setup.md` A20 (kullanıcı: hedefler panelden uygulama başına, kişisel veri yok) · olay deposu `m5a-olay-deposu.md`.

## Ne yapıldı

- **Tablolar** (göç `Version20261109230000`): `webhook_hedefleri` (uygulama, adres, seçilen olay adları, sır, etkin, imleç
  `(son_islem, son_olay_id)`; yeni hedefin imleci kuruluş anındaki xmin, yani geçmiş gönderilmiyor) ve
  `webhook_teslimleri` (hedef × olay başına bir satır; durum bekliyor / teslim / vazgecildi, deneme, son hata, sonraki deneme).
  Akış için dizin `olaylar (app, islem, id)`.
- **`app:webhook:gonder`** (dakikalık cron, `App\Olay\GidenWebhook`) iki adımda çalışıyor:
  1. **kuyruga:** her etkin hedefin imlecinden sonraki, seçtiği adlardaki ve bitmiş işlemlere ait olayları sıraya alıyor.
     İmleç seçilmeyen adları da geçiyor; hedef satırı kilitli (eşzamanlı iki cron aynı olayı iki kez sıraya koymasın).
  2. **gonder:** zamanı gelen teslimleri POST ediyor. Gövde `{id, ad, ziyaret, veri, zaman}` (kişisel veri yok, A20 / Ç90),
     başlıklar `webhook-id: olay_<id>`, `webhook-timestamp`, `webhook-signature: v1,<base64 HMAC-SHA256>`
     (Standard Webhooks; sır `whsec_…`). 2xx teslim sayılıyor; değilse 30 sn × 2^deneme bekleniyor (en çok 1 sa), 10 denemede
     vazgeçiliyor. 10 sn zaman aşımı, yönlendirme izlenmiyor. **İç ağ adresine gidilmiyor** (`NoPrivateNetworkHttpClient`: panelden girilen
     adres sunucunun içine dokunmasın).
- **Tuzak §53 düzeltmesi + Ç119:** `Olaylar::akis` artık `(islem, id)` sırasında ve imleciyle okuyor (`Olaylar::BITMIS`).
  İmleç her zaman son verilen olayın yeri; `devam` ayrı bayrak.

## §53: ilk çözüm yetersizdi (ölçüm)

Motoru yazarken akıl yürütmeyle bulundu: `id` imleci + `islem < xmin` bekçisi, **eski işlem yarım işlemden sonra ekleyip
önce biterse** yarım işlemin küçük id'li olayını atlatıyor. Önce bunu ölçen test yazıldı
(`testEskiIslemBuyukIdIleYarimIsleminOlayiniAtlatmaz`). Eski kodda kırmızıydı ve okuma `huni.ziyaret, izin.reddedildi,
izin.verildi, izin.reddedildi` verdi. A iki kez okundu, T yalnız son sayfada imleç olmadığı için (Ç119) baştan yeniden okunduğundan
tesadüfen geldi. Doğru imleç Ç119'la birlikte `(islem, id)` oldu.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Hedef başına imleç + teslim satırı | olayları doğrudan göndermek | En az bir kez teslim, yeniden deneme ve günlük (M7b) için kalıcı kayıt |
| 2 | İmleç `(islem, id)` | `id` | §53 (yukarıda) |
| 3 | Yeni hedef geçmişi göndermez | baştan göndermek | Binlerce eski olay ilk dakikada alıcıyı boğmasın; isteyen M7b'de yeniden gönderir |
| 4 | Standard Webhooks imzası | kendi biçimimiz | Alıcı hazır kitaplıkla doğrular |
| 5 | İç ağ engeli | adres doğrulamasıyla yetinmek | DNS adı içeriye çözülebilir; kontrol bağlantı anında |
| 6 | Teslim sıralı değil, en az bir kez | tam bir kez / sıralı | Alıcı `webhook-id` ile tekilleştirir (spesifikasyonun önerisi) |

## Test

`GidenWebhookTest` (8): imza (spesifikasyonun örnek vektörü, Python'la doğrulandı — tuzak §3), imleç ve seçim (kuruluştan önceki
**seçilen addaki** olay gitmiyor, başka uygulama yok, ikinci tarama tekrar etmiyor), yarım işlem bekleniyor, eski işlem önce biterse
yarım işlemin olayı atlanmıyor, imzalı teslim (başlıklar, gövde anahtarları, teslim edilen yeniden gitmiyor), bekleme (30 sn, 4. denemede
240 sn, zamanı gelmeyen gitmiyor, 10'da vazgeçiliyor, pasif hedefe gidilmiyor), iç ağa gidilmiyor (istek hiç çıkmıyor), konsol.
`OlaylarTest` +2: eski işlem büyük id, iki bitmiş işlem işlem sırasıyla (sayfa 1). Var olan akış testleri `devam`'a geçti. TRUNCATE
listelerine `webhook_teslimleri` eklendi. Testte adres genel bir IP: koruma alan adını kendisi çözüyor, testte DNS olmasın; belge
aralıkları (TEST-NET) da engelli.

## Kırma (16/16 🔴, üçü ilk koşuda tutmadı)

| # | Bozulan | Sonuç |
|---|---|---|
| G1 | akış `id` imleci | 🔴 |
| G1b | akış `id` sırası (imleç doğru) | ilk koşu **🟢** → iki bitmiş işlem testi → 🔴 |
| G2 | akış bekçisi yok | 🔴 2 |
| G3 | son sayfada imleç yok (eski Ç119) | 🔴 2 |
| G5 | webhook `id` imleci | ilk koşu **🟢** → "eski işlem önce biter" testi → 🔴 |
| G9 | sır base64 çözülmez | 🔴 |
| G10 | gövdeye fazla alan | 🔴 |
| G11 | 2xx dışı da teslim | 🔴 |
| G12 | bekleme sabit | 🔴 (üstel ölçümü kırmadan önce eklendi) |
| G13 | vazgeçme yok | 🔴 |
| G14 | iç ağ koruması yok | 🔴 |
| G15 | pasif hedefe gider | 🔴 |
| G16 | yeni hedef geçmişi gönderir (varsayılan, test DB'de değiştirilip geri alındı) | ilk koşu **🟢**: kuruluştan önceki olay seçilmeyen addaydı → seçilen adda olay → 🔴 |
| G17 | webhook bekçisi yok | 🔴 |

Ölçülmeyen: hedef satırı kilidi ve `UNIQUE (hedef, olay)` iki eşzamanlı cron'a karşı. Tek iş parçacıklı testte gözlenemiyor,
kod olarak duruyor.

## Gerçek sistem (2026-11-09)

- Dev göçü uygulandı.
- Host'ta Python alıcı (imzayı kendi hesaplıyor) ← çekirdek kabından gerçek HTTP POST, aynı başlıklar ve `imza()`:
  `204`, alıcıda **`imza_dogru: true`**, gövde anahtarları `id, ad, ziyaret, veri, zaman`. İç ağ koruması yerel alıcıyı
  engellediği için bu ölçüm korumasız istemciyle yapıldı.
- Koruma gerçek DNS'le: `https://example.com` → 200 (genel adres geçiyor), `host.docker.internal` → engellendi.
- Cron tanımlı değil (üretim yok). Panel ekranı M7b'de.
