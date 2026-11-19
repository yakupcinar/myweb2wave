# Ç149 — Paywall metninde `{fiyat}` yer tutucusu (2026-11-19)

Kullanıcı: "Ç149 sence nasıl olmalıysa onu yap". Sorun (canlı huni, 2026-11-17): paywall metni huni tanımında elle
"Aylık plan · 9,99 € / ay" yazılmıştı; plan Türkiye'den ₺349,99 açılıyordu. Fiyat ya da ülkeye göre para birimi
değişince metin yanlış kalıyor.

## Karar

- Paywall metninde **`{fiyat}`** seçili planın biçimlenmiş fiyatıyla değişir — paywall'ın fiyat satırında ve plan
  kartlarında görünen, ülkeye göre seçilmiş para birimindeki metnin aynısı. Plan seçicide kullanıcı plan değiştirince
  metin de değişir. Tek yer: [`fiyatli`](../../huni-bilesenleri/bilesenler.tsx) `Paywall` bileşeninin içinde; huni ve
  panel önizlemesi aynı kodu kullanır.
- Fiyat okunamadıysa (Stripe hatası) ya da panel önizlemesinde (fiyat yok): yer tutucu ve artan ayraç düşer —
  "Aylık plan · {fiyat}" → "Aylık plan", "A · {fiyat} · B" → "A · B". Ham "{fiyat}" ziyaretçiye görünmez.
- Yer tutucusuz metin olduğu gibi kalır (var olan hunilerde davranış değişmez).
- Panel alanının etiketi "Fiyat satırı — {fiyat} seçili planın fiyatıyla değişir"; sözleşmede `PaywallAdimi.metin`
  açıklaması — AI asistanı araç şemasından (`tanim-semasi.json`) öğrenir, fiyatı elle yazmaz.
- **Reddedilen:** (a) metinden fiyatı tamamen yasaklamak — "Bugün {fiyat} ile başla" gibi satış metni yazılamazdı;
  (b) yalnız panelden elle düzeltmek — her fiyat/para birimi değişikliğinde yine kayar; (c) sunucuda yayın anında
  değiştirmek — fiyat ziyaretçinin ülkesine göre istemcide seçiliyor, yayında tek fiyat yok.

## Testler ve kırma

[`bilesenler.test.tsx`](../../huni-bilesenleri/bilesenler.test.tsx) "Paywall {fiyat}" — tek plan (₺349,99), seçicide
seçili plan (iki plan, iki çizim), fiyat yokken temizlik (dört biçim), yer tutucusuz metin ve çoklu yer tutucu.
**Kırma 4/4 kırmızı:** K1 metin ham çizilir · K2 seçicide hep tek fiyat · K3 temizlik yok · K4 yalnız ilk yer tutucu.

## Açık

- Canlıdaki `genel` hunisinin metni "Aylık plan" (2026-11-17); `{fiyat}` istenirse panelden yazılıp yayınlanır.
