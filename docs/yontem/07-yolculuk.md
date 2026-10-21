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
| 18 | Dağıtım dosyaları (test modu) | korunduğunu sandığın şeyi koruyan dosya başkası çıkabilir (K9); cron'dan koşan betik dağıtım içinden koşandan farklı ortam görür | `dagitim.md`, C6 |
| 19 | Reklam atfı: ziyaret → ödeme oturumu → abonelik | tip denetimi yeşilken yanlış alan adı geçiyordu (§25); kendi yazdığım kırma SQL'i sahte kırmızı verdi (§8) | `atif.md` |
| 20 | İç panel: reklam / organik, ödedi mi | çerçeve başlığımı sessizce eziyordu (önbellek); `$`'lı özet tırnaksız ortam dosyasında bozuluyor (§26); deseni kaldırmak hiçbir şeyi bozmadı → yorum yanlıştı | `panel.md` |
| 21 | Huniye anket + tanıtım (tek sayfa, adım/cevap panelde) | görsel hata yalnız tarayıcıda göründü (stil yuvaya uygulanmıyordu, §27); tanım hatası derlemede durur | `huni-anket.md` |
| 22 | Karar A7 + E1.1: huni tanımı veritabanında, değişmez sürümler, imzalı önizleme | JSONB anahtar sırası geçerli veriyi reddettiriyordu (§28); kendi kırma denememin kusuru (Y8) da kayda girer | `huni-yayin.md` |
| 23 | E1.2 panelde huni düzenleme (kaydet, yayınla, sürümler, geri yükle, önizleme) | ezilen global değişken flaşları üretimde sessizce silerdi (§29); tarayıcı önbelleği eski düzenleyiciyi çalıştırdı (§30) — ikisi de yalnız gerçek kullanımda göründü | `panel-huni.md` |
| 24 | Karar A8 + E2.1: tek adres, kurallar seçer; uygulama başına çok huni | aracın ağ kaydı 503 dedi, DB ve günlük 204 dedi — önce kimin cevap verdiğine bak (§11) | `cok-huni.md` |
| 25 | Karar A9 + Stripe → RevenueCat aktarımı (giden kutusu) | test beklentimi Stripe'ın gerçek olay sırası düzeltti; cron satırı ölçülmüş bir tuzağı tekrarlayacaktı | `revenuecat.md` |
| 26 | E2.2 huni başına tema (renk, yazı tipi, köşe; kontrast yayından önce) | okunurluk öneri değil kural; kontrast koddan değil bilinen WCAG değerlerinden ölçüldü | `tema.md` |
| 27 | E2.3 görseller (yeniden kodlama, içerik özetli ad, SVG yok) | kodda yazan 5 MB sınırına PHP'nin 2M'si yüzünden hiç ulaşılmıyordu (§31); test süreci belleği gerçek isteği ölçmez — iddia curl ile ölçüldü | `gorseller.md` |
| 28 | E2.4a sürükle-bırak (Puck): çekirdek değişmeden, çeviri katmanı + birim testi | kayıpsız çeviri testle; üretilen dosyayı biçimlendirmek kendi denetimimizi kırdı | `puck.md` |
| 29 | Karar A10: ödeme sonrası tek kullanımlık giriş bağlantısı (dış "Lazy Auth" istemi yerine) | istem önce rakiplerle ölçüldü; birbirinin yedeği iki filtre tek tek bozulunca yeşil kaldı — çift birlikte ölçülür | `giris-baglantisi.md` |
| 30 | H0: React geçişi öncesi taban ölçümü (boyut, içerik zinciri, 15 davranış) | kararın gerekçesi ("ilk boyama") ölçülünce React o sorunu çözmüyor, uzatıyor çıktı — çözüm erken istek | `huni-oynatici-h0.md` |
| 31 | H1: ortak huni bileşenleri + tek tema/CSS (Puck ve huni aynı dosyalar; PHP ile ortak örnek dosyası) | iki dilde aynı formül tek JSON'la ölçülür; kontrol adımının grep'i kendi yorumunu yakaladı — kırma denemesi kontrol adımına da uygulanır | `huni-bilesenleri-h1.md` |
| 32 | H2: huni oynatıcısı tek React adacığı; tanım isteği satır içi betikte, D1–D15 testle | "testler yeşil" iki kez yalan söyledi: effect yarışı olumsuz iddiayı yalancı yeşil yapıyordu (§33); bir "şart" satırı (dedupe) ölçülünce gereksiz çıktı | `huni-oynatici-h2.md` |
| 33 | H3: önizleme = huni — aynı tanım iki bağımsız yoldan aynı DOM, tarayıcıda stil farkı 0 | karşılaştırma testi, karşılaştırılanlardan biri diğerinden hesaplanırsa kendini ölçer; ilk kırmızı ürün değil test ortamı farkıydı (SSR önyükleme bağlantısı) | `huni-onizleme-h3.md` |
