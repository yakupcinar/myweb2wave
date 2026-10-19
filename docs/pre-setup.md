# Kararlar ve NEDEN'leri

> Her karar: **ne** · **neden** · **reddedilen alternatifler ve sebebi** ·
> tarih. Karar değişirse eskisi silinmez, üstü çizilir ve yenisi
> gerekçesiyle altına yazılır.

## §A — Ürün

**A1 · Tek kiracı: kendi uygulamalarımız için** (2026-10-19, kullanıcı
kararı). Huniler kodda tanımlanır, görsel editör yok. Reddedilen: başka
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

## Risk tablosu

| Risk | Olasılık | Etki | Ne zaman ölçülür |
|---|---|---|---|
| ~~R1 · Next.js önbelleği yanlış kişiye ait içerik gösterir~~ | — | — | B2 ile düştü (statik huni) |
| R2 · Astro ile Symfony'nin sözleşmesi kayar: huni eski alan adıyla istek atar, çekirdek 4xx/sessiz yok sayar | orta | yüksek | ilk uçtan uca blok: OpenAPI'den üretilen tipler + sözleşme testi |
| R3 · Webhook iki kez gelir, abonelik iki kez açılır | yüksek | yüksek | ödeme bloğu: aynı olayı iki kez gönderen test |
