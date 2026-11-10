# M9b1 — yasal veriler ve metinler (2026-11-10)

Karar `pre-setup.md` A23 (kullanıcı: taslak + düzenlenebilir; şartlar, gizlilik, KVKK, çerez; tr + en) · önceki `m9a-uygulama-kimligi.md`.

## Ne yapıldı

- **Panel Yasal metinler** (`/panel/yasal`):
  - şirket unvanı, adres, kayıt no;
  - 4 belge × 2 dil: kaydedilmemişse taslak gösterilir ve **yayında değildir**, "Kaydet ve yayınla" yayınlar;
  - boş yer tutucu uyarısı; sayfanın üstünde "taslaktır, hukuki görüş değildir" notu.
- **Uçlar:** `GET /panel/api/uygulamalar/{app}/yasal`, `PUT …/yasal/veriler`, `PUT …/yasal/{belge}/{dil}` (sözleşme
  `PanelYasal`, `YasalVeriler`, `YasalMetin`; CSRF).
- **Herkese açık sayfa** `GET /yasal/{app}/{belge}/{dil}`: Twig, dış betik yok, `lang` belgenin dilinde, "Son güncelleme".
  - Biçim: `## ` başlık, `- ` madde, `**kalın**`, boş satır paragraf; hepsi kaçışlanır, HTML yorumlanmaz.
  - Yer tutucular (`{{uygulama}} {{unvan}} {{adres}} {{kayitNo}} {{eposta}}`) çizilirken dolar: ayar değişince sayfa
    yeniden kaydedilmeden güncellenir. Boş değer "—".
- **Taslaklar** (`Taslaklar.php`) ölçülmüş davranışı anlatır:
  - serbest yanıt ve izinsiz e-posta 90 gün; IP ve tarayıcı bilgisi ödemede ya da en geç 48 saatte silinir;
  - piksel ve tıklama kimliği yalnız izinle; tarayıcıda izin tercihi ve sekmelik ziyaret kimliği;
  - en fazla iki kurtarma e-postası; fiyat değişince mevcut abone eski fiyatta kalır;
  - işlemciler: Stripe, Postmark, RevenueCat, Meta, TikTok.
- Göç `Version20261110220000`.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Kaydetmek = yayınlamak, taslak yayında değil | taslağı hemen yayınlamak | Taslak hukuki görüş değil; yönetici okuyup onaylamalı |
| 2 | Yer tutucu çizimde dolar | kayıtta doldurmak | Unvan / adres değişince sekiz metni yeniden kaydetmek gerekmesin |
| 3 | Bilinmeyen yer tutucu reddedilir | olduğu gibi göstermek | Yazım hatası sayfada `{{sirket}}` olarak kalmasın |
| 4 | Kısıtlı biçim + kaçış | HTML / tam Markdown | XSS kuralı (D1); hukuki metne yetiyor |
| 5 | Taslakta sistemin yapmadığı vaat yok | genel şablon | "Fiyat değişimi önceden bildirilir", "e-postayla bildiririz", "sorumluluk 12 ayla sınırlı" çıkarıldı: ilk ikisini yapan kod yok, üçüncüsü hukuki seçim |
| 6 | Herkese açık uçta belge / dil / uygulama denetimi yok | listeye karşı denetlemek | Kaydedilmemiş satır zaten 404 ve DB kısıtı başka belgeyi kabul etmez; denetim bir şey ölçmüyordu (§10) |
| 7 | İletişim e-postası = Ayarlar'daki destek e-postası | ayrı alan | Aynı adres iki yerde tutulmasın (kural 7) |

## Test

- `PanelYasalTest` (14):
  - girişsiz yönlendirme ve taslak 404;
  - panelde 8 belge, taslak, eksikler, adresler;
  - kaydet → sayfa 200: yer tutucular dolu, `<script>` kaçışlı, başlık / liste / kalın, `lang`, tarih; öteki dil 404;
  - ayar değişince sayfa güncellenir, boş değer "—";
  - 5 geçersiz metin 422 ve yayında değil;
  - bilinmeyen belge / dil / uygulama 404, jetonsuz 403;
  - veri doğrulama; ayar satırı yoksa 422;
  - **8 taslağın hepsi** kaydedilip çiziliyor (`{{` kalmıyor);
  - DB kısıtı; `/panel/yasal` 200 (§51).
- Panel `Yasal.test.tsx` (3): uyarı / taslak / eksik / menü; dil seçimi, tr ve en kendi adresine jetonla, yanıt
  "Yayında"; veriler tamamı + 422 satırda.

## Kırma (14/14 🔴)

Y1 taslak yayında · Y2 `|raw` · Y3 yer tutucu doldurulmaz · Y4 bilinmeyen yer tutucu kabul · Y5 boş değer "" ·
Y6 eksikler hep dolu · Y7 ayar satırı yokken başarılı · Y8 metin kaydında CSRF yok · Y9 kalın yok · Y10 liste yok ·
Y11 kırpma yok · Y12 panelde belge / dil denetimi yok (DB kısıtı 500) · P1 dil hep tr · P2 veriler eksik gönderilir —
hepsi 🔴. Ayrıştırıcı PHPStan için yeniden yazıldıktan sonra Y9–Y11 yeniden koşuldu, 🔴.

## Ölçüm ve tuzak

- §48 yeniden ölçüldü: tırnaksız virgüllü `description` üretilen TS tipinde sessizce kesildi (EXIT=0), PHP sözleşme
  testi reddetti.
- **Dev:**
  - kaydedilmemiş sayfa 404, `/panel/yasal` girişsiz 302;
  - geçici satırla sayfa 200: `{{uygulama}}` → KeepChat, `<b>` kaçışlı, `{{unvan}}` boş → "—";
  - satır silinince 404.
- Panel sayfası tarayıcıda denenmedi (giriş). Hukuki gözden geçirme yok (Ç126).
- `bin/kontrol` YEŞİL, core 722 test.
