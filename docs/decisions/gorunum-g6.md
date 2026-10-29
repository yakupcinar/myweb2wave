# G6 — içerik kartı (2026-10-27)

Kullanıcı: "başla" (G6). G1 ölçümü: içerik kartı (C9) düzen farkında %89 — renk değiştirmeden "başka ürün" görünümü (`gorunum-g1.md`).

## Karar
- **Sözleşme:** `HuniTemasi.kartRengi` isteğe bağlı (`#rrggbb`). Nesne değil tek alan — biçim/gölge seçeneği G1'de ölçülmedi, ileride gerekirse eklenir.
- **Okunurluk kuralı değişti (`Tema.php`):** kart varsa metin kartın üstündedir → ölçüt **metinRengi ↔ kartRengi**; zemin (arkaPlan, G3 geçiş/görsel) yalnız çevrede kalır, metin kontrastı ondan istenmez — **biçimi** yine denetlenir. Sonuç: koyu zemin + açık kart + koyu metin artık geçerli (kartsız aynı tema "arka planda okunmuyor 1,72:1" ile reddedilir). Puck'taki canlı uyarı aynı kurala çekildi (kural 7).
- **Çizim:** kart varsa `--arka` = kart rengi (iç yüzeyler, çizgiler, soluk metin, alt çubuk solması kartla karışır), `--zemin` = G3 zemini ya da arkaPlan, `--kart`, `--kart-kose` (köşe + 8), `--kart-bosluk` 12px, `--kart-golge`. Zemin artık **sayfa kökünde** (`html:has(> body.huni)`), `.huni` kartsızken saydam → kartsız görünüm aynen.
- **CSS kapsam bekçisi:** "her kural `.huni` altında" testine tek, gerekçeli istisna: `html:has(> body.huni)` yalnız gövdesi `.huni` olan huni sayfasında eşleşir; Puck'ta `.huni` bir `div`, düzenleyici boyanmaz.
- **Yan boşluk:** ilk çekimde kart tüm genişliği kaplıyor, zemin yalnız üst/altta ince şeritti (G1'deki etki çevredeki zeminden geliyordu) → `max-width: min(520px, calc(100% - 32px - 2 × boşluk))`; kartsız boşluk 0 → eski genişlik.
- **AI:** yönerge kartRengi'ni anlatır; "finans" örneği kartlı (koyu lacivert zemin + beyaz kart + koyu metin — kartsız geçersiz olurdu).
- Reddedilen: kartı `main`'e koymak (masaüstünde zaten `.huni` kart — iç içe kart olurdu) · kart varken de metin ↔ arkaPlan istemek (kartın açtığı tasarımı kapatırdı, metin zeminde değil).

## Ölçüm
- Testler: `TemaTest` (kart kuralı, oranlar Python'da bağımsız 1,72 / 17,85; görsel zemin + kart → karartma 0 geçerli, biçim yine denetlenir; AI'da kartlı örnek) · `tema.test.ts` (değişkenler, CSS kökü, saydam gövde, gölge, genişlik; bekçi istisnası) · `donustur.test.ts` (gidiş-dönüş, "yok") · `onizleme.test.tsx` (Puck dış kutusu + canlı uyarı kart kuralıyla).
- Kırma 11/11 kırmızı: K1 `kartRengi` alan denetimine takılır · K2 kontrast hâlâ arka planla · K3 kartta zemin kontrastı yine istenir · K4 kart biçimi denetlenmez · K5 iç yüzeyler kartla karışmaz · K6 kart + düz zeminde `--zemin` yok · K7 gövde kartı çizmez · K8 yan boşluk yok · K9 Puck'tan kart çıkmaz · K10 Puck uyarısı eski kural · K11 AI örneğinde kart yok (örnek geçersizleşir).
- Gerçek sistem (konsol aracı, taslak yedeklendi → **aynen geri yüklendi**, tanım + imza eşit): kartsız koyu tema reddedildi (1,72:1); kartlı düz zemin ve kartlı görsel zemin (%0 karartma) kaydedildi, üç adım çekildi; yan boşluk düzeltmesinden sonra zemin kartın dört yanında.
- Bir koşu 300 sn'yi aştı → tek çekim yeniden ölçüldü: **2 sn** (gerileme yok; geçici yavaşlık oturumun duraklamasıyla aynı ana denk geldi, kaynak ölçülmedi).
- `bin/kontrol` yeşil (285 çekirdek testi).
- CI: koşu 40555689715, SHA 8ee1e6a, `KONTROL: YEŞİL`, 285 test.
- **Ölçülmedi:** Puck düzenleyicide yeni alanların görsel kontrolü; iOS Safari `:has` (15.4+) ve gölge görünümü.
