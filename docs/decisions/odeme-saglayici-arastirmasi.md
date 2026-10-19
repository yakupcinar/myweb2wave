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

## A3 çelişkisinin çözümü (2026-10-19, kullanıcı)

Test **Stripe test moduyla** sürer; canlıda başka sağlayıcı çıkarsa
entegrasyonun yeniden yazılmasını kullanıcı kabul ediyor. Kullanıcı Stripe
hesabı açmayı daha önce denediğini ve olmadığını hatırlıyor — açılamazsa
"başka yöntem" denenecek (ölçülmedi).

## Alıcılar uluslararası — neyi etkiler (bilgi; hukuki görüş değil)

| Alan | Etki | Kimin yükü |
|---|---|---|
| **AB KDV** | AB dışı satıcı, AB'deki tüketiciye dijital hizmette **ilk satıştan** alıcının ülkesindeki KDV'yi tahsil eder (non-Union OSS kaydı) | PSP'de (Stripe/iyzico) biz; MoR'da (Paddle) sağlayıcı |
| **ABD satış vergisi** | eyalete göre; dijital ürün vergilenmesi ve "ekonomik bağ" eşikleri eyaletten eyalete farklı | aynı |
| **İngiltere, Norveç, İsviçre, Avustralya… KDV/GST** | dijital hizmette yabancı satıcıya kayıt yükümlülüğü | aynı |
| **Türkiye tarafı** | hizmet ihracatı KDV istisnası ve kazanç indirimi olabilir — **mali müşavire doğrulatılacak** | biz |
| **AB tüketici hakkı** | dijital içerikte 14 gün cayma hakkı; anında erişim için açık onay + feragat metni huniye girer | biz (huni metni) |
| **Abonelik yenileme yasaları** | ABD'de eyalet otomatik yenileme yasaları (ör. Kaliforniya): açık onay, kolay iptal | biz (huni + iptal akışı) |
| **GDPR / çerez onayı** | AB ziyaretçisinde izleme pikselleri onaysız çalışamaz → huni analitiğini etkiler | biz (huni) |
| **SCA / 3D Secure** | AB kartlarında zorunlu doğrulama; ödeme bileşeni yönetir ama huni akışı bunu beklemeli | sağlayıcı + huni |
| **Para birimi, dil** | yerel para biriminde fiyat, çok dilli huni | biz (huni tanımı) |

**Mimariye etkisi:** B2 değişmez. Huni tanımına dil + para birimi +
yasal metin alanları, çekirdeğe vergi dahil/hariç fiyat ve onay kaydı girer.

**Sağlayıcı seçimine etkisi:** uluslararası tüketiciye satışta vergi
kayıtlarını (AB OSS, İngiltere, ABD eyaletleri…) kendimiz yapmak ağır bir
yük; Paddle gibi MoR bunu üstlenir. Bu, canlıda MoR lehine en güçlü argüman.
