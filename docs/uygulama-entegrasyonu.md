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
   `kullanici` = uygulamanın **kendi** kullanıcı kimliği (dizge) →
   **200 `{kisi}`** ya da **422** `invalid_code` / `expired_code` / `too_many_attempts`.
3. Sonra her gerekince: `GET /apps/{app}/kullanicilar/{kullanici}/hak` →
   **200 `{aktif, durum}`** · **404** `unknown_user` (bağlanmamış).
   `aktif` = `active` · `trialing` · `past_due`.

Hepsinde başlık `Authorization: Bearer <uygulama anahtarı>`; yanlış/eksik → **401**.

## 4 · Uygulamanın sunucusunda yapılması gerekenler

- Bağlamayı **oturum açmış** kullanıcı için yap (kimin bağladığı belli olsun).
- Hak cevabını kısa süre önbellekle; çekirdeğe her istekte gitme. Çekirdeğe
  ulaşılamazsa **son bilinen durumu** kullan, kullanıcıyı kilitleme.
- Kendi tarafında da oran sınırı koy (Ç31).

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
