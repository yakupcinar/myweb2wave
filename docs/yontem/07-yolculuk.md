# 7 · Yolculuk — blok blok

> Her satır: ne yapıldı · ne öğrenildi · kayıt. Ayrıntı kayıtta. Tarihlerin
> hepsi 2026-10-19 (ilk gün). Yeni blok = en alta bir satır.

| # | Blok | Öğrenilen | Kayıt · commit/PR |
|---|---|---|---|
| 1 | AI ortamı incelemesi, iki terminal değerlendirmesi | ortam ölçülmeden kurulmaz; iki kopya skill gizli maliyet | `ai-ortami-incelemesi.md` §1–4 |
| 2 | `ritual` tekrarı kapatıldı, KeepChat artıkları temizlendi | hook'a yazılan tek kesme işareti her Bash'i kırdı | §5 · `82fe9c4` |
| 3 | Graphify KeepChat'te ölçüldü, eklenmedi | vaat kendi iş yükünde ölçülür; maliyet sabit yükte | §7, C2 |
| 4 | GitHub deposu, ürün kapsamı (A1–A3) | — | `pre-setup` §A |
| 5 | web2wave dışarıdan incelendi | yığın çerez biçiminden okunur; lisanssız SDK'dan kod alınmaz | `web2wave-yigin-arastirmasi.md`, C4 |
| 6 | Yığın: B1 Next.js → B2 Astro + Symfony | "profesyonel" ölçütü para akışını ayırmayı gerektirdi; iyzico SDK'sı dili belirledi | `pre-setup` §B · `5dee4ff` |
| 7 | Ödeme sağlayıcısı + uluslararası alıcı araştırması | vergi yükü sağlayıcı seçimini belirliyor | `odeme-saglayici-arastirmasi.md` |
| 8 | İskelet: Docker, `/health`, `bin/kontrol` | test yapılandırması gerçek ortam değişkenine yenildi (§19) | `iskelet.md` · `3ca91f9` |
| 9 | CI | ilk gerçek koşu `composer install` eksiğini buldu; "koşmadı" ≠ "düştü" | `ci.md` · PR #1 |
| 10 | Stripe çekirdeği: oturum, imzalı webhook, tekrar koruması | eksik test kırma ile bulundu; sıra kirliliği önceki bloktan geldi | `stripe-odeme.md` · PR #2 |
| 11 | Huni paywall + sözleşme + tarayıcıda E1–E8 | gerçek olaylar ters sırada geldi; Checkout ilk ödemede `incomplete` üretmiyor | `huni-paywall.md` · PR #3 |
| 12 | Kontrol günlüğü: son 40 satır | ilk 40 satır asıl hatayı gizliyordu | `ci.md` · PR #4 |
| 13 | Uygulama boyutu (A4) | taşınan sayfada ölü bağlantı; kısıtın bekçisi yoktu | `uygulama-boyutu.md` · PR #5 |
| 14 | Rakip araştırması (2 tur) | rakipler past_due'da ikiye ayrılıyor; dönüşüm herkeste sunucudan | `rakip-arastirmasi.md` · PR #6, #7 |
| 15 | Kararlar A5 (erişim, bağlama, sorgu), A6 (analitik); App Store sorusu | IAP'siz yalnız web çoğu ülkede ret riski; tek hak merkezi | `pre-setup` A5, A6; Ç29 |
| 16 | Bu el kitabı | bilgi bölümlere ayrılır, kopyalanmaz | `docs/yontem/` |
| 17 | Kimlik + e-posta kodu + hak ucu | iki tutmayan kırma iki güvenlik testini buldu; tarif e-postayı sessizce atıyordu (§24); web ödemesi → bağlama → hak uçtan uca | `kimlik-hak.md` |
