# P6 — panel görünümü Apple tarzı (2026-11-10)

Kullanıcı: "stil güzel olmuş, bu tarz uygula; ileride değişiklikler bu stilde kalır". Taslak Design tuvalinde
(özel artifact; `web2wave-platform-yapisi.md` § Ek arayüz). web2wave yapı referansı, görsel dil Apple'dan esinlenme
(Apple'ın kendi tasarımının kopyası değil).

## Ne yapıldı

- **Renk değişkenleri** ([`stil.css`](../../core/panel/src/uygulama/stil.css)): zemin `#f5f5f7`, yüzey beyaz, metin
  `#1d1d1f`, ikincil `#6e6e73`, vurgu `#0066cc`. Koyu modda zemin siyah, yüzey `#1c1c1e`, vurgu `#4c9dff` ve üzerinde
  siyah yazı. Yazı tipi `-apple-system / SF Pro`, gövde 14 px.
- **Menü** ([`Duzen.tsx`](../../core/panel/src/uygulama/Duzen.tsx)):
  - dört grup: Analiz · Satış · Müşteriler · Ayarlar; seçili öğe dolu mavi;
  - dar ekranda grup adları gizli, bağlantılar tek satırda kayar;
  - ad değişiklikleri: Kullanıcılar → **Aboneler**, Planlar → **Planlar ve fiyatlar**, Ayarlar → **Uygulama**,
    Ürün bağlamı → **Ürün bağlamı (AI)**. Adresler aynı.
- **Ortak bileşenler:**
  - sayfa başlığı 34 px, kart çerçevesiz 14 px köşe (koyu modda ince çerçeve);
  - tablo başlığı sade (büyük harf ve gri bant yok), düğmeler hap, `Rozet` tehlike rengi.
- **Bölmeli seçici:** Rapor'daki dönem seçimi ve Yasal'daki dil seçimi.
- **Sayfa açıklamaları sadeleşti:** ne işe yaradığını söyleyen tek cümle, teknik ayrıntı yok. Örnekler:
  - "Ziyaret, ödeme ve anket" → "Ziyaretçilerin kaçı abone oldu ve ne kadar kazandırdı";
  - Webhook'lar: "(Standard Webhooks); … teslim en az bir kez" → "Kişisel veri gönderilmez; aynı olay iki kez gelebilir".
- **Giriş sayfası** ve Twig kabuğu ([`base.html.twig`](../../core/templates/base.html.twig)): aynı renkler, aynı
  marka işareti, 44 px alanlar ve hap düğme.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Görünüm değişkenler + ortak bileşenler üzerinden | sayfa sayfa yeniden yazmak | Sayfalar renkleri adla kullanıyor; tek yerden değişiyor |
| 2 | Taslaktaki yeni işlevler yok (önceki dönem, canlıya geçiş listesi, sayfa birleştirme) | bu blokta yapmak | Kullanıcı: "işlevle ilgili küçük şeyler değişebilir"; ayrı bloklar (R1, kurulum listesi) |
| 3 | Vurgu `#0066cc` (Apple bağlantı mavisi), `#0071e3` değil | Apple düğme mavisi | Açık gri zeminde `#0071e3` 4.31:1 (AA altı), `#0066cc` 4.99 |
| 4 | İkincil `#6e6e73`, `#8e8e93` değil | Apple'ın gerçek ikincil grisi | `#8e8e93` beyazda 3.3:1; kırma R1 gösteriyor |
| 5 | Koyu vurguda siyah yazı | beyaz yazı | Beyaz / `#4c9dff` 2.7:1 |
| 6 | HIG skill'i ve UI kit kurulmadı | kurmak | Değerlendirme `web2wave-platform-yapisi.md` § Ek arayüz |

## Test

- `PanelRenkleriTest` (4, açık ve koyu):
  - iki kopya (stil.css ↔ base.html.twig) aynı;
  - 6 metin rengi × 3 zemin ≥ 4.5:1 ve düğme yazısı / vurgu ≥ 4.5:1;
  - hesap WCAG formülüyle, koddan bağımsız.
- Panel testleri 158/158 (menü adına bakan 3 test yeni adla).

## Kırma (4/4 🔴)

R1 ikincil gri `#8e8e93` · R2 Twig'de vurgu farklı · R3 koyu modda düğme yazısı beyaz · R4 Twig'de uyarı rengi
farklı — hepsi 🔴.

## Gerçek sistem (2026-11-10, dev)

- Panel derlendi; giriş sayfası `ekran` kabındaki başsız Chromium ile açık ve koyu modda görüntülendi: yeni renkler,
  marka işareti, hap düğme doğru.
- **Panelin iç sayfaları tarayıcıda görülmedi:** giriş gerekiyor (şifre kullanıcıda), Chrome uzantısı bağlantısı
  koptu. Kullanıcı gözden geçirecek.
- `bin/kontrol` YEŞİL.
