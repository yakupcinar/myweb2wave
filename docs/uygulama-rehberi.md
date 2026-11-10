# Uygulama rehberi — panel sayfa sayfa, arkasındaki sistemle

> Uygulamayı **takip etmek ve okumak** için tek belge. Her panel sayfası aynı dört başlıkla anlatılır:
> **Ne görürsün · Ne yapabilirsin · Arkada ne çalışıyor · Henüz ölçülmeyen**. Kararların gerekçesi ve
> ölçümler burada kopyalanmaz, `docs/decisions/` kaydına bağlantı verilir.
>
> **Güncelleme kuralı:** bir blok bir sayfayı ya da arka plan işini değiştirirse, o blokta **yalnız o
> bölüm** güncellenir (`/belge` adım 4c). Adresler ve portlar `adresler.md`'de, tuzaklar `tuzaklar.md`'de.
>
> Son tam gözden geçirme: 2026-11-10 (M8a1 sonrası). Önceki mimari notları (`backend-mimarisi.md`,
> `teknik-mimari-notu.md`) buraya katıldı.

---

## 1. Uçtan uca yolculuk

```
Reklam / bağlantı
   │  ?utm_…&fbclid=…
   ▼
Huni (funnel, statik)  ──POST /huni/ziyaretler──▶  huni_ziyaretleri + olaylar
   │  izin şeridi, anket adımları, e-posta sorusu (izin kutusuyla)
   │  ──POST …/ilerleme · …/izin · …/eposta──▶  huni_adimlari · huni_cevaplari · huni_epostalari
   ▼
Paywall ──POST /checkout/sessions──▶ Stripe Checkout (e-posta önceden dolu)
   │                                      │
   │                            webhook ──▶ /webhooks/stripe → subscriptions, odeme_oturumlari,
   │                                                         odeme_faturalari, olaylar
   ▼                                      ├─▶ reklam dönüşümü (Meta CAPI / TikTok, izinliyse)
Başarılı sayfası ── giriş bağlantısı ─────├─▶ RevenueCat aktarımı
   │                                      └─▶ "Uygulamaya giriş bağlantınız" e-postası (Postmark)
   ▼
Mobil uygulama (KeepChat): bağlantıya dokunur ya da e-posta + 6 haneli kod
   │  ──POST /apps/{app}/giris/kullan · /baglama/…──▶ baglantilar (uygulama kullanıcısı ↔ kişi)
   ▼
Uygulamanın SUNUCUSU ──GET /apps/{app}/kullanicilar/{k}/hak──▶ {aktif, durum}
```

- **Kimlik:** gerçek kişi `kisiler` (e-posta tekil). Abonelik `subscriptions` (Stripe'ın kaydı). Bir uygulama
  kullanıcısı kişiye `baglantilar` ile bağlanır. Erişim veren durumlar: `active`, `trialing`, `past_due`
  (`Hak::ERISIM_VEREN`; kart reddedilince Stripe yeniden denerken erişim sürer).
- **Mobil uygulamada gizli anahtar yok:** hak sorgusunu uygulamanın sunucusu, uygulama anahtarıyla yapar
  (`uygulama-entegrasyonu.md`).
- **Abonelik yönetimi:** müşteri Stripe Customer Portal'a uygulamadan (`POST /apps/{app}/kullanicilar/{k}/portal`)
  ya da e-postayla gelen tek kullanımlık bağlantıyla (`/portal/{jeton}`) gider (`m6b1-portal.md`, `m6b2-portal-web.md`).

---

## 2. Panel sayfaları

Panel `/panel`, tek yönetici girişi. Oturum veritabanında (`sessions`). React, sözleşmeden üretilen tiplerle çalışır.

### 2.1 Rapor — `/panel`

**Ne görürsün**
- Üstte **Reklamdan gelen / Organik-doğrudan** ayrımıyla ziyaret, ödeme başladı, deneme başladı, ödedi, abone.
  - **"Ödedi":** tutar > 0 ya da faturası var. Kartlı denemenin 0 tutarlı ödemesi ödedi sayılmaz (§52, Ç118).
  - **"Dönüşüm":** abone.
- Günlük grafikler, **anket adımları** (her adıma kaç ziyaret ulaştı), **cevaplar** (cevaba göre dönüşüm).
- **Kaynak kırılımı:** UTM'e göre. Gelir, bu ziyaretlerin bugüne kadar tahsil edilen faturaları eksi iadeler.
- **Meta kampanyaları:** harcama, aynı adlı `utm_campaign` ile Meta'dan gelen ziyaretlerin geliri, ROAS. ROAS
  hesabın para biriminde, başka para birimi çevrilmez.
- **Kohort geliri:** ziyaret haftası başına birikimli gelir; "—" pencerenin henüz dolmadığı anlamına gelir.
- **Serbest yanıtlar:** metin cevapları, süreli saklanır.

**Ne yapabilirsin:** dönem seç: son 7 / 30 / 90 gün (`?gun=`; başka değer 30 olur). Başka işlem yok.

**Arkada ne çalışıyor**
- `GET /panel/api/rapor` → `App\Panel\Rapor`. Kaynaklar: `huni_ziyaretleri`, `olaylar` (`ziyaret_olaylari`
  görünümü), `odeme_oturumlari`, `odeme_faturalari` (`iade_kucuk` düşülür), `reklam_harcamalari`.
- Harcama `app:reklam-harcamalari:cek` ile Meta Insights'tan gelir (cron gerekir).
- Aynı rapor MCP'den de okunur (`RaporAraclari`, 6 araç).

**Henüz ölçülmeyen:** gerçek Meta hesabıyla çekiş (Ç121). Kayıtlar: `c118-deneme-rapor-reklam.md`, `m5a-olay-deposu.md`, `m5c-raporlar.md`, `m5d-meta-harcama.md`.

### 2.2 Huniler — `/panel/huniler` ve düzenleyici `/panel/huniler/{app}/{ad}`

**Ne görürsün**
- Uygulama başına huniler, sürümleri, varsayılan huni.
- **Yönlendirme kuralları:** reklam adresinin hangi huniyi açacağı. Üstten alta, ilk tutan kazanır.

**Ne yapabilirsin**
- Huni aç, kaldır ya da varsayılan yap; kural ekle, sil, taşı.
- Düzenleyicide (Puck) sürükle-bırakla blokları düzenle, taslağı kaydet, önizle, **yayınla**.
- Eski bir sürümü taslağa geri yükle.
- **AI sohbeti** ile taslak yazdır.

**Arkada ne çalışıyor**
- Huni tek bir JSONB belgesi olarak saklanır: `huniler.taslak`, yayınlananlar dondurulmuş olarak `huni_surumleri`'nde.
  Sorular için ayrı tablo yok.
- Her kayıt `Tanim.php` bekçisinden geçer: sözleşmedeki `HuniTanimi`, HTML yasak.
- `donustur.ts` Puck ağacı ↔ tanım dönüşümünü kayıpsız yapar.
- Yönlendirme kuralları `huni_kurallari` tablosunda. Koşul motoru (`huni-bilesenleri/kosul.ts`) göster/yönlendir
  kararlarını tarayıcıda verir.
- **AI:** `HuniAsistani`, Anthropic SDK ve araçlarla (`HuniAraclari`) çalışır; araç şeması sözleşmeden
  `app:huni-semasi` ile üretilir. `ekran` kabındaki Chromium (CDP) taslağın telefon görüntüsünü alıp AI'a geri
  verir. Ürün bağlamı (2.7) her isteğe eklenir. İstekler ve maliyet `ai_istekleri`'nde.
- **AI yalnız taslağa yazar; yayınlamak insanın işi.**
- Görseller: `medya` (yükleme ve stok arama), `/medya/{id}.webp`.

**Henüz ölçülmeyen:** `acik-uclar.md`'de "huni" geçen satırlar. Kayıtlar: `huni-yayin.md`, `huni-dallanma-h5.md`, `ai-a2-panel-arka-uc.md`, `ai-ekran-goruntusu.md` (diğerleri `huni-*`, `ai-*`).

### 2.3 Kullanıcılar — `/panel/kullanicilar`

**Ne görürsün**
- Abonelikler ve geldikleri kaynak. E-posta maskeli; tam adres Stripe'ta.
- Abonelik durumu, iptal planı ("İptal planlı — <dönem sonu>"), son fatura ve iadesi.

**Ne yapabilirsin** (iki adımlı onayla, CSRF jetonuyla)
- **Dönem sonunda iptal et** ya da **iptali geri al**.
- **Son faturayı iade et**: son faturanın iade edilmemiş kalanı.

**Arkada ne çalışıyor**
- `GET /panel/api/kullanicilar` → `Kullanicilar::liste`.
- İşlemler `AbonelikYonetimi` üzerinden Stripe'a gider; panel Stripe'ın **döndürdüğü** durumu yazar.
- İadeler `refunds.create(payment_intent)` ile yapılır. Ödeme ile fatura arasındaki bağ `fatura_odemeleri`'nde
  tutulur ve `invoice_payment.paid` olayından gelir (§54).
- Stripe panelinden yapılan iade de `charge.refunded` ile birikimli olarak düşer.
- Aynı işlemler MCP'de yalnız **yönetim izinli** anahtara açık (2.8).

**Henüz ölçülmeyen:** düğmeler tarayıcıda denenmedi. Stripe webhook uç noktası `invoice.paid`,
`invoice_payment.paid` ve `charge.refunded` ile kaydedilmeli (Ç120). Kayıt: `m6a-iptal-iade.md`.

### 2.4 Planlar — `/panel/planlar`

**Ne görürsün:** paywall'un sattığı planlar, para birimleri, teklif.

**Ne yapabilirsin**
- Plan aç: Stripe'ta ürün ve fiyat oluşur.
- Fiyat değiştir: mevcut aboneler eski fiyatta kalır.
- Para birimi başına tutar gir: boş bırakılan para birimi satılmaz.
- Teklif metnini düzenle: yalnız yeni ödemelere uygulanır.

**Arkada ne çalışıyor**
- `planlar` tablosu ve Stripe fiyatları.
- Ziyaretçinin para birimi IP'den gelen ülkeyle seçilir (DB-IP verisi, `app:ulke-verisi`). Paywall'daki birim
  ödemede `currency` ile zorlanır (§50).
- Ek satış: `ek_urunler`, `ek_satislar`, `POST /huni/ek-satis`.

**Kayıtlar:** `m1a-fiyat-katalogu.md`, `m1b-teklifler.md`, `m1c-cok-para-birimi.md`.

### 2.5 Deneyler — `/panel/deneyler`

**Ne görürsün:** deneyler, varyantlar, sonuçlar.

**Ne yapabilirsin**
- Taslakta varyant kur, sonra başlat ya da durdur.
- Deney aktif olunca sonuçlar karışmasın diye kilitlenir.

**Arkada ne çalışıyor**
- `deneyler` tablosu.
- Atama saklanmaz: sekme tohumu + deney adından belirlenir. Yalnız gösterilen varyant `ziyaret_deneyleri`'ne
  yazılır.
- Yönlendirme tipi deney trafiği hunilere böler; içerik tipi deney koşullarda `deney.ad` olarak kullanılır.
- MCP'den de kaydedilir (yönetim izni).

**Kayıtlar:** `m4a-deneyler.md`, `m4b-deney-sonuclari.md`.

### 2.6 Webhook'lar — `/panel/webhooklar`

**Ne görürsün:** uygulama başına giden webhook hedefleri ve teslim durumu.

**Ne yapabilirsin:** hedef ekle (adres + olay türleri; imza sırrı bir kez görünür), etkin/pasif yap, sil (iki adımlı onay), teslim günlüğüne bak.

**Arkada ne çalışıyor**
- `webhook_hedefleri` ve `webhook_teslimleri` tabloları.
- `app:webhook:gonder` olayları `olaylar`'dan imleçle okur. İmleç `(islem, id)`; bitmemiş işlem beklenir (§53).
- Gönderim Standard Webhooks imzasıyla ve özel ağa çıkmayan HTTP istemcisiyle yapılır.
- Kişisel veri gitmez, yalnız kimlikler. Teslim en az bir kez.

**Henüz ölçülmeyen:** cron tanımlı değil (üretim yok). Kayıtlar: `m7a-giden-webhook.md`, `m7b-webhook-paneli.md`.

### 2.7 Ürün bağlamı — `/panel/baglam`

**Ne görürsün / yapabilirsin:** uygulama başına ürün, kategori ve hedef kitle tanımı. AI huni metinlerini
yazarken bunu okur; boş alan gönderilmez.

**Arkada ne çalışıyor:** `uygulama_baglamlari` tablosu, her AI isteğinin sistem bağlamı.

### 2.8 API anahtarları — `/panel/anahtarlar`

**Ne görürsün / yapabilirsin**
- MCP anahtarı oluştur ya da iptal et. Anahtar bir kez gösterilir, saklanmaz; yalnız ilk karakterleri görünür.
- **"Abonelik ve deney yönetimi"** kutusu yazma araçlarını açar.

**Arkada ne çalışıyor**
- `api_anahtarlari` tablosu (özet ve `yonetim` bayrağı), `/mcp` uç noktası (Streamable HTTP).
- İzinsiz anahtar 13 araç görür: huni taslağı ve rapor okuma.
- İzinli anahtar 19 araç görür; fazladan abonelik listele / iptal planla / geri al / iade ve deney kaydet / durum.
- Çağrılar `mcp_cagrilari`'na yazılır.

**Kayıtlar:** `m7c1-okuma-araclari.md`, `m7c2-yazma-araclari.md`.

---

## 3. Ziyaretçi ve uygulama tarafı (panel dışı)

| Yüz | Ne | Kayıt |
|---|---|---|
| Huni sayfaları | Statik Vite + Preact oynatıcı; yayındaki tanımı `GET /huni/{app}` ile çeker. JS bütçesi `funnel-butce` adımında | `adresler.md` §3 |
| Başarılı sayfası | Ödemeden sonra tek kullanımlık giriş bağlantısı (pencere 60 dk, oturum başına 3) + reklam pikseli (Purchase / StartTrial, sunucu olayıyla aynı `event_id`) | `kimlik-hak.md`, `c118-deneme-rapor-reklam.md` |
| Abonelik sayfası | E-postayla portal bağlantısı ister (`/huni/portal-baglantisi`) | `m6b2-portal-web.md` |
| Mobil uygulama | Giriş bağlantısı ya da e-posta + 6 haneli kod → bağlama; hak sorgusu uygulamanın sunucusundan | `uygulama-entegrasyonu.md` |

---

## 4. Arka plan işleri

### 4.1 Gelen webhook'lar

| Uç | Kimden | Ne yazar |
|---|---|---|
| `POST /webhooks/stripe` | Stripe (imzalı) | `checkout.session.completed/expired`, `customer.subscription.*`, `invoice.paid`, `invoice_payment.paid`, `charge.refunded` → abonelik, oturum, fatura, iade, olay. Tekrar `processed_webhook_events` ile engellenir, eski olay yeniyi ezmez. Ardından RevenueCat, giriş e-postası ve reklam dönüşümü kuyrukları itilir |
| `POST /webhooks/postmark` | Postmark (HTTP Basic) | Subscription Change → `posta_bastirma` (A22, `m8a1-postmark.md`) |

### 4.2 Giden işler — kuyruk + komut (üretimde cron gerekir, **henüz hiçbiri tanımlı değil**)

| Komut | Ne yapar | Kuyruk |
|---|---|---|
| `app:webhook:gonder` | Olayları hedeflere imzalı gönderir | `webhook_teslimleri` |
| `app:reklam-donusumleri:gonder` | Başarısız Meta CAPI / TikTok olaylarını yeniden dener | `reklam_donusumleri` |
| `app:revenuecat:gonder` | Bekleyen RevenueCat aktarımları | `revenuecat_gidenler` |
| `app:reklam-harcamalari:cek` | Meta harcaması (son 7 gün) | `reklam_harcamalari` |
| `app:posta:dizi` | Aktivasyon dizisi: ödeyip bağlanmayana hatırlatma (15 dk cron) | `posta_dizisi` |
| `app:huni-metin-cevaplari-sil` | Süresi dolan serbest yanıtlar ve izinsiz e-postalar | — |
| `app:ulke-verisi` | DB-IP ülke verisi | — |

Giriş e-postası Stripe webhook'undan hemen sonra gider. Gidemeyen bir sonraki webhook'ta yeniden denenir; ayrı cron yok.
Bastırılmış adres atlanır.

### 4.3 E-posta

- Symfony Mailer. Dev'de Mailpit (`:8025`); testte `null://null` (§24); üretimde Postmark API (A22).
- İşlem e-postaları (`outbound` akışı): giriş kodu, giriş bağlantısı, portal bağlantısı.
- **Aktivasyon dizisi (M8a2, işlem e-postası, izin gerekmez):** ödeme e-postasından sonra 24. ve 72. saatte, uygulamaya
  hâlâ bağlanmamış kişiye yeni giriş bağlantısı (+ `MAGAZA_ADRESI_<APP>` doluysa App Store adresi). Durur: bağlandı,
  abonelik erişim vermiyor, adres bastırılmış. Gecikmiş adımlardan yalnız sonuncusu gider; 7 günden eski ödemeye
  başlamaz. Kayıt `posta_dizisi` (`m8a2-aktivasyon.md`).
- Terk edilen ödeme dizisi (M8b) bu bölüme eklenecek.
- **Postmark pasif alıcıda hata atmaz (§57)**, bastırma listesi bunun için var.

### 4.4 Tek seferlik / bakım komutları

`app:stripe-portal:kur` (portal ayarı koddan), `app:plan-ice-aktar`, `app:huni yayinla|onizleme`,
`app:huni-arac`, `app:huni-semasi`, `app:ai-degerlendir`, `app:olaylar <ziyaret>`, `app:ozellikler`,
`app:simge-dizini`.

---

## 5. Veri omurgası (tablolar ve görünümler)

| Grup | Tablolar |
|---|---|
| Huni | `huniler`, `huni_surumleri`, `huni_kurallari`, `medya`, `uygulama_baglamlari`, `ai_istekleri` |
| Ziyaret | `huni_ziyaretleri`, `huni_adimlari`, `huni_cevaplari`, `huni_metin_cevaplari`, `huni_epostalari` (`pazarlama_izni_at`), `ziyaret_deneyleri`, `deneyler` |
| Olay deposu | `olaylar` (yalnız ekleme; güncelleme tetikleyiciyle reddedilir; kaynak satırla aynı işlemde, yalnız durum değişince) · görünümler `ziyaret_olaylari`, `ziyaret_ozellikleri` |
| Para | `planlar`, `ek_urunler`, `ek_satislar`, `odeme_oturumlari`, `subscriptions`, `odeme_faturalari`, `fatura_odemeleri`, `processed_webhook_events` |
| Kimlik | `kisiler`, `baglantilar`, `dogrulama_kodlari`, `giris_jetonlari`, `portal_jetonlari` |
| Dışa | `reklam_donusumleri`, `reklam_harcamalari`, `revenuecat_gidenler`, `webhook_hedefleri`, `webhook_teslimleri`, `posta_bastirma` |
| Erişim | `api_anahtarlari`, `mcp_cagrilari`, `sessions` |

Sayım 2026-11-10'da dev DB'den alındı: 36 tablo + 2 görünüm (göç tablosu hariç).

---

## 6. Değişmez mimari kurallar (kod yazarken)

1. **Sözleşme önce:** yeni alan önce `contract/openapi.yaml`'a yazılır, tipler `npm run uret` ile üretilir.
   `sozlesme-guncel` adımı bayt bayt karşılaştırır.
2. **Analitik için yeni tablo açılmaz:** olaylar `olaylar`'dan, özellikler `ziyaret_ozellikleri`'nden okunur.
3. **Stripe webhook'u:** tekrar engeli + zaman damgası. Alanlar gerçek nesneden okunur (yeni API, §54).
4. **İzin:** reklam pikselleri ve `fbclid`/`ttclid` saklama yalnız izinle (H6a). Pazarlama e-postası yalnız
   `pazarlama_izni_at` doluysa (A22).
5. **Kişisel veri:** log'a, giden webhook'a ve MCP listesine tam e-posta gitmez (maskeli ya da yalnız kimlik).
6. **AI yalnız taslağa yazar**; yayınlamak ve para işlemleri insanda ya da izinli anahtarda.
7. **Sıralama:** Postgres ↔ JS eşitliği için `COLLATE "C"` (§41).

---

## 7. Canlıdan önce açık olanlar (kısa liste)

Ayrıntı `acik-uclar.md`. En önemlileri:
- Stripe webhook kaydı (Ç120) ve Meta hesabı (Ç121).
- Postmark hesabı ve DNS (Ç30, Ç123).
- Cron'lar (4.2).
- Composer güvenlik güncellemeleri (Ç122).
- Panel ekranlarının tarayıcıda gözden geçirilmesi.
