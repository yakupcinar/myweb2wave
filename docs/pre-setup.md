# Kararlar ve NEDEN'leri

> Her karar: **ne** · **neden** · **reddedilen alternatifler ve sebebi** ·
> tarih. Karar değişirse eskisi silinmez, üstü çizilir ve yenisi
> gerekçesiyle altına yazılır.

## §A — Ürün

**A1 · Tek kiracı: kendi uygulamalarımız için** (2026-10-19, kullanıcı
kararı). ~~Huniler kodda tanımlanır, görsel editör yok.~~ → **A7 ile değişti
(2026-10-20): huni panelden yönetilir.** Tek kiracı geçerli. Reddedilen: başka
geliştiricilere SaaS — çok kiracılı, editörlü, aylar süren iş ve web2wave'le
doğrudan rekabet; "önce kendim sonra SaaS" — şimdiden tenant_id taşımak
ölçülmemiş bir ihtiyaca altyapı kurmak.

**A2 · İlk hedef: kullanıcının mevcut iOS uygulaması** (2026-10-19). Hangi
uygulama olduğu ve web'den ödemenin gerekip gerekmediği açık: Ç9.

**A3 · Ödeme: Stripe TEST modu** (2026-10-19). Şirket Türkiye'de; gerçek
ödeme yok. ⚠️ Stripe Türkiye şirketine canlı hesap açmıyor (ikincil
kaynaklar, resmi listeden ölçülmedi) — canlıya geçiş ayrı karar: Ç10.
Kullanıcı teyidi (2026-10-19): test Stripe'la sürer; canlıda başka sağlayıcı
çıkarsa entegrasyonun yeniden yazılması kabul. Alıcılar **uluslararası** →
vergi/hukuk etkisi `odeme-saglayici-arastirmasi.md`, doğrulama Ç12.

**A4 · Birden çok kendi uygulamamız: çekirdekte UYGULAMA boyutu**
(2026-10-19, kullanıcı: "KeepChat'e bağlarım ama başka app'lerim de olacak").
A1 değişmez (hâlâ tek kiracı, SaaS değil); ama abonelik, fiyat ve huni artık
**hangi uygulamaya ait** olduğunu taşır. A1'de "tenant_id ölçülmemiş ihtiyaç"
diye reddedilen şey bu değil: ihtiyaç kullanıcıdan geldi, müşteri değil
uygulama boyutu.
- **Kimlik çekirdekte, tek kişi tüm uygulamalarda aynı** (e-posta kodu,
  sonra Apple, Google — kullanıcı kararı 2026-10-19); **hak (entitlement)
  uygulama başına**.
- **KeepChat'in kullanıcılarında e-posta yok** (ölçüm: `users` = username +
  bcrypt). KeepChat kendi girişini korur; uygulamada bir kez "aboneliğimi
  bağla" (e-posta → kod) ile KeepChat kullanıcısı çekirdek kimliğine bağlanır.
  Yeni uygulamalar kendi kimlik sistemi kurmadan doğrudan çekirdeği kullanır.
- Reddedilen: (a) her uygulama kendi aboneliğini tutar — web ödemesi tek
  yerde (Stripe), hak da tek yerde olmalı; (b) KeepChat'in girişini çekirdeğe
  taşımak — çalışan bir sistemin kimliğini değiştirmek, ayrı ve büyük karar.

**A5 · Abonelik erişimi, bağlama ve sorgu** (2026-10-19, kullanıcı:
"önerilerinle devam et"; girdi `rakip-arastirmasi.md`).
- **Erişim (Ç24):** `active`, `trialing` **ve `past_due`** erişim verir;
  `unpaid`, `canceled`, `incomplete`, `incomplete_expired` vermez. Stripe'ın
  yeniden deneme süresi kullanıcıya kartını güncelleme zamanı tanır.
  Reddedilen: yalnız `active`/`trialing` (web2wave yolu) — ödeme bir kez
  düştüğü anda ücretli kullanıcıyı kilitler; RevenueCat/Adapty/FunnelFox
  da grace'te erişimi sürdürüyor. ⚠️ Stripe panelinde "tüm denemeler
  düşünce aboneliği iptal et" ayarı ölçülmeli (Ç20).
- **Bağlama (Ç25):** şimdilik yalnız **e-posta + 6 haneli kod**. Tek
  kullanımlık bağlama bağlantısı, Universal Link/derin bağlantı altyapısı
  kurulunca (Ç27 ile). Reddedilen: şimdi bağlantı — KeepChat'te derin
  bağlantı altyapısı yok, kod her yerde çalışır.
- **Sorgu (Ç26):** "aktif mi?" sorusunu **uygulamanın sunucusu** sorar
  (KeepChat sunucusu → çekirdek), **uygulama başına gizli anahtarla**;
  uygulamanın içinde sır yok. Reddedilen: uygulamaya gömülü anahtar
  (web2wave yolu) — anahtar ikili dosyadan çıkarılabilir; kullanıcı
  jetonuyla uygulamadan sorgu — KeepChat'in kendi oturumu var, iki oturum.
- **Ertelenmiş derin bağlantı / MMP (Ç27):** şimdi YOK. Ücretli reklama
  başlamadan önce yeniden karar.

**A6 · Analitik ve reklam atfı: C · karışık** (2026-10-19, kullanıcı:
"önerilerinle devam et"; seçenekler Ç28, `rakip-arastirmasi.md`).
- Huni olayları, tıklama kimliği (`fbclid`/`ttclid`) ve UTM **bizim
  Postgres'te** — gerçeğin kaynağı bizde (KVKK/GDPR: ham veri yurt dışına
  çıkmaz).
- Reklam ağına dönüşüm **çekirdekten**, Stripe webhook'u geldiğinde (Meta
  Conversions API, TikTok Events API) — rakiplerin hepsi böyle.
- Görselleştirme: önce çekirdekte basit sayım tabloları; hacim gerektirirse
  AB'de barındırılan ya da kendi sunucumuzda bir araç (ölçülmedi).
- **Ne zaman:** canlı ödemeden ve ücretli trafikten **önce**; çerez onayı
  (Ç12) aynı blokta.
- Reddedilen: A (yalnız hazır araç) — ham veri ve dönüşüm üçüncü tarafta,
  yurt dışı aktarım; B (yalnız kendi panel) — reklam ağına dönüşüm yine
  gerekir, rapor yükü baştan bizde.

**A7 · Huni panelden yönetilir: E1'den başla, hedef E2** (2026-10-20,
kullanıcı; araştırma `decisions/huni-editoru-arastirmasi.md`, Ç37). A1'in
"huniler kodda, görsel editör yok" kısmını **değiştirir**; tek kiracı
aynen kalır. Huni tanımı veritabanında; yayınlanan sürüm değişmez anlık
görüntü; tanım yayından önce çekirdekte doğrulanır; huni sayfası statik
kabuk + yayınlanmış tanımı çalışma anında okur (yayın anında canlı; ilk
boyama ölçülecek, kötüyse FunnelFox gibi yayında statik derlemeye geçilir).
Önizleme imzalı, süreli bağlantıyla. Serbest HTML yok — ölçüm motoru
(adım, cevap, reklam, ödeme) her bileşende otomatik kalır. **Reddedilen:**
E3'e doğrudan gitmek (A/B, çok dil çerez/KVKK ister, trafik yok) ·
serbest HTML editör (GrapesJS: ölçümü ve sözleşmeyi atlar, XSS) · yayında
statik derleme ile başlamak (sunucuda derleme hattı; sunucu bile yok).

**A8 · Etikete özel huni: tek adres + panelde kurallar; içerik, tema ve görsel huni başına** (2026-10-20, kullanıcı;
öneri Ç40). Bir uygulamanın birden çok hunisi olur (ör. `genel`, `fitness`, `is`); her
birinin kendi soruları, **teması** (renk, yazı tipi, köşe) ve **görselleri**. Reklam
adresi tektir (`/keepchat/`); hangi huninin açılacağını panelde sıralı **kurallar**
seçer: adresteki bir parametre (`k`, `utm_source`, `utm_campaign`, `utm_content`)
şu değere eşitse → şu huni; hiçbiri tutmazsa varsayılan huni. Seçimi **çekirdek**
yapar (kurallar orada); huni sayfası yalnız bu parametreleri gönderir — `fbclid`
her tıklamada farklı, tüm adresle seçmek önbelleği öldürür. Her huninin test için
doğrudan adresi (`?huni=<ad>`). Ziyaret hangi huniyi gördüğünü taşır. **Reddedilen:**
etiket başına ayrı adres (kampanyayı yönlendirmek reklamı düzenlemeyi ister, Meta
yeniden incelemeye alabilir) · seçimi tarayıcıda yapmak (kurallar istemciye sızar,
iki kaynak). **Sıra (E2):** E2.1 çok huni + kurallar · E2.2 tema · E2.3 görseller ·
E2.4 sürükle-bırak (Puck) ve bileşen kütüphanesi · sonra A/B (aynı kural mekanizması
+ ağırlık).

**A9 · Hak kaynağı RevenueCat** (2026-10-20, kullanıcı; Ç42). "Kim Pro" bilgisinin
tek kaynağı web + App Store için RevenueCat; uygulama içi satın alma (IAP)
doğrulamasını ve Apple bildirimlerini RevenueCat yapar (Ç29'daki "kendimiz
yazarız" kalktı). Bizde kalır: huni, atıf, ödeme sayfası, Stripe webhook'ları,
panel. Aktarım: Stripe abonelik olayı geldiğinde, e-postası bu uygulamada bir
kişiye bağlıysa çekirdek aboneliği RevenueCat'e yollar
(`POST /v1/receipts`, `X-Platform: stripe`, `fetch_token` = `sub_…`,
`app_user_id` = kişi UUID'i); bağlama (e-posta kodu) başarılı olduğunda o
e-postanın abonelikleri de yollanır. Gönderim önce aynı işlemde **giden
kutusuna** yazılır, sonra denenir; başarısız olan komutla yeniden denenir —
RevenueCat'in kesintisi Stripe kaydımızı durdurmaz. Uygulama RevenueCat
SDK'sında `appUserID` = kişi UUID'i kullanır. Bizim hak ucumuz (A5) uygulama
RevenueCat'e geçene kadar yedek olarak kalır. **Reddedilen:** hak kaynağı
bizde kalsın + IAP'ı kendimiz doğrulayalım (Ç29; Apple bildirimleri, iade,
sürüm farkları — tekerleği yeniden icat) · web alıcısına satın almada rastgele
RevenueCat kimliği verip sonra birleştirmek (takma ad yönetimi; kişi UUID'i
zaten var).

**A10 · Ödeme sonrası tek kullanımlık giriş bağlantısı** (2026-10-21, kullanıcı;
Ç46, Ç25'in sırası geldi). Başarı sayfası ve ödeme e-postası uygulamayı
`<şema>://giris?jeton=…` ile açar; uygulamanın sunucusu jetonu çekirdekte
kişiye çevirir, uygulama `Purchases.logIn(kisi)` yapar — e-posta yazmadan,
şifresiz. Jeton rastgele 256 bit, özeti saklanır, **tek kullanımlık, 24 sa**;
başarı sayfası yalnız ödemeden sonraki 60 dk'da, oturum başına en çok 3 jeton.
E-posta kodu (A5) kurtarma yolu olarak kalır. **Reddedilen:** Stripe işlem
kimliğini RevenueCat kimliği yapıp bağlantıyla taşımak ("Lazy Auth" istemi —
kimlik adreste, süresiz, çok kullanımlık; A9'un reddettiği takma ad
birleştirmesi) · Universal Link (aynı alandan açılmaz, Team ID + AASA ister;
sonra Ç47) · parmak izi / MMP ile kurulumu aşmak (Ç27). Kayıt:
`docs/decisions/giris-baglantisi.md`.

**A11 · Huni dallanması: seçenek → hedef adım, yalnız ileri; skor yok**
(2026-10-21, kullanıcı; B4/H5). Soru seçeneği ve tanıtım düğmesi isteğe bağlı
`hedef` (tanımda **daha sonraki** bir adımın kimliği) taşır; yoksa sıradaki
adım. Tanıtım düğmesinin hedefi dalların ortak adımda **birleşmesi** için
(yoksa bir dalın son adımı öteki dala düşerdi). Paywall tam bir kez ve en
sonda kalır. **Reddedilen:** adım görünürlük koşulu (FunnelFox gibi; VE/VEYA,
daha karmaşık alan ve doğrulama) · ikisi birden (çelişen kurallar) · geriye
hedef (döngü; doğrulama tek geçişte biter) · skor (puan + sonuç metni ya da
puana göre plan — ölçülmüş ihtiyaç yok, kural 6a; puana göre plan ödeme
kuralını değiştirirdi). Kayıt: `docs/decisions/huni-dallanma-h5.md`.

**A12 · Reklam ölçümü: Meta + TikTok, piksel + sunucu, herkese onay şeridi,
yalnız abonelik olayı** (2026-10-21, kullanıcı; B4/H6, A6'nın uygulanışı).
Hunide herkese Kabul/Reddet şeridi; tıklama kimlikleri yalnız izinle
saklanır. Abonelik başlayınca çekirdek Meta Conversions API + TikTok Events
API'ye bildirir (yalnız izinli ziyaret); tarayıcıda Meta Pixel + TikTok Pixel
yalnız izinden sonra, platformların standart satın alma olaylarıyla
(`Purchase` / `CompletePayment`) sunucuyla aynı `event_id` (tekilleştirme).
**Reddedilen:** yalnız Meta (önerilen; kullanıcı ikisini seçti) · GA4 (analiz
bizde, A6) · yalnız sunucu (önerilen; kullanıcı eşleşme kalitesi için piksel
de istedi — hunide üçüncü taraf betik, onaydan sonra) · konuma göre onay
(ülke tespiti + hangi ülkeler hukuki karar) · onaysız test · huni olayları
(ViewContent, InitiateCheckout — daha çok veri paylaşımı). Metinler hukuken
doğrulanmadı (Ç12). Kayıtlar: `docs/decisions/huni-izin-h6a.md` (H6a),
H6b, H6c.

**A13 · İnceleme kararları paketi** (2026-10-26, kullanıcı; Ç58–Ç62 ve
eski açık uçlar). Yapılacaklar: Ç58 izin yarışı **istemcide sıralanır**
(reddedilen: sunucu sıra numarası — sözleşme + göç, ölçülmüş ihtiyaç yok) ·
Ç59 terk edilen ödemede IP/UA `checkout.session.expired` + 48 sa cron
temizliği · Ç60 dev DB'ye göç + `bin/kontrol`'de **uyarı** (kırmızı değil;
CI'da dev DB yok) · Ç62 `reference.php` `.gitignore` · Ç61 Puck'ta yalnız
3 hazır soru türü + benzersiz kimlik; paywall fayda listesi + yasal
bağlantı alanları, görünür geri düğmesi, logo + masaüstü düzeni, okunur
gizlilik bağlantısı · Ç55 ulaşılamayan adıma **uyarı** (yayın engellenmez;
reddedilen: yayında reddetmek) + yeniden adlandırmada hedef güncelleme ·
Ç44 alt metin (silme yok) · Ç53 b2 klasik düzenleyici silinir · Ç39
eşzamanlı kayıt 409 · Ç41 8 sa oturum + uyarı, huni arşivleme · Ç35
oturumlar DB'de (**2FA şimdilik hayır**) · Ç34 ziyaret hız sınırı +
sekmede ziyaret sürdürme · Ç23 ölü bağlantı denetimi · Ç14 Dependabot ·
Ç19/Ç21 fiyat tek kaynak Stripe; **Adaptive Pricing AÇIK kalır**
(kullanıcı 2026-10-26, ilk "kapat" kararını geri aldı; → **A14 ile kapanır**, 2026-11-03) · Ç56-3 izin oranı raporu · Ç18 webhook bütünlük
testi · Ç20 past_due test clock · Ç54 yöne göre geçiş · Ç50 Preact
denemesi (ölç, iyiyse geç). Reddedilen / ertelenen: Ç32 bağlama geçmişi.
Dış girdi bekleyenler (sunucu, anahtarlar, hukuk, KeepChat, anket metni)
döngüde atlanır. Blok bitince kural 11 ile commit + push, sorulmaz.

**A14 · Para modülleri: sıra, para birimi, ödeme yeri, teklifler**
(2026-11-03, kullanıcı; plan `web2wave-platform-yapisi.md` § Ek 2026-11-03).
- **Sıra M1 → M2 → M3 → M4** (fiyat kataloğu → ayrı paywall → downsell/
  upsell → deneyler); sonra M5–M11. Reddedilen: önce raporlar (M5), önce
  iptal/portal (M6).
- **Para birimi: plan başına elle girilen sabit tutarlar** (Stripe
  `currency_options`, ziyaretçinin ülkesinden seçilir, ziyaretçi seçmez).
  ⚠️ A13'teki "Adaptive Pricing AÇIK kalır" kararı **değişti → kapanır**
  (otomatik çeviri €18,47 gibi tutar verir ve paywall kesin yerel fiyatı
  gösteremez). Reddedilen: Stripe otomatik çeviri; tek para birimi.
  **Ülke kaynağı (2026-11-04, kullanıcı; rakip araştırması):** IP'den, DB-IP
  IP-to-Country Lite (CC BY 4.0 — paywall'da atıf); test `?ulke=DE`; desteklenmeyen
  ülke → planın ana para birimi. Reddedilen: MaxMind GeoLite2 (hesap+anahtar),
  tarayıcı dili. Kayıt `m1c-cok-para-birimi.md`.
- **Ödeme paywall sayfasında, gömülü** (Stripe gömülü form + Apple/Google
  Pay; M2 alt bloğu). Apple Pay alan adı doğrulaması sunucuyla. Reddedilen:
  Stripe'ın barındırılan sayfasına yönlendirme (bugünkü yol, M2'ye dek kalır).
- **M1 teklif türleri: üçü de** — kartlı ücretsiz deneme, ücretli giriş
  dönemi, ilk dönem indirimi (kupon). ⚠️ Giriş dönemi **değişti
  (2026-11-04, kullanıcı)**: Subscription Schedule değil, **deneme günleri +
  ilk faturada tek seferlik ücret** — schedule ödemeden sonra webhook'ta
  kurulur, kurulamazsa müşteri süresiz giriş fiyatında kalırdı. Reddedilen:
  Subscription Schedule. Kayıt `m1b-teklifler.md`.

**A15 · M2 paywall: bölünme, sayaç, cevaba göre paywall** (2026-11-04, kullanıcı).
- **Sayaç yalnız gerçek bitiş tarihine** (herkes için aynı, yöneticinin girdiği an;
  süre dolunca teklif gerçekten kalkar → bitiş teklifin kendisinde, ödeme de uyar).
  Reddedilen: ziyaretçi başına süre (rakiplerde yaygın; gerçek süreye bağlı değilse
  yanıltıcı ticari uygulama riski, yönergemiz de yasaklıyor) · sayaçsız.
- **Cevaba göre paywall = hunide birden çok paywall adımı** + mevcut koşullu
  yönlendirme (T3); "tam bir kez, en sonda" kuralı kalkar. Reddedilen: ayrı paywall
  varlığı (yeni tablo, sürümleme, panel sayfası — M3/M4 isterse yeniden bakılır).
- **Sıra:** M2a plan seçici + ödeme koşulu → M2b teklif bitişi + sayaç, güven
  rozetleri, şart onayı → M2c birden çok paywall → M2d gömülü ödeme + Apple/Google
  Pay. Reddedilen: önce gömülü ödeme (Apple Pay sunucusuz ölçülemez).

**A16 · M3 downsell / upsell** (2026-11-04, kullanıcı).
- **Downsell tetiği:** paywall'da geri tuşu + gömülü ödeme panelini ödemeden kapatma;
  ziyaretçi başına bir kez. Reddedilen: masaüstü çıkış niyeti (mobil trafikte yok), yalnız panel.
- **Upsell:** ödeme sonrası **tek seferlik ek ürün** (panelde ek ürün kataloğu), kayıtlı kartla
  tek tık; tutar düğmede açık, banka isterse 3D Secure. Reddedilen: üst plana geçiş
  (abonelik değişikliği, M6 ile çakışır), upsell'siz.
- **Ödeme sonrası sayfa = hunide düzenlenebilir ekran.** Reddedilen: sabit sayfa.
- **Bölünme:** M3a downsell → M3b ödeme sonrası ekran → M3c ek ürün kataloğu + tek tık upsell.

**A21 · M7c yönetim API / MCP** (2026-11-09, kullanıcı: dördü de).
- Okuma: MCP rapor araçları (özet, kaynak, kohort, Meta ROAS, cevap, deney sonuçları) — anahtarın uygulamasıyla
  sınırlı; olay akışı API'si (`GET /apps/{app}/olaylar`, uygulama anahtarı, imleçli).
- Yazma: MCP'de abonelik (iptal planla / geri al / iade) ve deney (oluştur / başlat / durdur) araçları — **yalnız
  izinli anahtar** (panelde anahtar oluşturulurken "Abonelik ve deney yönetimi"; var olanlar izinsiz kalır), araçlar
  yıkıcı işaretli (istemci her çağrıda onay sorar). Reddedilen: tüm anahtarlar, iadeyi MCP dışında tutmak.
  Bölünme: M7c1 okuma → M7c2 yazma.

**A22 · M8 e-posta** (2026-11-10, kullanıcı: önerilere göre).
- Sağlayıcı **Postmark** (API). İşlem e-postası `outbound`, dizi / pazarlama `broadcast` akışı. Reddedilen: Resend
  (kolay ama genç), SES (ucuz ama sandbox + SNS + itibar yükü bugün). Dizi motoru bizde, sağlayıcı yalnız teslim.
- Bastırma: Postmark'ın listesi esas, yerel kopyası `posta_bastirma` (Subscription Change webhook'u + 406 olayı).
- İzin **karma**: huninin izin kutusu + izin kanıtı; adres ödeme ya da kodla kanıtlanınca dizi. Reddedilen: tek onay
  (itibar), çift onay (terk edilen ödeme dizisini fiilen öldürür). İYS hukuk görüşü bekliyor.
- M8b kurtarma bağlantısı: Stripe'ın kurtarma adresi (oturum 1 saatte dolar). Reddedilen: kendi uçumuzda yeni oturum
  açmak (fazla kod; ölçüm kopyanın metadata, dönüş adresi ve e-postayı koruduğunu gösterdi), huniye dönmek
  (anket baştan başlar). İndirim yok (teklif kuralı açık değil).
- Sıra: M8a aktivasyon (işlem e-postası, izin gerekmez) → M8b terk edilen ödeme kurtarma. Deneme bitiyor
  hatırlatması önce Stripe'ın kendi ayarıyla.

**A23 · M9 proje ayarları** (2026-11-10, kullanıcı: dört ayar alanı da; roller şimdilik yok — tek yönetici).
- Bölünme: M9a uygulama kimliği → M9b yasal veriler + metinler → M9c varsayılan tema/paywall → M9d dil/ülke yönlendirmesi.
- M9a: ad, URL şeması, App Store adresi, destek e-postası panelde (`uygulama_ayarlari`); yaml / .env'den taşındı.
  Uygulama listesi yaml'da kalır (sözleşme enum'u). Reddedilen: yaml'da bırakmak (panelden değişmez), DB + yaml
  yedeği (§12, hangisinin geçerli olduğu belirsizleşir). Reddedilen roller: ikinci kişi yokken ölçülmemiş ihtiyaç.
- M9b (kullanıcı: taslak + düzenlenebilir; şartlar, gizlilik, KVKK, çerez; tr + en): metinler çekirdekten
  `/yasal/{app}/{belge}/{dil}`, kaydedilmeden yayında değil, yer tutucularla. Reddedilen: yalnız kullanıcının metni
  (boş başlangıç), dış adres (sistemle eşleşmez), taslağı otomatik yayınlamak (hukuki görüş değil).
- M9c (kullanıcı: yeni huniye kopyalansın): bir huninin teması, logosu ve son paywall'ı "yeni hunilere örnek" olarak
  kopyalanır; boş huni hepsini, şablondan huni tema + logo + paywall'ın uygulama alanlarını alır, kopya huni almaz.
  Reddedilen: çalışırken miras (yayınlanmış sürüm donmuş olmaktan çıkar, deney sonuçları belirsizleşir), tema miras +
  paywall kopya; Ayarlar'da ayrı tema / paywall düzenleyicisi (Puck'ın kopyası olurdu).

**A28 · M6c duraklat / sürdür** (2026-11-12, kullanıcı: üç öneri de).
- **Duraklatılan abonenin erişimi kapanır:** hak sorgusu `aktif: false, durum: paused`. Stripe'ta tahsilat durur
  (`pause_collection.behavior = void`), abonelik `active` kalır. Reddedilen: erişim sürsün (bedava dönem olur).
- **Süre seçilir: 1 / 2 / 3 ay**; Stripe `resumes_at`'te kendisi sürdürür; elle erken sürdürme var. Reddedilen:
  süresiz (unutulan abonelik sonsuza dek duraklı kalır).
- **Yalnız panelden** (Aboneler satırı, M6a gibi). Reddedilen: müşteri kendi duraklatsın (huni akışı, büyük iş; portal
  Stripe'ın, orada duraklatma yok).

**A27 · Rapor sekmeleri ve onay kutusu** (2026-11-11, kullanıcı: "Rapor çok uzuyor… tiklenen şeyler çok modern
durmuyor").
- Rapor web2wave gibi **sekmelere** bölünür: Genel bakış · Anket · Kaynaklar · Gelir · Ziyaretler; sekme adreste;
  Anket'te tek huni seçilir. Reddedilen: tek sayfa + katlanır bölümler (yine uzun). Ölçüm: sayfa 6.604 px, Anket
  1.710, Ziyaretler 1.268, Cevaplar 796.
- Onay kutusu **özel çizim** (`.kutu`, gerçek input yerinde). Reddedilenler: olaylar için hap, anahtar (switch).
  Ayrıntı: [p7h-onay-kutusu.md](decisions/p7h-onay-kutusu.md).

**A26 · Panel ekran döngüsü** (2026-11-11, kullanıcı: "1'i onaylıyorum, ekran döngüsünü kur").
- Ekran görüntüsü için ayrı, yerel bir panel kabı (`panel-ekran`) ve yalnız bu iş için üretilmiş test kullanıcısı.
  Gerçek panel şifresi kullanılmaz. Reddedilenler: Chrome eklentisine bağlı kalmak, geliştirme ortamında girişi
  atlayan yol. Ayrıntı: [p7f-ekran-dongusu.md](decisions/p7f-ekran-dongusu.md).

**A25 · Entegrasyonlar ve anahtarlar** (2026-11-11, kullanıcı).
- Menüde Webhook'lar, API anahtarları ve Ürün bağlamı (AI) yerine tek **Entegrasyonlar** sayfası (taslaktaki
  gibi). Reddedilenler: ayrı kalmaları; Ürün bağlamı'nın ayrı kalması.
- Webhook olayları Türkçe ve gruplu seçilir (Huni · Ödeme · Abonelik), grup başına "Tümü".
- İptal edilen API anahtarları listede gizlenir ("İptal edilenler (N)" katlanır) ve silinebilir; etkin anahtar
  önce iptal edilir. Reddedilenler: yalnız gizlemek, iptalde silmek (geçmiş kalmaz).
- Kaynak: [p7-taslak-farki.md](decisions/p7-taslak-farki.md) (Ç130, Ç131). Uygulama: [p7d-entegrasyonlar.md](decisions/p7d-entegrasyonlar.md).

**A24 · Panel tabloları sayfalı** (2026-11-10, kullanıcı: numaralı sayfalama, büyüyen tüm tablolar).
- Sayfa başına 25, sunucuda; sıralama da sunucuda. Aboneler'de sayfa ve sıra adreste. Rapor'un üç listesi
  (ziyaretler, kaynaklar, serbest yanıtlar) kendi uçlarında. Reddedilenler: tarih aralığı zorunluluğu,
  "daha fazla göster", tablo içinde kaydırma. Ayrıntı ve ölçüm:
  [p7a2-sayfalama.md](decisions/p7a2-sayfalama.md).

**A20 · M7 giden webhook** (2026-11-09, kullanıcı: hedefler panelden uygulama başına; kişisel veri yok).
- Hedef: adres + seçilen olaylar (sözlükten) + gizli anahtar; uygulama başına, panelden. Reddedilen: yapılandırma
  dosyası, yalnız çekme (akış API).
- Gövde yalnız kimlikler ve sözlük verisi (`{id, ad, ziyaret, veri, zaman}`; e-posta yok, Ç90). Reddedilen: abonelik
  olaylarında e-posta.
- Teslim en az bir kez, üstel bekleme; imza Standard Webhooks biçimi (`webhook-id`, `webhook-timestamp`,
  `webhook-signature: v1,<base64 HMAC-SHA256>`) — alıcılar hazır kitaplıkla doğrular. Bölünme: M7a motor →
  M7b panel ekranı.

**A19 · M6 abonelik yönetimi** (2026-11-09, kullanıcı).
- **İptal dönem sonunda** (`cancel_at_period_end`); dönem bitene kadar geri alınabilir; denemede deneme
  sonunda biter, ücret çekilmez. Reddedilen: hemen iptal, yöneticiye seçtirmek.
- **İade: son tahsil edilen faturanın tamamı**, tek düğme; iade gelirden (kohort, kaynak, ROAS) düşülür.
  Reddedilen: kısmi / tutar seçmeli iade, iadeyi yalnız Stripe panelinde bırakmak.
- **Müşteri yönetimi Stripe Customer Portal'da** (kart, iptal, faturalar); kendi sayfamız reddedildi
  (iş ve risk). Bölünme: M6a panel iptal + iade → M6b portal.
- **Portala iki yol** (kullanıcı 2026-11-09): uygulama sunucusu çekirdekten kişi için portal adresi ister
  (uygulama anahtarı) **ve** web: hunide "Aboneliğimi yönet" → tek kullanımlık e-posta bağlantısı →
  portal (uygulamayı silen müşteri için de). **Portal ayarları kodda**, Stripe API'siyle kurulur:
  dönem sonunda iptal (A19 ile aynı), kart güncelleme, fatura geçmişi; plan değiştirme kapalı. Reddedilen:
  tek yol, ayarları Stripe panelinde elle tutmak. Bölünme: M6b1 ayar + uygulama ucu → M6b2 web yolu.

**A18 · M5 sırası: önce olay deposu (Ç92)** (2026-11-09, kullanıcı).
- Önce yalnız ekleme yapılan olay deposu (cevap / izin / abonelik geçmişi), sonra cevap
  dağılımı · UTM · kohort geliri, en son Meta harcama → ROAS. Reddedilen: önce raporlar —
  son-hâl verisinden kohort geliri sonra yeniden yazılırdı; giden webhook (M7) de aynı akışı ister.
- **Kohort geliri Stripe faturalarından** (kullanıcı 2026-11-09): `invoice.paid` aboneliğe → ziyarete
  bağlanır; deneme sonrası ilk ödeme ve yenilemeler dahil. Reddedilen: yalnız ilk ödeme oturumu
  (denemeli hunide kohort geliri hep 0). Bölünme: M5b faturalar → M5c raporlar.
- **M5c raporları** (kullanıcı 2026-11-09, üçü de): UTM kırılımı (kaynak / ortam / kampanya → ödemeye
  geçti → deneme → ödedi → gelir), cevaba göre dönüşüm (seçenek başına seçen + dönüşüm), kohort
  tablosu (ziyaret haftası × 0/7/30/60 gün, kişi başı birikimli gelir).
- **M5d ROAS** (kullanıcı 2026-11-09): harcama Meta Marketing API'den (Insights, ads_read — CAPI
  anahtarından ayrı `META_INSIGHTS_ACCESS_TOKEN`, `META_AD_ACCOUNT_ID`); eşleşme `utm_campaign =
  kampanya adı` ve ziyaret Meta'dan (fbclid ya da kaynak meta/facebook/fb/instagram/ig). Reddedilen:
  elle giriş, kampanya kimliği. **Değer aktarımı (StartTrial tahmini değer) sonraya** — kohort verisi
  birikmeden LTV tahmini yanlış olur.

**A17 · M4 deneyler** (2026-11-04, kullanıcı: tür ve sonuç; atama ayrıntısı öneri).
- **Tür: ikisi de.** Yönlendirme deneyi bir hunin trafiğini varyant hunilerine böler
  (ilk varyant = kontrol = deneyin hunisi); içerik deneyi aynı hunide koşulda
  `deney.<ad>` olur (T3 motoru, yeni alan yok). Reddedilen: yalnız biri.
- **Atama:** `sha256(deney|tohum)` ilk 8 hex mod 100 → payların birikimli dilimi; tohum
  hunin sekme deposunda (ziyaret kimliğiyle aynı sınıf, Ç34 — çerez/yeni izin yüzeyi
  yok). Bedeli: sekme ve gün arası yapışık değil. Tohumsuz (engelli depo) ziyaretçi ve
  `?huni=` doğrudan adresi deney dışı. Reddedilen: sunucuda saklanan atama (her
  ziyarette yazım + kimlik), çerez (izin şeridine yük).
- **Durum:** taslak → aktif ↔ durdu → bitti; taslak dışında varyantlar kilitli, taslağa
  dönüş yok (sonuçlar karışmasın); hunide tek aktif yönlendirme (DB kısıtı); aktife
  geçerken huniler yayında olmalı.
- **Kayıt:** atama ziyaretle `ziyaret_deneyleri`'ne — istemciden gelir, yalnız ziyaretin
  hunisindeki aktif deneyin geçerli varyantı yazılır.
- **Sonuç: oranlar + kazanma olasılığı (Bayes).** Bölünme: M4a atama + panel yönetimi →
  M4b sonuçlar.
- **Dönüşüm = abone oldu, deneme dahil** (kullanıcı 2026-11-09). Reddedilen: yalnız para
  ödeyen (denemeli hunide günlerce 0), ikisine ayrı olasılık (kalabalık). "Ödedi" ayrı sütun,
  oturum tutarı > 0'dan — Stripe kartlı denemeyi de `paid` sayar (ölçüldü, tuzak §52).
  Olasılık: Beta(1,1) önsel, P(en iyi) belirlenimci ızgara tümlevi (Monte Carlo reddedildi:
  test edilemez).
- **Rapor ve reklam olayı aynı tanıma** (kullanıcı 2026-11-09, Ç118): panel Rapor'da "ödedi"
  tutar > 0, kartlı deneme ayrı "deneme başladı", dönüşüm = abone. Reklam ağlarına denemede
  `StartTrial` gider (değer 0); tarayıcı pikseli adı `giris-baglantisi` yanıtının `deneme`
  alanından seçer. Reddedilen: tahmini değerle `Purchase` (değer M5 LTV'sine kaldı).

## §B — Yığın

~~**B1 · TypeScript: Next.js (App Router) + Postgres + Stripe Node SDK,~~ — **YERİNİ B2 ALDI (2026-10-19).** Aşağıdaki gerekçe tarihî kayıttır.
Docker ile kendi sunucumuzda** (2026-10-19). Kullanıcı ölçütü: deneyim
değil, işe en uygun dil.

**Neden:**
- Ürünün yüzü **etkileşimli huni** (adımlar, ilerleme, istemci durumu,
  geçişler) ve ileride **görsel editör** — ikisi de React ekosisteminin
  işi. TypeScript'te huni, API, webhook ve editör **tek dilde**.
- Huni sayfası hızı: sunucu bileşenleri sayfayı sunucuda üretir, yalnız
  etkileşimli parça istemciye JS taşır (web2wave'in "sunucuda HTML + az JS"
  yolunun modern karşılığı).
- Ödeme: Stripe, Paddle ve iyzico'nun **resmi** Node SDK'ları var →
  Ç10'un üç yolu da açık.
- Doğrulama: `tsc --noEmit` + Vitest + **Playwright** (huniyi gerçek
  tarayıcıda baştan sona yürütür — blok adım 6'nın otomatik hâli).

**Reddedilen:**
- **Go** — sunucu tarafı için iyi, ama huni arayüzü ve editör için ikinci
  bir dil (JS/TS) yine gerekir; abonelik yaşam döngüsü elle yazılır.
- **Laravel** — web2wave'in yığını; Cashier (Stripe + Paddle) gerçek bir
  artı. Ama etkileşimli huni ve editör için Inertia + React'e gidilir → iki
  dil; PHP dağıtımı ayrı bir düzen.
- **Astro** — statik sayfada en hızlısı, ama huni durumu + webhook + editör
  için uygulama çatısı değil.
- **SvelteKit** — teknik olarak uygun; editör kütüphanelerinde (sürükle-bırak,
  form kurucular) ekosistem React'ten küçük.
- **Vercel'e dağıtım** — sunucusuz kilitlenme ve maliyet belirsizliği;
  `output: standalone` + Docker ile kendi sunucumuz.

⚠️ **Risk:** Next.js'in sürümden sürüme değişen önbellek/yönlendirme
davranışı — sessiz yanlış sonuç adayı; ilk blokta ölçülecek (risk tablosu).

**B2 · Astro (statik huni, CDN) + Symfony (çekirdek API) + Postgres**

> **Güncelleme 2026-11-03 (kullanıcı):** Astro kaldırıldı → **Vite çok sayfalı + Preact** (`docs/decisions/astro-kaldirma.md`). B2'nin özü aynı: sunucusuz, derlemede üretilen statik huni, aynı adresler ve `dist` düzeni.
(2026-10-19, kullanıcı onayı; B1'in yerine). Ölçüt: profesyonel şirket
kalitesi.

**Neden:**
- **Para akışı herkese açık sayfa sunucusundan ayrı.** B1'de huni, webhook
  ve uygulama API'si tek Next.js sürecindeydi; self-hosted Next.js'te 18 ayda
  3 ciddi CVE (2025-29927 middleware bypass, 2025-55182 RSC RCE, 2026-44577
  SSRF). Huni sayfası statik → sunucusu yok → saldırı yüzeyi yok.
- **Huniler kodda tanımlı (A1)** → sayfalar derlemede üretilir, CDN'den en
  hızlı açılış; kullanıcıya özel içerik olmadığı için B1'in R1 önbellek
  riski ortadan kalktı. Etkileşim React adacıklarıyla.
- **Çekirdek Symfony:** iyzico'nun resmi SDK'sı yalnız PHP (`gh repo list`
  ölçümü: Stripe ve Paddle'da Go+PHP var, iyzico'da Go yok) — Türkiye
  şirketinin üç canlı ödeme yolu da resmi SDK'yla açık. Messenger (webhook
  kuyruğu, tekrar, başarısız iş deposu), Workflow (abonelik durum makinesi),
  Doctrine Migrations hazır; para akışında elle yazılan parça az.
- Zayıf yan ve önlemi: PHP çalışma zamanında dinamik → PHPStan en üst
  seviye `/kontrol`'de zorunlu.

**Reddedilen:**
- **B1, tek Next.js** — yukarıdaki ayrım gerekçesi.
- **Go çekirdek** — iyzico SDK'sı yok; kuyruk/durum makinesi/göç elle
  birleştirilir; bu iş yükünde hız kazancı kullanılmaz. Ç10 kesin Paddle
  çıkarsa yeniden tartılır.
- **Laravel çekirdek** — kullanıcı Symfony'yi seçti; Symfony'nin bileşen
  sınırları (Messenger, Workflow) çekirdeğin işine daha doğrudan oturuyor.
- **Huni sayfalarını Symfony/Twig ile sunucuda üretmek** — para sürecini
  yine herkese açık sayfa trafiğiyle aynı yere koyar.

**Bedeli:** iki dil, iki dağıtım (CDN + container), aralarında OpenAPI
sözleşmesi; A/B testi için her varyant ayrı sayfa, atamayı çekirdek yapar;
A1 değişip huniler panelde tanımlanırsa statik üretim yetmez.

**B3 · Sürümler ve çalışma ortamı** (2026-10-19, iskelet bloğu).
- **Symfony 7.4 LTS** (hata 2028-11, güvenlik 2029-11) · PHP **8.4** ·
  Postgres **18.6** · Astro **7.3.8** · TypeScript **6.0.3** · Node **24.21.0**.
- **Her araç Docker'da** (FrankenPHP 1.13.1 çekirdek imajı + Node 24 araç
  imajı); taban imajlar **digest'e sabit**, npm paketleri tam sürüm,
  Composer `^` + `composer.lock`.
- Neden: geliştirme = CI = üretim tabanı; makinedeki PHP/Node sürümü
  ölçümü değiştirmesin (ölçüldü: makinede Node 22.15, `undici` ≥22.19 istiyor).
**Reddedilen:** Symfony 8.1 — LTS değil, desteği 2027-01'de biter ·
TypeScript 7 — `@astrojs/check` desteklemiyor (peer ölçümü) · makineye PHP
kurmak — sürüm kayması, CI'dan farklı ortam · etiketle (digest'siz) sabitleme
— etiketler yeniden derleniyor · Astro 7.4.0 — 4 günlük `.0`.

**C3 sona erdi** (2026-10-19): depoda kod var; kapı artık `bin/kontrol`.

**B4 · Huni oynatıcısı React; sunucusuz** (2026-10-21, kullanıcı; Ç48). Anket
çalışma zamanı düz TypeScript'ten **React tabanlı huni oynatıcısına** geçer;
blok bileşenleri **tek kaynak**: Puck düzenleyicisinde görünen ile yayındaki
ankette çalışan aynı React bileşeni (bugün iki çizim: `HuniKabugu.astro` ve
`core/editor/src/yapilandirma.tsx`). Herkese açık Node sunucusu YOK (B2'nin
gerekçesi kalır): ya Astro sayfasında tek React adacığı ya saf Vite SPA,
ikisi de CDN'den statik. Adım geçişleri sayfa yenilemeden; merkezi durum;
piksel/olay bildirimi tek noktadan. Gerekçe (kullanıcı): çift çizimin bakım
yükü ve önizleme–huni kayması; anket bir durum makinesi (dallanma, skor,
geçiş, doğrulama, ödeme) ve düz TS'te büyümesi yavaş; rakiplerde anket React
(Next.js: RevenueCat, BetterMe; Vite + React SPA: WalkFit, Muscle Booster),
Astro yalnız pazarlama sayfasında (`huni-cati-arastirmasi.md`).
**Reddedilen:** Next.js (herkese açık Node süreci, CVE yüzeyi, maliyet — B2) ·
düz TS'te kalmak (çift çizim). **Ölçülmemiş varsayımlar** (geçişin ilk adımı
ölçer, kural 1): "boş kabuk → içerik isteği ilk boyamayı geciktiriyor"
(süresi ölçülmedi); Simple'ın çatısı (Vite SPA ölçüldü, React DEĞİL —
tanımlanamadı); rakiplerin React'i animasyon/durum için seçtiği (çıkarım).
**Bedeli:** huni JS'i ~12 KB → React ile büyür (bütçe ölçülerek konur);
yeni ortak paket, iki tüketici (huni + düzenleyici) ve Docker bağlaması.
Geçiş planı: `PLAN.md` §1 (H0–H6).
**Güncelleme (L20, 2026-11-02):** hunide çalışma zamanı **Preact (compat)** — bileşenler React API'siyle yazılır, panelde React, hunide Preact'e eşlenir (tek kaynak korunur); JS 77,4 → 19,7 KB, bütçe 30 KB (`l20-preact.md`, onay Ç81).

**B5 · Panel ayrı React uygulaması; Symfony panel için JSON veri servisi**
(2026-10-27, kullanıcı: "yapılarını nasıl kurmuşlar, basite kaçmayalım,
geliştirmemiz ileride kesilmesin"). Rakip ölçümü
(`docs/decisions/panel-mimari-arastirmasi.md`): OpnForm (Laravel API +
ayrı Nuxt SPA), HeyForm (NestJS + ayrı React webapp + ayrı form-renderer),
Formbricks (Next.js + ayrı Preact anket paketi + ortak types), openFunnel
(admin/builder/runtime + engine) — dördünde de yönetim paneli **ayrı
istemci uygulaması**, ziyaretçi tarafı **ayrı hafif paket**, tipler ortak.
Bizde huni (Astro+React), ortak bileşen ve OpenAPI tipleri var; eksik olan
panel (Twig sayfaları + ayrı sayfada Puck). Twig'de CRM araması, grafik, A/B,
sayfa yenilemeden düzenleme, çoklu dil zorlaşır.
- Panel = `core/panel` (bugünkü `core/editor` büyür): Vite + React +
  React Router, **Radix** (erişilebilir davranış) + **Tailwind** (stil;
  bileşen kodu depoda, shadcn yöntemi), **TanStack Query** (veri), TanStack
  Table (tablo), Recharts (grafik). Aynı adresten (`/panel/*`) Symfony
  sunar: aynı köken, mevcut oturum + giriş formu korunur, CSRF başlıkla.
- Panel uçları `/panel/api/*` JSON, **OpenAPI sözleşmesinde**; tipler
  üretilir (huniyle aynı yol). Puck düzenleyici panelin bir sayfası olur.
- Giriş sayfası Symfony formu kalır (güvenlik bileşeni), yalnız görünüm.
- Geçiş adım adım (`PLAN.md` "Panel React" P0–P5); her adım testli, ayrı
  commit; eski Twig sayfası, yerine gelen React sayfası testle eşlenince
  silinir.
**Reddedilen:** Twig + Symfony UX (Turbo/Stimulus) — grafik/filtre/CRM'de
sınır, düzenleyiciyle iki dünya · Next.js ayrı sunucu (Formbricks) — ikinci
Node sunucusu ve ikinci arka uç, oturum ve dağıtım iki yerde (B2'nin
gerekçesi) · hazır tasarım sistemi (Mantine/Ant) — görünüm özelleştirmesi
zor, büyük bağımlılığa kilit · kendi CSS + Radix — her bileşenin stili elle.
**Bedeli:** panel JS'i büyür (panel herkese açık değil; bütçe yok ama
ölçülür), bağımlılık sayısı artar (Dependabot, L18), Twig panel testleri
JSON uç testlerine dönüşür.

**B6 · AI'ın ilk kapısı dışarıya açılan MCP sunucusu (URL)** (2026-10-27,
kullanıcı: "Quiz Builder için en mantıklı yol, ilk aşamada sistemi yalnızca
dışarıya açılan bir MCP sunucusu (URL) olarak inşa etmek"). Önceki sıra
(`ai-asistan-arastirmasi.md`): panel içi sohbet (A2/A3) önce, MCP (A5) sonra
ve önce yerel stdio. Yeni sıra: **MCP (HTTP, URL) önce**.
- Gerekçe: MCP'ye Claude Desktop / claude.ai kullanıcının KENDİ Claude
  aboneliğiyle bağlanır; model Anthropic'te çalışır, bizde yalnız araçlar →
  **ANTHROPIC_API_KEY gerekmez** (kullanıcıda anahtar yok, A2/A3 kapalı
  duruyor). web2wave ve FunnelFox'ta da aynı kapı var.
- Araçlar A0 (`HuniAraclari`) — değişmez; yalnız taslak, yayın/silme yok.
- A2/A3 (panel içi sohbet) kalır, kapalı; ikinci aşama. Silinmez.
- Yerel stdio artık ilk hedef değil (yalnız geliştirme kolaylığı olarak
  istenirse).
**Açık (MCP araştırmasında netleşecek):** herkese açık adres — dağıtım
ertelenmişti (sunucu yok); yerel deneme için tünel (ör. Cloudflare Tunnel)
mi, önce sunucu mu · kimlik — uygulama başına belirteç (Bearer) mı, claude.ai
özel bağlayıcısı için OAuth mu · PHP'de MCP HTTP aktarımı (`mcp/sdk` 0.9,
`symfony/mcp-bundle` beta; iki hafta kuralı).

## §C — Araç ortamı

**C1 · Ritüel skill'leri projede yaşar, kullanıcı düzeyi `ritual` eklentisi
bu projede kapalı** (2026-10-19). Neden: proje kopyaları depoyla
sürümlenir, başka makineye/bulut oturumuna gider ve projeye göre
budanabilir (KeepChat artıkları ancak böyle temizlendi). Reddedilen:
(a) proje kopyalarını silip eklentiye dayanmak — eklenti yalnız bu Mac'te,
artıkları temizlemek onu kullanan diğer projeleri de değiştirir;
(b) ikisini birden tutmak — skill'ler iki kez listelenir, hook iki kez koşar.

**C2 · Graphify şimdilik YOK** (2026-10-19). Neden: depoda kod yok; graf
keşif maliyetini düşürür, keşfedilecek bir şey yok. Bayat graf, sessizce
yanlış cevap üretme riski taşır (tuzak §12'nin akrabası). Tetikleyici ve
ölçüm planı: Ç8.
**Ölçüldü (2026-10-19, KeepChat, n=1 × 3 soru):** Graphify'lı kol her soruda
grafı sorguladı ama yine kaynağa gitti; toplam token +%4/+%4/+%15, cevaplar
iki kolda da doğru → **eklenmez**. Yeniden açılma koşulu: ajanın keşifte
onlarca dosyayı tek tek okuduğu ölçülürse (`ai-ortami-incelemesi.md` §7).

**C3 · Yığın seçilene kadar kural 11'in kapısı: elle ölçüm** (2026-10-19,
kullanıcı kararı). `/kontrol` tanımsızken yalnız ayar/belge değişiklikleri,
ölçümü kayıtlıysa commit + push edilir (ör. `ai-ortami-incelemesi.md`
§5: oturum init satırı, hook vakaları, artık grep'i). Kod girdiği anda
istisna biter; kapı yeniden `/kontrol` olur.

**C4 · web2wave'e kara kutu gözlemle bakılır, tersine mühendislikle değil**
(2026-10-19). Serbest: herkese açık sayfa/başlık/belge okumak, SDK'nın
**davranışını** okumak, deneme hesabıyla huniyi müşteri gibi yürümek.
Yasak: bot korumasını aşmak, panel/API'ye yetkisiz istek, derlenmiş kodu
açmak, lisanssız SDK'dan ya da "güvenlik değerlendirmesinden yeniden
yapım" türü klon depolardan kod almak (kural 12; kaynağı belirsiz).

**C5 · Depo herkese açık** (2026-10-19, kullanıcı kararı). Neden: özel
depoda Actions faturalandırma yüzünden koşmadı (Ç15); açık depoda Actions
ücretsiz. Açmadan önce tüm geçmiş tarandı: API anahtarı/token/özel anahtar
yok, KeepChat üretim host/IP'si yok. Görünür kalanlar: commit e-postası
(13 commit), KeepChat'ten iki fonksiyon adı (Graphify tablosu); dev/test
`APP_SECRET` ve yerel `app/app` DB parolası Symfony varsayılanı.
**Reddedilen:** faturayı düzeltmek (önerilen, kullanıcı seçmedi) · Mac'te
self-hosted runner · KeepChat üretim sunucusunda runner (üretim hostunda CI
kodu — güvenlik riski). **Sonuç:** depoya **asla sır girmez** — sırlar
`core/.env.local` (git dışı, `git check-ignore` ölçüldü) ve üretim ortamında.

**C6 · Dağıtım: ayrı sunucu, Stripe test modu** (2026-10-20, kullanıcı
kararı). Çekirdek + huni + Postgres **KeepChat sunucusundan ayrı** yeni bir
sunucuda (para akışı başka ürünle aynı makinede durmaz — B2'nin gerekçesi);
TLS ve huni Caddy'de, çekirdek iç ağda. Ödeme **test modunda**: canlı ödeme
sağlayıcısı (Ç10) ve vergi/hukuk (Ç12) kararı yok; `dagit.sh` `sk_test_`
dışındaki anahtarla durur. **Reddedilen:** keepchat-vps'e yan yana (ek
maliyet yok ama canlı KeepChat'e dokunur, ayrımı bozar) · önce plan, sonra
kurulum (kullanıcı seçmedi) · yönetilen platform (PaaS) — sorulmadı, tek
sunucu + Docker geliştirme/CI ile aynı taban. Ayrıntı: `decisions/dagitim.md`.

## Risk tablosu

| Risk | Olasılık | Etki | Ne zaman ölçülür |
|---|---|---|---|
| ~~R1 · Next.js önbelleği yanlış kişiye ait içerik gösterir~~ | — | — | B2 ile düştü (statik huni) |
| ~~R2 · Astro ile Symfony'nin sözleşmesi kayar~~ | — | — | ✅ ölçüldü 2026-10-19: `contract/openapi.yaml` + üretilen tipler + `sozlesme-guncel`; kırma P1–P3, P7–P8 |
| ~~R3 · Webhook iki kez gelir, abonelik iki kez açılır~~ | — | — | ✅ ölçüldü 2026-10-19: PK kısıtı + test + `stripe events resend` gerçekte 1 olay/1 abonelik (`stripe-odeme.md`) |
