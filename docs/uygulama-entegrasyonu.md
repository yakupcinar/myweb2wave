# Uygulama entegrasyonu — bir uygulama MyWeb2Wave'e nasıl bağlanır

> Okuyucu: bir uygulamanın (ilk: KeepChat) **sunucu** ve **istemci**
> geliştiricisi. Kaynaklar: sözleşme `contract/openapi.yaml` (tek kaynak),
> kararlar `docs/pre-setup.md` A4, A5, Ç29, ölçümler `docs/decisions/kimlik-hak.md`.

## 1 · Kimin neyi yaptığı

```
Kullanıcı ──(web hunisi, Stripe)──► MyWeb2Wave çekirdeği   [abonelik e-postayla kayıtlı]
Uygulama istemcisi ──(kendi oturumu)──► UYGULAMANIN SUNUCUSU ──(uygulama anahtarı)──► çekirdek
```

- Çekirdeğe **yalnız uygulamanın sunucusu** konuşur; anahtar istemcide
  (iOS ikilisi, tarayıcı) **asla** bulunmaz (A5).
- Uygulama kendi girişini korur; çekirdeğe yalnız "bu kullanıcıyı bu
  e-postaya bağla" ve "bu kullanıcının hakkı var mı" sorulur.

## 2 · Yapılandırma (uygulamanın sunucusunda)

| Değişken | Ne | Not |
|---|---|---|
| Çekirdek adresi | ör. `http://localhost:8081` (yerel) | **boşsa özellik KAPALI** — çekirdek dağıtılmadan üretim buna ulaşamaz |
| Uygulama anahtarı | çekirdekte `UYGULAMA_ANAHTARI_<APP>` ile aynı değer | sır; depoya girmez |
| Uygulama adı | `keepchat` | çekirdekte `config/packages/uygulamalar.yaml` |

## 3 · Akış: "Aboneliğimi bağla"

1. İstemci → uygulama sunucusu: e-posta. Sunucu →
   `POST /apps/{app}/baglama/kod {email}` → **202** (her zaman; e-postanın
   varlığı sızmaz) ya da **429** (10 dk'da 3'ten fazla).
2. Kullanıcı e-postadaki **6 haneli** kodu girer (10 dk geçerli, 5 deneme).
   Sunucu → `POST /apps/{app}/baglama/dogrula {email, kod, kullanici}`;
   `kullanici` = uygulamanın **kendi** kullanıcı kimliği, **dizge** (sayısal
   kimlik ondalık yazılır: `"42"`). **Kalıcı** olmalı — değişebilen ad değil
   (KeepChat'te silinen hesabın adı değişir, `id`'si kalır) →
   **200 `{kisi}`** ya da **422** `invalid_code` / `expired_code` / `too_many_attempts`.
3. Sonra her gerekince: `GET /apps/{app}/kullanicilar/{kullanici}/hak` →
   **200 `{aktif, durum}`** · **404** `unknown_user` (bağlanmamış).
   `aktif` = `active` · `trialing` · `past_due`.

Hepsinde başlık `Authorization: Bearer <uygulama anahtarı>`; yanlış/eksik → **401**.

## 4 · Uygulamanın sunucusunda yapılması gerekenler

- Bağlamayı **oturum açmış** kullanıcı için yap (kimin bağladığı belli olsun).
- Bağlantının gerçek kaynağı **çekirdek** (`baglantilar`); uygulamanın
  bağlantıyı saklaması gerekmez, yalnız hak durumunu önbellekler (göç gerekmez).
- Önerilen önbellek: başarılı hak cevabı **5 dk**; çekirdeğe ulaşılamazsa son
  bilinen durum **en çok 24 saat**, sonra "bilinmiyor" — kullanıcıyı
  kilitleme. Kod/doğrulama uçları önbelleğe alınmaz.
- Kendi tarafında da oran sınırı koy (Ç31).

## 4b · Hak kaynağı RevenueCat (A9)

- "Kim Pro" bilgisinin tek kaynağı **RevenueCat**: web (Stripe) + App Store.
- Çekirdek, Stripe aboneliğini RevenueCat'e kendisi aktarır — e-postası bu
  uygulamada bir kişiye **bağlıysa**; bağlama sonrası eski abonelikler de
  gider. RevenueCat'te kullanıcı kimliği = bağlamanın döndürdüğü **`kisi`**
  (UUID).
- Uygulama: bağlama başarılı olunca `kisi`'yi saklar ve RevenueCat SDK'sında
  `Purchases.logIn(kisi)` ile o kimliğe geçer; hakkı SDK'dan sorar. App Store
  satın alması da aynı kimliğe düşer → web + iOS tek hak.
- RevenueCat Offering'deki ürün kimliği Stripe ürün kimliğiyle **aynı**
  olmalı; Stripe iptali RevenueCat'e en geç ~2 saatte yansır (yeniden
  gönderimle hemen — çekirdek her durum değişikliğinde yeniden gönderir).
- Bizim hak ucumuz (§3, `…/hak`) uygulama RevenueCat'e geçene kadar yedek.

## 5 · ⚠️ App Store kuralları (iOS istemcisi)

- Web'de alınan aboneliği uygulamada **açmak**, aynı ürün uygulama içi
  satın almada (IAP) da satılıyorsa serbest (3.1.3(b)); IAP yokken kilit
  açmak ret riski (Ç29). İlk adımda yalnız **durumu göstermek** güvenli.
- Uygulamanın içinden **web'de satın almaya yönlendirme yok** (bağlantı,
  düğme, "web'den al" metni) — ABD vitrini dışında yasak. Bağlama ekranı
  yalnız "zaten abone misin? e-postanla bağla" der.

## 6 · Doğrulama (uygulama tarafında)

Çekirdek yerelde `docker compose up -d --wait` ile `:8081`'de; kod
e-postaları Mailpit'te (`http://localhost:8025`). Uçtan uca ölçülmüş örnek
zincir ve beklenen yanıtlar: `docs/decisions/kimlik-hak.md` § Gerçek istek.
