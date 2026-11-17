# M2b — teklif bitişi + sayaç, güven rozetleri, şart onayı (2026-11-04)

Karar `pre-setup.md` A15 (sayaç yalnız gerçek bitişe; süre dolunca teklif gerçekten
kalkar) · önceki bloklar `m1b-teklifler.md`, `m2a-plan-secici.md`.

## Ölçüm (önce)

- Teklifin (M1b) süresi yok; paywall'da aciliyet unsuru yok. Tasarım yönergesi AI'a
  "sayaç ya da sınırlı teklif yazma" diyor.
- Paywall'da güven unsuru yalnız yöneticinin serbest fayda maddeleri; şart onayı yok —
  ödeme düğmesi doğrudan Stripe'a götürüyor, onayın kanıtı yok.
- Teklif üç yerden okunuyor (huni fiyatı, panel listesi, ödeme) ama hepsi
  `Planlar::teklifler`'den — tek bekçi koymaya uygun.

## Kararlar

| # | Karar | Elenen | Neden |
|---|---|---|---|
| 1 | Bitiş **teklifin kendisinde** (`planlar.teklif_bitis`, herkes için aynı), sayaç ona sayar | paywall'da ayrı sayaç tarihi | A15: süre dolunca teklif GERÇEKTEN kalkmalı — paywall'daki tarih ödemeyi bağlamazdı |
| 2 | Tek bekçi: `teklifler()` süresi geçmişi döndürmez (SQL `teklif_bitis > now()`) | her okuyucuda ayrı denetim | Paywall, panel, ödeme aynı yerden; biri unutulamaz |
| 3 | İstemci de saniyelik saatle teklifi düşürür (yalnız bitişli teklif varken saat işler) | sayfa yenilenene dek eski teklifi göstermek | Sayaç 0'da teklif metni ekranda kalırsa ödeme denemesiz açılır — metin yalan olurdu |
| 4 | Bitiş: saat dilimli ISO 8601, gelecekte, ≤ 1 yıl; teklifsiz planda yok (DB kısıtı da) | serbest metin; saat dilimsiz | Sunucu ve tarayıcı farklı saat diliminde aynı anı anlasın |
| 5 | Süresi geçen satır **silinmez**, yalnız sunulmaz (panelde "—") | otomatik temizleme | Geçmiş teklifin izi kalır; yönetici yenisini koyar |
| 6 | Güven rozetleri sabit katalog, yalnız kurgusu gereği doğru olanlar: `guvenli-odeme` ("Güvenli ödeme · Stripe"), `kart-saklanmaz` | iptal / iade / "binlerce kullanıcı" rozetleri | İptal yolu M6'da yok; iade yöneticinin politikası; sayı uydurma yasak (yönerge) |
| 7 | Şart onayı: `sartOnayi: true` (yalnız `sartlar` adresiyle) → kutu işaretlenmeden düğme kapalı; onay ödemede `metadata.sart_onayi=1` (oturum + abonelik) | Stripe `consent_collection` | Onay bizim sayfamızda, bizim şart adresimizle; kanıt Stripe kaydında. Yalnız `true` kanıt sayılır |
| 8 | Sayaç fiyat kartlarının üstünde, yapışık değil | yapışık şerit | Yapışık alan (düğme + koşul + rozet) zaten dolu; ölçülmüş ihtiyaç yok → Ç111 |
| 9 | AI yönergesi: rozetler yalnız katalogdan; sayaç/aciliyet metni yazılmaz, ekran gerçek bitişi kendisi gösterir | — | Tasarım yönergesinin dürüstlük kuralıyla aynı yön |

## Test

`TeklifBitisiTest` (10): bitiş yazılır, listede UTC (İstanbul 23:59 → 20:59Z, elle);
5 geçersiz (geçmiş, > 1 yıl, saat dilimsiz, bozuk, teklifsiz) Stripe'a gitmez; aynı
teklif bitişi gelecekteyken huni + ödemede VAR, geçmişteyken YOK (kontrol grubuyla);
DB kısıtı; şart onayı oturum + abonelik metadata'sında, `"evet"` kanıt sayılmaz.
`PlanSeciciTest` +1 (rozet kataloğu, tekrar, boş; onay adres ister, yalnız true).
Bileşen: `kalanSure` (gün, iki hane, 0/eksi → yok). Oynatıcı: sayaç 2 sn'lik gerçek
bitişe sayar, dolunca sayaç ve teklif paywall'dan kalkar (gerçek saat); onaysız düğme
kapalı, onayla ödeme `sartOnayi: true`, rozet metinleri. Panel: Puck gidiş-dönüş
(tekrar/boş rozet ve "hayır" yazılmaz), teklif formu bitişi milisaniyesiz UTC gönderir.

## Kırma (17 deneme, hepsi kırmızı)

| # | Bozulan | Sonuç |
|---|---|---|
| K1 | `teklifler()` süre bekçisi yok | 🔴 |
| K2 | geçmiş bitiş kabul | 🔴 |
| K3 | 1 yıl sınırı yok | 🔴 |
| K4 | teklifsiz planda bitiş kabul (DB kısıtı da yakaladı) | 🔴 |
| K5 | bitiş yazılmaz | 🔴 |
| K6 | bitiş huniye gitmez | 🔴 |
| K7 | onay kanıtı yok | 🔴 |
| K8 | `"evet"` de kanıt sayılır | 🔴 |
| K9 | rozet kataloğu denetimi yok (PHPStan düzeltmesinden sonra yeniden: 🔴) | 🔴 |
| K10 | onay şart adresi istemez | 🔴 |
| K11 | istemci süreyi uygulamaz | 🔴 |
| K12 | saat işlemez | 🔴 |
| K13 | onaysız düğme açık | 🔴 |
| K14 | onay ödemeye gitmez | 🔴 |
| K15 | sayaç gün göstermez | 🔴 |
| K16 | Puck onayı tanıma yazmaz | 🔴 |
| K17 | panel bitişi milisaniyeli gönderir | 🔴 |

## Gerçek istek (Stripe test API + geliştirme)

- Gerçek `Planlar::teklifAyarla`: deneme 7 gün, bitiş +150 sn (09:25:08Z).
- Süre içinde: `GET /huni/keepchat` teklif `bitis: 2026-11-04T09:25:08Z`; gerçek ödeme
  oturumu (`sartOnayi: true`) bugün **0** (deneme), Stripe'ta `metadata {app, sart_onayi: "1"}`.
- Ekran (fitness paywall'ı + rozetler + onay + şart adresi): uyarı yok; "Teklifin
  bitmesine 00:02:05", düğme onaysız soluk, koşul, iki rozet, yasal satır.
- Süre dolduktan sonra (09:25:13): huni fiyatı teklifsiz `€9,99 / ay`; gerçek ödeme
  oturumu **999** (deneme yok). Satır DB'de duruyor (karar 5).
- Panel teklif formu ve Puck alanları tarayıcıda açılmadı (Ç111).

## Kontrol

`bin/kontrol` **YEŞİL**: 559 çekirdek testi / 4748 doğrulama; huni 76+, bileşen 122,
panel 131. İlk koşu: PHPStan 1 (`array_diff` karışık liste) — düzeltildi, K9 yeniden 🔴.

## Açık

Ç111.
