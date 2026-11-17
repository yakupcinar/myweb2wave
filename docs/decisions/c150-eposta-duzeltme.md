# Ç150 — Panelden abonelik e-postası düzeltme, kanıt kartın son 4 hanesi (2026-11-17)

Kullanıcı sorusu: ödeme formunda e-posta yanlış yazılırsa (ör. `gmial.com`) giriş bağlantısı da kod da o adrese
gider, kullanıcı aboneliğini bağlayamaz. "Panelden e-posta düzeltme ekleyelim; ödemeyi onun yaptığını nasıl
kanıtlayacak, alakasız biri de destek yazabilir." Kullanıcı kararı: **sistem kart son 4'ü zorlasın**.

## Ne yapıldı

- `POST /panel/api/abonelikler/{abonelik}/eposta {eposta, son4}` ([`AbonelikYonetimi::epostaDuzelt`](../../core/src/Billing/AbonelikYonetimi.php)):
  1. Yeni e-posta normalize (küçük harf, boşluksuz) ve geçerli; eskisiyle aynı değil; son 4 dört rakam.
  2. Eski e-posta uygulamada doğrulanmışsa (`baglantilar` + `kisiler`) **reddedilir**: yanlış yazılmamıştır,
     düzeltme aboneliği doğrulanmış sahibinden almak olur. Stripe'a gidilmez.
  3. Yanlış son 4 abonelik başına saatte 5 (`eposta_duzeltme` limiter); dolunca doğru hane de kabul edilmez.
  4. Stripe'tan aboneliğin kartı (`default_payment_method`, yoksa müşterinin varsayılanı) okunur; son 4
     `hash_equals` ile karşılaştırılır. Eşleşmezse hiçbir şey değişmez.
  5. Eşleşirse Stripe müşterisinin e-postası (makbuz, portal) ve `subscriptions.email` güncellenir.
- Panel › Aboneler: her satırda "E-postayı düzelt"; onay satırında yeni e-posta + "Son 4" (rakam dışı atılır),
  ikisi geçerli olana dek "Düzelt" kapalı. Panel son 4'ü **göstermez**: destek müşteriye söyleyemez.
- Sözleşme: yeni yol; tipler yeniden üretildi.

## Neden böyle

- **Kanıt son 4:** makbuz yanlış adrese gittiği için müşteri onu gösteremez; ödeyen kart son 4'ünü bilir, alakasız
  biri bilmez. Reddedilen: panelin son 4'ü gösterip desteğin karşılaştırması (destek ağzından kaçırabilir /
  kandırılabilir); doğrulamasız düzeltme (sorudaki riski kapatmaz).
- **Yeni adres hak vermez:** hak e-postayı uygulamada kod ya da giriş bağlantısıyla kanıtlayan hesaba (`Hak`).
  Düzeltilen adresin sahibi yine kanıtlar; ayrıca e-posta göndermeye gerek yok.
- **Kayıt olay deposunda değil, günlükte:** `olaylar` ziyarete bağlı ve giden webhook'larla dışarı akar; e-posta
  sızardı. Günlüğe abonelik kimliği ve sonuç (`info` / `warning`), e-posta değil. ⚠️ **Üretimde görünmüyor** (Ç151):
  monolog yok, yerleşik günlükçü bu seviyeleri basmıyor — kullanıcının `1111` denemesinden sonra günlükte 0 satır (ölçüldü).
- **Webhook düzeltmeyi ezmez:** e-postayı yalnız `checkout.session.completed` yazıyor; olaylar tekrar işlenmiyor.
- P7g'deki "işlemsiz kartta işlem satırı gizli" kuralı kalktı: artık her satırda en az bir işlem var.

## Kırma denemeleri — 9/9 kırmızı (P1 ilk koşuda tutmadı → test güçlendi)

| # | Bozulan | Sonuç |
|---|---|---|
| E1 | son 4 karşılaştırılmıyor | 2 düştü |
| E2 | bağlı e-posta kontrolü yok | 1 |
| E3 | eski e-posta normalize edilmeden (karışık harfli abonelik e-postası) | 1 |
| E4 | deneme sınırı denetlenmiyor | 1 |
| E5 | müşterinin varsayılan kartına düşmüyor | 1 |
| E6 | Stripe müşterisi güncellenmiyor | 1 |
| E7 | yeni e-posta normalize edilmiyor | 1 |
| P1 | düğme e-postasız açık | **tutmadı** → test "son 4 tamam, e-posta yok" adımını almadı; eklendi, kırmızı |
| P2 | rakam dışı atılmıyor | 1 |

E3 için test bilerek karışık harfli abonelik e-postası kullanıyor (kişi tablosu küçük harf kısıtlı).
Betik: `$TMPDIR/…/scratchpad/kir-eposta.py`.

## Ölçüm

Çekirdek `PanelAbonelikTest` 14 (5 yeni), panel `Kullanicilar.test` 15 (2 yeni; iki eski testin düğme listesi
yeni düğmeyle güncellendi). Tam kontrol ve canlı: aşağıda.
- **Tam kontrol:** ilk koşu biçim (`use` sırası), PHPStan (2 tip) ve belge bağlantısı (rehberdeki `#L` satırları
  kaydı, tuzak §58) ile kırmızı; düzeltildi, rehbere yeni işlem eklendi. İkinci koşu `TEST_TOKEN=duz`: **YEŞİL**,
  791 çekirdek testi.
- **Canlı (2026-11-17, kullanıcı dağıttı, sürüm `c54639010a15`):** kullanıcı panelden önce yanlış son 4 (reddedildi), sonra
  doğru son 4 denedi: "doğru çalışıyor". Sunucuda düzeltilen aboneliğin `updated_at` 11:08; Stripe müşterisinin
  e-postası veritabanındakiyle aynı (değerler basılmadan özetle karşılaştırıldı). Denetim izi günlükte yok → Ç151.
