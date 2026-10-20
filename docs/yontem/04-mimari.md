# 4 · Mimari

Ayrıntı ve reddedilenler: `docs/pre-setup.md` §A, §B2, §B3.

## İki parça: statik huni + çekirdek API

```
reklam ──► Huni (Astro, statik, CDN)          ── sunucusu yok
              │  POST /checkout/sessions {app, plan}
              ▼
           Çekirdek API (Symfony 7.4, PHP 8.4, FrankenPHP) ◄── Stripe webhook
              │
           Postgres 18
```

- **Neden ayrı:** para akışı (ödeme, webhook, abonelik hakkı) herkese açık
  sayfa sunucusuyla aynı süreçte durmamalı. İlk karar (B1, tek Next.js)
  bu yüzden değiştirildi: kendi sunucusunda Next.js'te 18 ayda 3 ciddi CVE.
- **Huni statik:** huniler kodda tanımlı → sayfalar derlemede üretilir;
  sunucu yok = saldırı yüzeyi yok, kullanıcıya özel önbellek riski yok.
- **Çekirdek Symfony:** üç ödeme sağlayıcısının (Stripe, Paddle, iyzico)
  resmi SDK'sı PHP'de; Messenger/Workflow/Doctrine Migrations hazır.
  Zayıf yan (dinamik tipler) PHPStan **en üst seviye** ile kapatılır.
- **Her şey Docker'da**, imajlar digest'e sabit: geliştirme = CI = üretim
  tabanı; makinedeki PHP/Node sürümü sonucu değiştirmez.

## Sözleşme: tek kaynak, iki yönlü kontrol (R2)

`contract/openapi.yaml` huni ↔ çekirdek sözleşmesinin **tek** kaynağı.

- Çekirdek testleri **gerçek yanıtı** bu dosyaya göre doğrular.
- Huninin TypeScript tipleri bu dosyadan **üretilir**; huni sözleşmede
  olmayan alanı kullanırsa tip denetimi düşer.
- `bin/kontrol` tipleri yeniden üretip depodakiyle bayt bayt karşılaştırır
  (`sozlesme-guncel`) — üretmeyi unutmak kırmızıdır.
- Kırma: iki yön de ölçüldü (huni yanlış alan, çekirdek yanlış alan,
  sözleşme değişip tip üretilmemesi) — `huni-paywall.md` P1–P8.

## Uygulama boyutu (A4): birden çok kendi uygulamamız

- Tek kiracı (SaaS değil), ama abonelik, fiyat ve huni **hangi uygulamaya**
  ait olduğunu taşır. Uygulamalar `config/packages/uygulamalar.yaml`'da.
- Kimlik çekirdekte, bir kişi tüm uygulamalarda aynı; **hak uygulama
  başına**.
- Abonelik hangi uygulamaya ait: çekirdek Stripe oturumuna `metadata.app`
  yazar, Stripe geri getirir — tek kaynak, fiyattan tahmin yok.
- KeepChat'in kendi girişi (kullanıcı adı + şifre, e-posta yok) korunur;
  bir kez "aboneliğimi bağla" ile çekirdek kimliğine bağlanır. Yeni
  uygulamalar doğrudan çekirdeğin girişini kullanır.

## Kapsam dışı bırakılan (bilinçli)

Görsel huni editörü, çok kiracılı panel (A1); uygulama içi satın alma
(IAP) doğrulaması sonra ama kendimiz (Ç29) — kimlikte kişiye kalıcı UUID
(`appAccountToken`) şimdiden.

## Huni = veri + motor (`huni-anket.md`, `huni-yayin.md`, A7)

- Her uygulamanın hunisi **veritabanında** veri: adımlar (tanıtım, soru,
  paywall). Taslak düzenlenir; "yayınla" değişmez bir sürüm (v1, v2…) üretir.
- Motor (`components/HuniKabugu.astro`) statik kabuk: yayınlanmış en son
  sürümü çalışma anında okur, tek sayfada sırayla gösterir, metni yalnız
  `textContent` ile basar. Yeni uygulama = veritabanında huni + bir sayfa.
- Tanım yayından önce çekirdekte doğrulanır (`App\Huni\Tanim`) —
  çekirdeğin reddedeceği veri yayına çıkmaz. (E1.1'den önce bu kontrol
  derlemedeydi.)
- **Tek adres, çok huni (A8):** bir uygulamanın birden çok hunisi var;
  panelde sıralı kurallar (`k`, `utm_*` = değer → huni) hangisinin açılacağını
  çekirdekte seçer. Huni sayfası yalnız seçim parametrelerini gönderir
  (önbellek). Ziyaret hangi huniyi ve sürümü gördüğünü taşır.
- **Tema (E2.2):** huni başına renk/yazı tipi/köşe; sınırlı değer kümesi
  (serbest CSS yok), okunurluk (WCAG AA) çekirdekte yayından önce; yalnız
  cihaz yazı tipleri (dış font = üçüncü tarafa IP).
- **Sürükle-bırak (E2.4):** panelde ayrı React paketi (`core/editor`, Puck);
  çekirdek yine yalnız huni tanımını bilir — düzenleyici Puck verisini
  tanıma çevirip mevcut kaydet ucuna yollar. Tipler sözleşmeden üretilir.
