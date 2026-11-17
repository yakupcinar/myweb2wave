# M1c — sabit tutarlı çok para birimi; Adaptive Pricing kapandı (2026-11-04)

Karar `pre-setup.md` A14 ("plan başına elle girilen sabit tutarlar, ziyaretçinin
ülkesinden seçilir, ziyaretçi seçmez; Adaptive Pricing kapanır") · önceki
bloklar `m1a-fiyat-katalogu.md`, `m1b-teklifler.md` · açık uç Ç19 (kapandı).

## Ölçüm (önce)

- Ziyaretçinin ülkesi hiçbir yerde bilinmiyordu (IP yalnız atıf eşleşmesi için
  `odeme_oturumlari`'na yazılıyor). `trusted_proxies: private_ranges`.
- Plan tek para birimi (Stripe fiyatı); Adaptive Pricing hesapta açık: TR
  ziyaretçisi paywall'da "9,99 €", ödemede "TRY 577,50 (%4 kur ücreti dahil)" (Ç19).
- `GET /huni/{app}` **`public, max-age=60`** — fiyat IP'ye bağlanınca ortak
  önbellek (CDN) bir ülkenin yanıtını ötekine verirdi.
- Stripe: elle girilen çok para birimi (`currency_options`) Adaptive
  Pricing'in önüne geçer; ödeme oturumunda `currency` verilirse o para birimi
  açılır (belge ölçüldü). `currency_options` fiyat güncellemesiyle değişebilir.

## Rakip araştırması (kullanıcı isteği: "rakipler nasıl yapıyor, ona göre seçelim")

| Rakip | Ülke | Seçim | Test |
|---|---|---|---|
| web2wave | **IP'den** (sağlayıcı açıklanmıyor) | ülke → para birimi listesi (çok birimli ülkede ilki); yoksa ana birim; tutar sayfa açılışında bir kez, ziyaretçi seçemez | `?currency=eur`, `?w2w_country_code=DE` |
| RevenueCat Web | "müşterinin konumuna en uygun" (yöntem açıklanmıyor) | yoksa uygulamanın varsayılanı | `?currency=EUR` |
| FunnelFox | yerel ayar kuralları (locale) | yerel ayar başına fiyat | `ff-locale` ("VPN'siz" — IP'ye işaret) |

Kaynaklar: docs.web2wave.com (multicurrency-auto-currency-switching),
revenuecat.com/docs/web/web-billing/multi-currency-support,
funnelfox.com/docs/editor/pricing-localization.md.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Ülke **IP'den**, **DB-IP IP-to-Country Lite** (kullanıcı 2026-11-04) | MaxMind GeoLite2 (hesap + lisans anahtarı + EULA); `Accept-Language` (ülke değil dil) | Rakiplerin yolu; hesap/anahtar yok. **Lisans ölçüldü:** CC BY 4.0, ticari serbest, sonucu kullanan sayfada bağlantılı atıf şart. Okuyucu `maxmind-db/reader` Apache-2.0 |
| 2 | Atıf: paywall'da fiyat varken yasal satırda "IP Geolocation by DB-IP" | her sayfada; gizlilik metninde | Sonucun kullanıldığı yer fiyat |
| 3 | Test parametresi `?ulke=DE` (web2wave'in `w2w_country_code`'u); yayında da çalışır | yalnız önizlemede | Önizlemede ödeme kapalı → ödemeyi ölçemezdi. Bedeli: ziyaretçi elle ucuz para birimini seçebilir (VPN'le de olur) → Ç109 |
| 4 | Ülke → para birimi: ülkenin ISO 4217'deki **ilk** resmî birimi desteklenen dörtten biriyse (EUR/GBP/TRY/USD); Panama (PAB+USD) gibi ülkeler ana birimde. Bulgaristan 2026-01-01'den EUR | her ülkeye en yakın birim | web2wave kuralı; bir ülke iki birimde değil (test) |
| 5 | Plan o para birimini taşımıyorsa **ana para birimi** (kullanıcı kararı) | her zaman USD | Her plana USD tutarı zorunlu olurdu |
| 6 | Paywall ve ödeme **aynı seçimi** kullanır; ödemede `currency` zorlanır | Stripe'ın kendi konum tahmini | İki tahmin (bizim IP, Stripe'ın) ayrışınca paywall ile ödeme farklı tutar gösterirdi |
| 7 | `adaptive_pricing: {enabled: false}` her oturumda, açıkça | hesap ayarına güvenmek | Kararın kodda ve testte görünmesi; hesap ayarı panelden açılsa da ödeme etkilenmez |
| 8 | Ek para birimi değişikliği **yeni Stripe fiyatı** (ana tutar aynı), eskisi pasif | var olan fiyatın `currency_options`'ını güncellemek | M1a kuralı: mevcut aboneler eski tutarda kalır — güncellenen fiyatta yenileme sessizce yeni tutara geçebilirdi (ölçülmedi; temkinli yol) |
| 9 | Ana tutar değişince (`Fiyatı değiştir`) ek para birimleri yeni fiyata aynen geçer | sıfırlamak | Yönetici yerel fiyatları sessizce kaybetmesin |
| 10 | **Ücretli giriş + çok para birimi birlikte yok** (iki yönden reddedilir) | giriş ücretine de `currency_options` | Giriş ücreti her para biriminde ayrı tutar ister; panel/sözleşme büyür → Ç109. Deneme ve yüzde indirim para biriminden bağımsız, çalışıyor |
| 11 | `GET /huni/{app}` **`private, max-age=60`** | `Vary` ile ortak önbellek | Ülkeyi taşıyan bir istek başlığı yok; bugün CDN de yok (A7'deki "CDN önbelleği" notu değişti) |
| 12 | ~~Ülke desteklenen birime denk gelmiyorsa ödemede fiyat **okunmaz**, para birimi verilmez (Stripe ana birimi açar)~~ → **2026-11-04 M2d'de değişti: her zaman zorlanır** — varsayım yanlıştı, Stripe zorlanmayanı kendi konum tahminiyle yerelleştiriyor (ölçüldü, tuzak §50, `m2d-gomulu-odeme.md`) | her ödemede okumak | ~~Gereksiz Stripe isteği yok; sonuç aynı~~ |
| 13 | DB-IP dosyası depoda değil: `app:ulke-verisi` indirir (bu ay, yoksa geçen ay), açar, **okuyup** yerine koyar; dağıtımda + ayın 3'ünde cron; dosya yoksa ana birim + günlükte uyarı | dosyayı depoya koymak (8 MB, aylık) | Lisans/atıf aynı; veri tazesi |

## Test

`UlkeTest` (3): ülke → birim (TR, DE, BG, HR, GB, JE, US, EC → beklenen; PA, JP,
CH → yok; elle), bir ülke tek birimde, test parametresi önce / biçimsizi yok /
dosyasız IP null. `ParaBirimiTest` (11): ek birimler yeni fiyat (`currency_options`
try 34999, eur 899; ana 2000 usd/ay aynı; eski pasif) · boş eşleme kaldırır ·
4 geçersiz (Stripe'a gitmez) · ana birim ek olamaz · giriş teklifi iki yönden ·
fiyat değişimi ekleri taşır · huni fiyatı TR/DE/JP/yok + `private` + panelde
`ekler` (sözleşme) · ödeme TR → `try`, DE → ana `usd`, JP → para birimi yok ve
fiyat okunmaz, Adaptive her oturumda kapalı. `HuniTest` önbellek beklentisi
`private`. Huni: `?ulke=TR` tanım isteğine ve ödeme gövdesine, biçimsizi
gitmez; DB-IP atfı fiyatla var, fiyatsız yok. Panel: ek birimler sütunu, form ana
birimi sormaz, mevcutla dolar, **silinen alan gönderilmez**.
`KayitliStripeIstemcisi.yanitlar` artık sorgu dizgesini (`expand`) yok sayar.

## Kırma (20 deneme, hepsi kırmızı; K21 ilk koşuda TUTMADI → test eksikti)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | ülke → birim eşlemesi yok | 🔴 3 |
| K2 | Bulgaristan EUR listesinde yok | 🔴 |
| K3 | test parametresi yok sayılır | 🔴 3 |
| K4 | gösterim para birimini seçmez | 🔴 2 |
| K5 | ana birim de ek sayılır | 🔴 |
| K7 | huni yanıtı yine `public` | 🔴 2 |
| K8 | ödemede `currency` yok | 🔴 |
| K9 | `adaptive_pricing` yok | 🔴 |
| K10 | birim yokken de fiyat okunur | 🔴 |
| K11 | yeni fiyat yerine var olan güncellenir | 🔴 2 |
| K12 | eski fiyat kapanmaz | 🔴 |
| K13 | fiyat değişiminde ekler kaybolur | 🔴 |
| K14 | girişli plana ek birim kabul | 🔴 |
| K15 | çok birimli plana giriş kabul | 🔴 |
| K16 | ana birim ek olarak kabul | 🔴 |
| K17 | huni `ulke`'yi tanım isteğine koymaz | 🔴 |
| K18 | huni `ulke`'yi ödemeye koymaz | 🔴 |
| K19 | DB-IP atfı yok | 🔴 |
| K20 | panel formu ana birimi sorar | 🔴 |
| K21 | panel boş alanı da gönderir | ilk koşu **🟢 TUTMADI**: test hiç alan silmiyordu (eklenmemiş birim zaten durumda yok) → test "silinen TRY gönderilmez" ile genişledi → 🔴 |

Yamalar bu kez Python listesinden uygulandı (zsh, §16); her yamanın eşleşme sayısı yazdırıldı.

## Gerçek istek (Stripe test API + geliştirme DB'si + gerçek DB-IP dosyası)

- `app:ulke-verisi` → `DBIP-Country-Lite, 8.1 MB`. Gerçek IP'ler: 8.8.8.8 → US → USD ·
  88.255.0.1 → TR → TRY · 81.2.69.142 → GB → GBP · 193.0.6.139 → NL → EUR ·
  2a00:1450:4001::1 → DE → EUR · 127.0.0.1 / Docker 172.18.0.1 → yok (geliştirmede `?ulke=`).
- Gerçek `Planlar::paraBirimleri` (KeepChat aylık, 9,99 EUR): yeni Stripe fiyatı
  `eur 999 + gbp 899 + try 34999 + usd 1099`, aynı ürün, eski fiyat `active=false`.
- Gerçek `GET /huni/keepchat` ve `POST /checkout/sessions` → Stripe'taki oturum:

| ülke | huni fiyatı | ödeme oturumu |
|---|---|---|
| TR | 349,99 TRY | try 34999, Adaptive kapalı |
| US | 10,99 USD | usd 1099 |
| GB | 8,99 GBP | gbp 899 |
| DE | 9,99 EUR | eur 999 |
| JP | 9,99 EUR (ana) | eur 999 |
| yok | 9,99 EUR | eur 999 |

  Hepsinde `Cache-Control: max-age=60, private`, `adaptive_pricing.enabled=false`.
- Teklifler: deneme 7 gün + TR → try **0**; %50 indirim + TR → 34999 − 17500 = **17499**
  (Stripe yine indirimi yukarı yuvarladı; paywall yüzde yazar, M1b karar 7); giriş
  ücreti çok birimli planda reddedildi. Sonra teklif kaldırıldı; dev planı ek
  birimlerle kaldı.
- Tarayıcı (Chrome, `?ulke=TR`, sablon-fitness paywall önizlemesi): fiyat
  "₺349,99 / ay", düğmenin altında "IP Geolocation by DB-IP".

## Yan bulgu: dağıtım betiği hiç çalışmıyordu (tuzak §49)

`deploy/dagit.sh`'in ön kontrolündeki Python kodu kabukta tek tırnak içinde;
2026-10-20'de (A6/2, `028f150`) eklenen yorumdaki kesme işareti (`$'ı`) tırnağı
erken kapatıyordu → `bash -n` hem macOS bash 3.2'de hem bash 5.2'de sözdizimi
hatası. Dağıtım o tarihten beri denenmedi (sunucu yok), fark edilmedi. Düzeltildi;
düzeltmeyi yazarken ikinci ve üçüncü kesme işaretini de yorumla soktum —
**çift sayıda olunca `bash -n` geçti** ama Python kodu bölünürdü; gömülü bloğu
çıkarıp `compile` ile ölçerek yakalandı. Şimdi: iki bash'te `bash -n` 0, gömülü
Python 21 satır, derleniyor.

## Kontrol

`bin/kontrol` **YEŞİL**: 537 çekirdek testi / 4631 doğrulama; huni 96+, panel, bileşen,
derlemeler. İlk koşu kırmızıydı: PHPStan 2 (test: `assertCount` önerisi; az önce `[]`
atanmış özelliği boş sanma) — düzeltildi. Disk %99 (3,8 GB boş).

## Açık

Ç109.
