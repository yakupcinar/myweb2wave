# L7 — Paywall fayda listesi + yasal bağlantılar (Ç61 (3))

2026-10-26 · karar `pre-setup.md` A13. Metinleri / adresleri kullanıcı
Puck'ta yazar (Ç12/Ç36/Ç56 hukuk metni ayrı).

## Ölçüm (önce)
Paywall yalnız başlık + fiyat satırı + düğme (tarayıcıda görüldü,
2026-10-26). Tanımda fayda ya da şartlar/gizlilik/iptal alanı yoktu;
`Tanim.php` paywall'da yalnız `baslik, metin, dugme, plan` kabul ediyordu.

## Karar
- Tanım (sözleşme `PaywallAdimi` + `Tanim.php`): isteğe bağlı
  `faydalar` (1–6 öğe, her biri 1–120 karakter) ve `sartlar`, `gizlilik`,
  `iptal` (yalnız `https://`, boşluksuz, ≤ 500). **Neden yalnız https:**
  `javascript:` / `data:` adresi huni sayfasında tıklanınca betik
  çalıştırırdı; doğrulayıcı çekirdek (sözleşme deseni belgeler, yanıt
  testleri yalnız geçerli veriyle koşar).
- Bileşen (`Paywall`): faydalar fiyat satırının altında `ul.faydalar`;
  bağlantılar düğmenin altında `p.yasal`, sabit adlar ("Kullanım şartları",
  "Gizlilik politikası", "İptal ve iade"), `target=_blank`,
  `rel="noopener noreferrer"`. Boşsa hiçbiri çizilmez.
- Puck: Paywall'da "Faydalar" dizi alanı (en çok 6) + üç adres alanı;
  boş metin / boş adres tanıma yazılmaz (`yasal()`), çekirdek boş listeyi
  reddettiği için.
- **Reddedilen:** bağlantı adlarını da düzenlenebilir yapmak (istenmedi,
  kural 6a) · göreli adres (huni ayrı alanda; mutlak adres şart) · fayda
  başına simge (istenmedi).

## Test
Çekirdek: 7 yeni bozuk örnek (liste değil, 7 öğe, boş öğe, http,
`javascript:`, boşluklu adres, paywall dışında alan) + geçerli tanım +
yayından dönen yanıt sözleşmeye uyar ve alanları taşır (66 HuniTest).
Bileşen 27/27 (+3; XSS sayımı faydayı da kapsar, 15 kaçış). Düzenleyici
41/41: gidiş-dönüş, boş alan yazılmaz, önizleme = huni (H3) yeni alanlarla.
Huni 47/47.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `https?://` (http kabul) | 🔴 `http bağlantı` |
| K2 | 7 fayda kabul | 🔴 `yedi fayda` |
| K3 | fayda metni doğrulanmaz | 🔴 `boş fayda` |
| K4 | `rel="opener"` | 🔴 bileşen bağlantı testi |
| K5 | boş listede de `ul` | 🔴 "boşsa hiç yok" |
| K6 | boş `faydalar` tanıma yazılır | 🔴 8 düzenleyici testi |
| K7 | `sartlar` Puck'a taşınmaz | 🔴 gidiş-dönüş + önizleme |

## Yan bulgu — L6 testi aralıklı kırmızıydı
L6'nın "Geri düğmesi" testi bir koşuda `history.state` `null` verdi: ilk
`replaceState({adim: 0})` effect'te; test effect'i beklemeden tıklıyordu
(tuzak §33). Var olan D-testi gibi `waitFor(history.state)` eklendi; üç
ardışık koşu 47/47.

## Tarayıcı (Chrome, yerel)
Kullanıcının hunilerine dokunmamak için önceki bloktan kalan deneme hunisi
`b1deneme`'nin taslağı yedeklendi, paywall'a 3 fayda + 3 bağlantı SQL ile
eklendi, `app:huni onizleme` bağlantısıyla açıldı: koyu temada liste ve
bağlantı satırı çizildi; bağlantılar `href` doğru, `rel="noopener
noreferrer"`, `_blank`. Taslak yedekten geri yüklendi, JSON karşılaştırması
**AYNI**.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 216 çekirdek testi / 1.350 doğrulama.
