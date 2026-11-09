# Sıralı Teknik Mimari Notu (Developer Architectural Runbook)

> Bu belge, MyWeb2Wave projesinin kod seviyesindeki 8 sıralı teknik adımını, dosya yollarını, kaputun altında dönen mekanizmaları ve kritik kuralları içerir.

---

### 1. ADIM: Sözleşme & Tip Üretimi (Tek Hakiki Kaynak)
* **İlgili Dosya:** [`contract/openapi.yaml`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/contract/openapi.yaml)
* **Teknoloji:** OpenAPI 3.1 + `openapi-typescript`
* **Kaputun Altında Ne Oluyor?**
  * Huni tanımı (`HuniTanimi`), adımlar (`EkranAdimi`, `PaywallAdimi`) burada tanımlanır.
  * `npm run uret` komutu çalışınca TypeScript tipleri otomatik üretilip hem huniye (`funnel/src/sozlesme/api.ts`) hem panele (`core/panel/src/sozlesme.ts`) basılır.
* **⚠️ Kritik Kural:** Kodda elle yeni bir alan (prop) uydurulamaz. Önce bu YAML değişir, tipler üretilir; yoksa `bin/kontrol` derlemeyi kırar (Kural R2).

---

### 2. ADIM: Panelde Tasarım & Veri Üretimi
* **İlgili Dosyalar:** 
  * [`core/panel/src/duzenleyici.tsx`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/panel/src/duzenleyici.tsx) (Puck Editörü)
  * [`core/panel/src/donustur.ts`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/panel/src/donustur.ts) (Kayıpsız Çevirici)
* **Teknoloji:** React 19 + `@puckeditor/core`
* **Kaputun Altında Ne Oluyor?**
  * Puck ekrandaki blokları kendi iç ağaç yapısında tutar.
  * "Kaydet" dendiğinde `donustur.ts`, Puck ağacını OpenAPI uyumlu `HuniTanimi` JSON formatına çevirir.
  * `PUT /panel/api/uygulamalar/{app}/huniler/{ad}/taslak` ucuna HTTP gövdesi olarak fırlatır.
* **⚠️ Kritik Kural:** `Tanim -> Puck -> Tanim` çevirisi kayıpsızdır (`donustur.test.ts` ile korunur).

---

### 3. ADIM: Backend Güvenlik Bekçisi & JSONB Depolama
* **İlgili Dosyalar:**
  * [`core/src/Huni/Tanim.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/src/Huni/Tanim.php) (Güvenlik Bekçisi)
  * [`core/src/Controller/PanelHuniApiController.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/src/Controller/PanelHuniApiController.php) (Controller)
* **Teknoloji:** Symfony 7.4 + Doctrine DBAL + PostgreSQL 18
* **Kaputun Altında Ne Oluyor?**
  * `Tanim::dogrula()`: Gelen JSON'daki metinleri tarar. İçinde HTML tagi (`<script>`, `<div>` vb.) varsa 400 hatası basıp reddeder (XSS Kalkanı — Tuzak D1).
  * Temizse, PostgreSQL'e gidip `UPDATE huniler SET taslak = :json` diyerek tek bir `jsonb` sütununa yazar.
* **⚠️ Kritik Kural:** Sorular ve şıklar için ayrı ilişkisel tablolar yoktur. Huni bir bütün olarak JSONB hücresinde yaşar.

---

### 4. ADIM: Canlı Huni Oynatıcı & Koşul Motoru
* **İlgili Dosyalar:**
  * [`funnel/src/oynatici/Huni.tsx`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/funnel/src/oynatici/Huni.tsx) (Oynatıcı Adacığı)
  * [`huni-bilesenleri/kosul.ts`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/huni-bilesenleri/kosul.ts) (Dallanma Motoru)
* **Teknoloji:** Astro + Preact (compat) + Vanilla CSS (`huni.css`)
* **Kaputun Altında Ne Oluyor?**
  * `Huni.tsx` açılır açılmaz `GET /huni/{app}` ucundan veritabanındaki JSON'ı çeker.
  * Kullanıcı bir şıkka tıkladığında `kosul.ts` devreye girer: `yonlendir()` (ilk tutan kurala zıpla), `grupTutar()` (şartı sağlamayan adımı atla).
  * Kullanıcı her adım değiştirdiğinde arka planda `POST /huni/ziyaretler/{id}/ilerleme` atılır.
* **⚠️ Kritik Kural:** Toplam JavaScript boyutu 30 KB gzip sınırının altındadır (`funnel-butce` testi bunu denetler).

---

### 5. ADIM: Sıfır-Ek-Tablo Analitiği (SQL Görünümleri)
* **İlgili Migration:** [`core/migrations/Version20261103100000.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/migrations/Version20261103100000.php)
* **Teknoloji:** PostgreSQL `VIEW` (Event Sourcing)
* **Kaputun Altında Ne Oluyor?**
  * Ziyaretçinin adımları ve cevapları `huni_adimlari` ve `huni_cevaplari` tablolarına sadece "ekleme" (append-only) yapılır.
  * M5a (2026-11-09, [`Version20261109120000.php`](../core/migrations/Version20261109120000.php)): olaylar yalnız ekleme yapılan `olaylar` tablosuna, kaynak satırla aynı işlemde ve yalnız durum değişince yazılır (`huni.ziyaret`, `adim.goruldu`, `cevap.verildi`, `izin.*`, `odeme.*`, `abonelik.durum`). `ziyaret_olaylari` bu tablonun görünümü (panel grafikleri). Eski T4 görünümü son hâlden türetiyordu — geçmiş kayboluyordu (Ç92).
* **⚠️ Kritik Kural:** Analitik için başka tablo açılmaz; olaylar `olaylar`dan, özellikler `ziyaret_ozellikleri` görünümünden okunur.

---

### 6. ADIM: Stripe Tahsilatı & Webhook Güvenliği
* **İlgili Dosyalar:**
  * [`core/src/Controller/CheckoutController.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/src/Controller/CheckoutController.php)
  * [`core/src/Billing/SubscriptionSync.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/src/Billing/SubscriptionSync.php)
* **Teknoloji:** Stripe Checkout + Webhook + Doctrine Transaction
* **Kaputun Altında Ne Oluyor?**
  * Paywall'da `POST /checkout/sessions` çağrılır (`metadata.app = "keepchat"` yazılır).
  * Ödeme bitince Stripe webhook atar (`checkout.session.completed`).
  * `SubscriptionSync`:
    1. Gelen `event_id`'yi `processed_webhook_events` tablosuna dener (Aynı webhook ikinci kez gelirse PK kısıtından reddedilir - Idempotency).
    2. Zaman damgasını kontrol eder (`status_event_created`), eski olay yeniyi ezemez.
    3. `subscriptions` tablosuna `status = 'active'` yazar.
* **⚠️ Kritik Kural:** `Hak::ERISIM_VEREN` listesinde `past_due` vardır. Kart reddedilse bile Stripe tekrar denerken kullanıcı aktif kalır (Grace Period).

---

### 7. ADIM: Mobil Uygulama Entegrasyonu (KeepChat Bağlantısı)
* **İlgili Dosyalar:**
  * [`core/src/Kimlik/DogrulamaKodu.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/src/Kimlik/DogrulamaKodu.php) (E-posta Kodu)
  * [`core/src/Kimlik/Hak.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/src/Kimlik/Hak.php) (Erişim Sorgusu)
* **Teknoloji:** HMAC-SHA256 Token + Multi-Tenant Eşleşme
* **Kaputun Altında Ne Oluyor?**
  * Kullanıcı KeepChat iOS uygulamasında "Aboneliğimi Bağla" der, e-postasını yazar.
  * `DogrulamaKodu`: 6 haneli kod üretir, veritabanına HMAC özetiyle kaydeder (10 dk geçerli).
  * Kod doğrulanınca `baglantilar` tablosuna `keepchat + ahmet123 <-> kisi_id` kaydı atılır.
  * KeepChat sunucusu çekirdeğe `GET /apps/keepchat/kullanicilar/ahmet123/hak` diye sorar. Çekirdek `baglantilar -> kisiler -> subscriptions` JOIN sorgusu atıp `{"aktif": true}` döner.
* **⚠️ Kritik Kural:** Mobil uygulamanın içinde gizli anahtar (secret key) YOKTUR. Sorguyu uygulamanın sunucusu `Bearer` token ile yapar.

---

### 8. ADIM: Otonom AI & Headless Chrome Ekran Görüntüsü
* **İlgili Dosyalar:**
  * [`core/src/Ai/HuniAsistani.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/src/Ai/HuniAsistani.php) (Claude Asistanı)
  * [`core/src/Huni/Ekran/ChromeEkranCekici.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/src/Huni/Ekran/ChromeEkranCekici.php) (Görsel Denetim)
  * [`core/src/Mcp/McpSunucusu.php`](file:///Users/jacobthemillicano/Desktop/MyWeb2Wave/core/src/Mcp/McpSunucusu.php) (MCP Sunucusu)
* **Teknoloji:** Anthropic PHP SDK (Prompt Caching) + Chromium CDP (Port 9222) + Streamable HTTP MCP
* **Kaputun Altında Ne Oluyor?**
  * AI panelden brief alır, `HuniAraclari` ile taslağı JSON olarak günceller.
  * `ChromeEkranCekici`: Ayrı Docker kabındaki Chrome'a CDP ile bağlanır, 390×844 boyutunda sayfayı açar, yükleme bitince (`HAZIR` JS) ekran görüntüsü alıp JPEG olarak AI'a iletir.
  * AI tasarladığı sayfayı kendi gözüyle görüp hatalarını düzeltir.
* **⚠️ Kritik Kural:** AI yalnızca `taslak` üzerinde çalışabilir. Canlıya yayınlama (`huni_surumleri`) yetkisi kesinlikle insandadır.
