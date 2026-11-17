# M4a — deneyler: atama ve panel yönetimi (2026-11-04 → 2026-11-09)

Karar `pre-setup.md` A17 · önceki `m3c-ek-urun-upsell.md` · sonraki M4b (sonuçlar).

## Ne yapıldı

Panelde **Deneyler** sayfası: taslak deney yazılır (ad, tür, huni, 2–4 varyant + yüzde
pay), Başlat / Durdur / Sürdür / Bitir. **Yönlendirme** deneyi bir hunin trafiğini
varyant hunilerine böler; **içerik** deneyi aynı hunide koşulda `deney.<ad>` olur. Huni
sayfası sekmesinde bir tohum üretir (`huni-tohum`, sessionStorage) ve tanım isteğine
ekler; çekirdek varyantı tohumdan hesaplar, yanıtta `deneyler: {ad: varyant}` döner;
oynatıcı bunu koşul özelliklerine ve ziyaret kaydına taşır; çekirdek atamayı
`ziyaret_deneyleri`'ne yazar (özellik deposunda `deney.*`). Sonuç ekranı yok (M4b).

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Kova = `sha256(deney|tohum)` ilk 8 hex mod 100; payların birikimli dilimi | rastgele + saklama | Saklamasız, aynı tohum aynı varyant; deney adı kovaya girer → iki deney birbirinden bağımsız |
| 2 | Tohum sekme deposunda, yalnız yayında (önizlemede yok) | çerez · localStorage | Ziyaret kimliğiyle aynı sınıf (Ç34) — yeni izin yüzeyi yok; bedeli: sekme/gün arası yapışık değil (Ç117) |
| 3 | Tohumsuz / biçimsiz tohum → deney dışı; `?huni=` yönlendirme deneyine girmez | varsayılan varyant | Doğrudan test adresi ölçümü kirletmesin; engelli depo ziyaretçisi rastgele dağılmasın |
| 4 | Yönlendirmede ilk varyant = deneyin hunisi (kontrol), huniler farklı | serbest | Kontrol grubu tanımlı olsun |
| 5 | Taslak dışı kilitli; taslağa dönüş yok; bitti son | serbest düzenleme | Çalışan deneyin varyantı değişirse sonuçlar karışır |
| 6 | Hunide tek aktif yönlendirme — **DB kısmi tekil indeksi** | yalnız uygulama kontrolü | Tuzak §4; iki deney aynı trafiği bölemez |
| 7 | Ziyarete yalnız o hunideki aktif deneyin geçerli varyantı yazılır | istemciye güvenmek | Gövde istemciden; eski sekme başka hunin atamasını taşıyabilir |
| 8 | Panel uçları sözleşmede, sayfa üretilen istemciyle | elle `fetch` + tipler | Tuzak §25 / tek kaynak |

## Geçmiş — kapı açılmadan commit (dürüst kayıt)

`296a78c` (2026-11-06, başka oturum) **CI kırmızıyla** commit + push edildi (kural 11
ihlali): PHPStan 12 hata (DB satırı `mixed`), huni 3 test (tanım adresine eklenen
`tohum`'u eski beklentiler bilmiyordu); karar kaydı, kırma ve panel testi yoktu; panel
uçları sözleşmede değildi. Aynı oturum içerik atamasını sessizce düşüren bir hatayı da
düzeltmişti (`array_column(…, 'huni', 'ad')` hunisiz içerik varyantlarını atıyordu).
2026-11-09'da: PHPStan tipleri, huni beklentileri + tohum testleri, panel sayfası
üretilen istemciyle + testi, çekirdek testleri 2 → 14, panel uçları sözleşmede, ve gerçek
istekte bulunan **`/panel/deneyler` 404** (sunucu rotası yoktu: menüden açılıyor,
doğrudan adres / yenileme 404 — hiçbir test görmüyordu). Yarım M4b taslağı commit dışı
yedeklendi (Ç117 (5)).

## Test

`DeneylerTest` (14): kova sınırı (Python'la bağımsız hesaplanmış kovalar, tuzak §3),
dağılım (2000 tohum, 90/10, ±4σ), tanım isteğinde yönlendirme + içerik + `?huni=` +
tohumsuz/biçimsiz (sözleşme), durmuş deney atanmaz, ziyarete yalnız geçerli atama,
biçimsiz gövde 422, varyant denetimleri (9 hata metni), taslak kilidi, geçişler
(sürdürmek başlangıcı değiştirmez — bir gün önceye çekilerek), yayın şartı, tek aktif
yönlendirme (uygulama + **DB kısıtı doğrudan**), koşulda `deney.*`, panel uçları
(giriş, doğrudan adres, CSRF, 404, sözleşme). `OzellikEsligiTest` +1 örnek (sunucu ↔
oynatıcı `deney.*`). Huni: tohum sekmede kalır / engelli depoda yok / önizlemede yok,
koşulda `deney.<ad>` (iki varyant), ziyarete atama gider / atamasızda alan yok. Panel
`Deneyler.test.tsx` (5).

## Kırma (24 deneme; ilk koşuda K5, K6 KOŞULMADI, K15 belirsiz)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | kova `<` → `<=` | 🔴 |
| K2 | deney adı kovaya girmez | 🔴 |
| K3 | `?huni=` yönlendirmeye girer | 🔴 |
| K4 | tohum biçimi denetlenmez | 🔴 |
| K5 | ziyaret hunisi denetlenmez | ilk koşu KOŞULMADI (yama eski koda göreydi, §8) → 🔴 |
| K6 | uydurma varyant yazılır | ilk koşu KOŞULMADI → 🔴 |
| K7 | aktif olmayan deney yazılır | 🔴 |
| K8 | taslak dışı kilit yok | 🔴 |
| K9 | aktif → taslak geçişi | 🔴 |
| K10 | aktife geçerken yayın denetimi yok | 🔴 |
| K11 | sürdürmek başlangıcı sıfırlar | 🔴 (test önce aynı saniyede koşuyordu → başlangıç geçmişe çekildi; deneme sonra) |
| K12 | bitiş zamanı yazılmaz | 🔴 |
| K13 | sözlükte `deney.*` yok | 🔴 |
| K14 | panel CSRF yok | 🔴 |
| K15 | ziyaret biçimsiz `deneyler` kabul | ilk yama süreci çökertti (sahte, §8) → yama değişti → 🔴 1 (422 yerine 500) |
| K16 | tohum sekmeye yazılmaz | 🔴 2 |
| K17 | önizlemede de tohum | 🔴 3 |
| K18 | oynatıcı `deney.*` üretmez | 🔴 |
| K19 | ziyarete atama gitmez | 🔴 |
| K20 | atamasız ziyarete boş `deneyler` | 🔴 2 |
| K21 | panelde taslakta Bitir | 🔴 |
| K22 | panel kontrol payını tamamlamaz | 🔴 |
| K23 | DB tekil indeksi yok (test DB'de düşürüldü, sonra yeniden kuruldu) | 🔴 2 |
| K24 | `/panel/deneyler` rotası yok | 🔴 |

## Gerçek istek (2026-11-09, dev)

- `GET /huni/keepchat?tohum=…` (b1deneme varsayılan; `acilis` b1deneme 50 / sablon-dil 50;
  `baslik` sablon-dil a/b): `tohum-0001` → `sablon-dil {acilis: dil, baslik: b}`,
  `tohum-0004` → `b1deneme {acilis: kontrol}`, `?huni=b1deneme&tohum=tohum-0001` /
  tohumsuz / `tohum=kisa` → `b1deneme`, deney yok — Python'un kovalarıyla (94, 33, 81) aynı.
- `POST /huni/ziyaretler` `{acilis: dil, baslik: b, sahte: x}` → `ziyaret_deneyleri` 2 satır,
  `app:ozellikler` `deney.acilis = dil`, `deney.baslik = b`; `sahte` yok.
- Chrome, `localhost:4321/keepchat/`: sekme tohumu UUID, tanım isteği `?tohum=<aynı>`;
  yenilemede aynı tohum, aynı huni (kova 10 → kontrol); ziyaret `acilis = kontrol` yazıldı.
- Panel sayfası tarayıcıda açılmadı (giriş — kullanıcı bakacak, Ç117 (4)); `/panel/deneyler`
  404 → rota eklendi, test K24.
- Dev'de iki deney (`acilis`, `baslik`) **durdu** bırakıldı.
