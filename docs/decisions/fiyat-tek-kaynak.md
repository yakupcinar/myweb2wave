# L10 — Paywall fiyatı tek kaynaktan: Stripe (Ç19/Ç21)

2026-10-26 · karar `pre-setup.md` A13 (Adaptive Pricing AÇIK kalır).

## Ölçüm (önce)
Paywall fiyatı yalnız kullanıcının yazdığı metinde ("Aylık plan · 9,99 € /
ay"); Stripe'ta fiyat değişirse paywall sessizce eski fiyatı söylerdi (Ç21,
tuzak §12 akrabası). Gerçek Stripe test fiyatı (çekirdek, 2026-10-26):
**9,99 EUR / ay** — bugün metinle aynı.

## Karar
- Çekirdek `Billing\Fiyatlar`: uygulamanın her planı için Stripe fiyat
  nesnesi → `{tutar (ana birim), paraBirimi, aralik}`; `fiyat.onbellegi`
  havuzunda 1 saat. Stripe'a ulaşılamazsa o plan atlanır, **hata önbelleğe
  yazılmaz**, huni yine açılır.
- Yalnız tek aralıklı yinelenen fiyat (`interval_count = 1`): "3 ayda bir"
  "/ ay" diye yazılmaz; tek seferlik fiyat da yok.
- Ana birime çeviri `Billing\ParaBirimi` — `DonusumAktarimi`'ndeki ondalıksız
  para birimi listesi oraya taşındı (kural 7: bir olgu iki yerde değil).
- `GET /huni/{app}` ve `/taslak` (önizleme) yanıtında isteğe bağlı
  `fiyatlar` (sözleşme `PlanFiyati`). Oynatıcı `fiyatMetni` (Intl tr-TR):
  `€9,99 / ay`; paywall'da metnin altında `p.fiyat`.
- **Reddedilen:** fiyatı yayında tanıma gömmek (yayından sonra Stripe'ta
  değişen fiyat yine eskirdi) · tarayıcıdan Stripe'a gitmek (gizli anahtar
  yok; yayımlanabilir anahtarla fiyat okunamaz) · Adaptive Pricing'e göre
  yerel para göstermek (ziyaretçinin ülkesi gerekir; kullanıcı açık
  bırakmayı seçti, Ç19 fark açık).
- Puck önizlemesinde fiyat yok (panel yanıtı fiyat taşımıyor) — önizleme =
  huni eşliği adım bileşenleri üzerinden, fiyat prop'u verilmez.

## Test
`FiyatTest` (yeni, 3): yanıtta `{monthly: {tutar: 20, paraBirimi: USD,
aralik: month}}` (stripe-mock 2000 → elle 20), istek doğru fiyat kimliğine,
ikinci okuma Stripe'a gitmez; Stripe kapalıyken 200 ve fiyatsız, açılınca
fiyat gelir (hata önbellekte değil); tek aralık / JPY / tek seferlik.
Önbellek havuzu her testte temizlenir. `HuniTest` iki tam-eşitlik testinde
`fiyatlar` dışarıda (konuları sürüm/önizleme). Huni 51 (+3 fiyat metni,
+1 paywall satırı), bileşen 30.

## Kırma
| # | Bozulan | Sonuç |
|---|---|---|
| K1 | önbellek süresi 0 | 🔴 önbellek testi |
| K2 | Stripe hatası `null` olarak önbelleğe | 🔴 "açılınca fiyat gelir" |
| K3 | aralık sayısı denetimi yok | 🔴 tek aralık testi |
| K4 | JPY de 100'e bölünür | 🔴 tek aralık testi (JPY) |
| K5 | oynatıcı fiyatı geçmez | 🔴 paywall fiyat testi |
| K6 | bileşen fiyat satırı yok | 🔴 bileşen + sınıf testi |
| K7 | aralık çevirisi yanlış | 🔴 fiyat metni + paywall |

## Gerçek istek + tarayıcı
Yerel çekirdek (gerçek Stripe test anahtarı) `GET /huni/keepchat`:
`fiyatlar.monthly = 9.99 EUR month`, ilk istek 1,42 sn, ikincisi önbellekten
0,05 sn. Huni paywall'ında `p.fiyat` = "€9,99 / ay".

**Kullanıcıya:** yayındaki KeepChat paywall metni fiyatı elle de yazıyor
("Aylık plan · 9,99 € / ay") → ekranda fiyat iki kez. Metni "Aylık plan"
yap (panel → Puck → Ödeme → Fiyat satırı). İçeriğe dokunulmadı.

## Kontrol
`bin/kontrol` **YEŞİL**, EXIT=0, 226 çekirdek testi / 1.422 doğrulama.
