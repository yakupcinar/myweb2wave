# Ödeme sağlayıcısı araştırması — 2026-10-19 (Ç10)

> Bağlam: Türkiye şirketi (A3), web'den uygulamaya abonelik satışı, şu an
> test. Karar değil; kararı belirleyecek soru en altta.

## Ölçülenler

| Olgu | Kaynak | Güven |
|---|---|---|
| **Paddle Türkiye satıcısını kabul ediyor**: "software businesses anywhere in the world with the exception of the unsupported countries"; listede Türkiye yok (28 ülke/bölge: İran, Rusya, Suriye…) | Paddle yardım sayfası, doğrudan okundu | yüksek (hesap onayı yine KYC'ye bağlı) |
| **Stripe Türkiye şirketine hesap açmıyor** | ikincil (Dodo, Paddle müşteri alıntısı) | orta — Stripe'ın resmi listesinden okunmadı |
| Resmi SDK: Stripe Go+PHP, Paddle Go+PHP, **iyzico yalnız PHP** | `gh repo list` | yüksek |
| iyzico: çok para birimi (TRY, USD, EUR…) destek talebiyle açılıyor; **yurt dışı kartla tekrarlayan USD aboneliği belgelenmiş bulunamadı**; Amex yalnız yerel para birimi (tek kullanıcı raporu) | ikincil | düşük |

## Web'den uygulamaya huni özellikleri (FunnelFox entegrasyon matrisi)

| | Abonelik | Tek seferlik upsell | 2. abonelik upsell | Yükseltme upsell | Apple/Google Pay | Cüzdan düğmesi |
|---|---|---|---|---|---|---|
| Stripe | ✅ | ✅ | ✅ | ✅ | ✅/✅ | ✅ |
| Paddle | ✅ | ✅ | ❌ | ✅ | ✅/✅ (yalnız Express Checkout) | ❌ |
| Solidgate | ✅ | ✅ | ✅ | ❌ | ✅/✅ | ✅ (tanıtım teklifi/promosyon kodu yok) |
| FastSpring | ✅ | ✅ | ✅ | ❌ | ✅/✅ | ❌ |

iyzico listede yok — web'den uygulamaya huni araçları onu entegre etmiyor.

## Yollar

| Yol | Vergi yükü | Ücret (ikincil, doğrulanmadı) | Huni özelliği | Türkiye şirketiyle |
|---|---|---|---|---|
| **Paddle** (merchant of record) | **Paddle'da** — dünya geneli KDV/satış vergisini o tahsil edip öder | ~%5 + $0,50 | iyi, 2. abonelik upsell yok | ✅ doğrudan |
| **Stripe** + yurt dışı şirket (ABD LLC / AB) | **bizde** (Stripe Tax yalnız hesaplar) | ~%2,9 + $0,30 (+Tax ~%0,5) | en iyi | ❌ yurt dışı şirket şart |
| **iyzico** | bizde | yerel tarife | belirsiz, huni araçlarında yok | ✅ doğrudan |

## Kararı belirleyecek soru

**Alıcılar nerede?** Dünya geneli → Paddle (Türkiye şirketiyle bugün
mümkün, vergiyi o taşır). Ölçek büyüyünce yurt dışı şirket + Stripe ücret
ve özellik farkıyla yeniden tartılır. Yalnız Türkiye → iyzico.

⚠️ A3 (Stripe test) ile çelişki: canlı Paddle olacaksa Stripe'a karşı
yazılan entegrasyon atılır. Paddle'ın ayrı, ücretsiz bir sandbox ortamı var
— test, canlıya gidecek sağlayıcıyla yapılmalı.
