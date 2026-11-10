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
| 34 | Ada modüllerine derlemede modulepreload | ölçülen açık uç (ikinci JS turu) bir sonraki blokta kapandı; ölçüt aynı araçla aynı tablo | `ada-onyukleme.md` |
| 35 | b1: yayınla/önizleme/sürümler Puck düzenleyicisinde (rakip ölçümünden sonra) | koruma denemesi tutmadı çünkü koruma başka katmandaydı (route değil access_control) — denemeyi gerçek korumaya uygula | `puck-yayin-b1.md` |
| 36 | H4: adım geçişleri yalnız CSS, ilk adımda yok | ilk ölçüm kendi kararımızı bozdu (ilk adım da 0'dan başlıyordu → ilk boyama gecikirdi); gizli sekme animasyon ölçümünü yalan söyletti (§34) | `huni-gecisler-h4.md` |
| 37 | H5: dallanma — seçenek/tanıtım → ileri hedef (A11), skor yok | kullanıcıya rakip modelleriyle soruldu; bir 'düzeltme' kendi ölçüm hatama dayanıyordu (sentetik olay Puck'a ulaşmamıştı) — geri alındı | `huni-dallanma-h5.md` |
| 38 | H6a: reklam ölçümü onay şeridi; tıklama kimlikleri yalnız izinle (A12) | yeşil süite rağmen tarayıcıda iki kusur: göçsüz geliştirme DB'si (500) ve CORS'a eklenmeyen uç (durum 0, §36) — ikisini de huni sessizce yuttu | `huni-izin-h6a.md` |
| 39 | V6: niş şablonları — yeni huni boş değil iyi bir örnekten başlar | yayın denetimi uydurma yorumu geçirdi (K6c yeşil): içerik kuralı ancak dosyayı okuyan testte ölçülür; uyarı sayısı temaya bağlı (aynı içerik Gece'de 0, Okyanus'ta uyarılı) | `v6-nis-sablonlari.md` |
| 40 | M1b: teklifler — deneme, ücretli giriş, ilk dönem indirimi; ödeme teklifi katalogdan kurar | kullanıcıya "webhook'a bağlı schedule" yerine webhook'suz yol önerildi ve seçildi; Stripe'ın %50 yuvarlaması (4,99) gösterilen tutarı hesaplamama kararını ölçümle doğruladı; iki kırma denemesi zsh yüzünden koşulmadı (§16) | `m1b-teklifler.md` |
| 41 | M1c: ülkeye göre sabit tutarlı para birimi; paywall ile ödeme aynı tutar | kullanıcı "rakiplere bak, ona göre seçelim" dedi → üç rakibin belgesi okunup karar onunla verildi; bir kırma denemesi tutmadı (test silinen alanı hiç denemiyordu); yan bulgu: dağıtım betiği 2 haftadır sözdizimi hatalıydı (§49) | `m1c-cok-para-birimi.md` |
| 42 | M2a: paywall plan seçici + seçili plana göre ödeme koşulu | büyük iş önce kullanıcıyla bölündü (sayaç dürüstlüğü, paywall yapısı, sıra); otomatik metinde ölçemediğimiz sözü (iptal) yazmadık; ekran ölçümü bir satır kırılmasını yakaladı | `m2a-plan-secici.md` |
| 43 | M2b: teklif bitişi + sayaç, güven rozetleri, şart onayı | aciliyet dürüst kaldı: sayaç teklifin gerçek bitişine bağlı ve süre dolunca hem paywall hem ödeme gerçek Stripe'ta ölçüldü (0 → 999); tek bekçi deseni (`teklifler()`) | `m2b-sayac-guvence-onay.md` |
| 44 | M2c: cevaba göre paywall — birden çok paywall adımı | önce "tek varsayım nerede" ölçüldü: yalnız bir doğrulama satırı + çubuk; oynatıcı ve düzenleyici zaten doğruydu → küçük blok; gerçek tarayıcıda iki cevapla iki paywall yürüyüşü | `m2c-cok-paywall.md` |
| 45 | M2d: gömülü ödeme; cüzdanlar sunucuya | test kartıyla uçtan uca gerçek ödeme bir önceki bloğun (M1c) yanlış varsayımını yakaladı: Stripe zorlanmayan para birimini kendisi seçiyor (§50) — mock hiç göstermezdi | `m2d-gomulu-odeme.md` |
| 46 | M3a: downsell penceresi | bir kırma iki kez tutmadı: önce test o durumu denemiyordu, sonra sahte hata happy-dom'da hiç tetiklenmiyordu — "tutmadıysa testi suçla" iki katmanlı; gerçek veride kendi yönergemi çiğneyen metni (elle fiyat) yakaladım | `m3a-downsell.md` |
| 47 | M3b: ödeme sonrası ekran düzenlenebilir | kritik işlev (giriş bağlantısı) yöneticinin silebileceği bir blok yapılmadı, sabit kaldı; tek uygulamalı yapılandırmada uçtan ölçülemeyen kural servis düzeyinde ölçüldü | `m3b-odeme-sonrasi.md` |
| 48 | M3c: ek ürün + tek tık upsell | gerçek parayla (test modu) uçtan uca: abonelik → kayıtlı kartla ikinci ödeme → ikinci istek yeni çekim yapmadı; bir kırma, adın başka bir CORS desenine de uyması yüzünden tutmadı | `m3c-ek-urun-upsell.md` |
| 49 | M4a: deneyler (atama + panel) | ilk commit CI kırmızıyla gitmişti — sonraki oturum kapıyı yeniden kurdu: eksik testler, 24 kırma, gerçek istekte testlerin görmediği 404 (doğrudan adres) | `m4a-deneyler.md` |
| 50 | M4b: deney sonuçları + kazanma olasılığı | olasılık Python'un bağımsız hesabıyla ölçüldü; gerçek denemeli Stripe oturumu kendi varsayımımı çürüttü (deneme "paid", tutar 0); 4 kırma ilk koşuda tutmadı, hepsi testi güçlendirdi | `m4b-deney-sonuclari.md` |
| 51 | Ç118: denemede rapor + reklam olayı | düzeltme tek yere değil ailenin tamamına: rapor, sayım, satır, panel dönüşümü, sunucu olayı ve tarayıcı pikseli aynı tanıma çekildi; tekilleştirme için iki taraf aynı adı göndermeli | `c118-deneme-rapor-reklam.md` |
| 52 | M5a: yalnız ekleme olay deposu | taşıma birebir ölçüldü (göçten önce ve sonra parmak izi); akış imlecinin bitmemiş işlemi atlaması testte görünmez — ikinci bağlantıyla yarım işlem kurup ölçüldü | `m5a-olay-deposu.md` |
| 53 | M5b: Stripe faturaları → ziyaret | alan yeri belgeden değil gerçek faturadan okundu (yeni API'de `subscription` üst düzeyde yok); tutmayan bir kırma, gerçek biçimde ulaşılamayan bir savunmayı gösterdi — kod kaldırıldı | `m5b-faturalar.md` |
| 54 | M5c: kaynak · cevap · kohort raporları | tutmayan kırmalar testi güçlendirdi ve güçlenen test gerçek bir hatayı (NULL eşleşmesi, organik gelir boş) buldu; düzeltme sorguyu sadeleştirince savunma da gereksizleşti — ölçülüp kaldırıldı | `m5c-raporlar.md` |
| 55 | M5d: Meta harcaması → ROAS | ezberden bir dil davranışıyla ("round ön yuvarlar") bir savunmayı kaldırdım; servis düzeyi test tersini ölçtü, savunma geri geldi — "gereksiz" demeden önce testin o yoldan geçtiği doğrulanır | `m5d-meta-harcama.md` |
| 56 | M6a: panelden iptal ve iade | alanların yeri yine gerçek Stripe nesnesinden ölçüldü (dönem sonu kalemde, fatura ↔ ödeme ayrı nesnede); iptal / geri al / iade gerçek test modunda uçtan uca, M4b'den açık kalan deneme aboneliği de böylece kapandı | `m6a-iptal-iade.md` |
| 57 | M6b1: müşteri portalı — ayar kodda + uygulama ucu | portal ayarı kodda tutulup tek komutla her ortama aynı uygulanıyor (gerçek hesapta kur → güncelle); ölçüm betiğinde adres biçimini varsayıp maskelemeyi kaçırdım — test oturum adresi çıktıya düştü, kayda geçti | `m6b1-portal.md` |
| 58 | M6b2: web yolundan müşteri portalı | tek kullanımlık bağlantı GET'te değil POST'ta harcanır (e-posta tarayıcıları); CORS testi yalnız kökene bakınca yalancı yeşildi — yol başka bir desene de uyuyordu | `m6b2-portal-web.md` |
| 59 | M7a: giden webhook motoru | motoru yazarken kendi M5a çözümümdeki (tuzak §53) bir açığı akıl yürütmeyle buldum, önce onu düşüren testi yazdım, sonra düzelttim — kayıtta eski "doğrusu" düzeltmeyle duruyor; imza beklentisi spesifikasyonun örneğinden | `m7a-giden-webhook.md` |
| 60 | M7b: webhook paneli | sayfa testi gerçek bir çökmeyi buldu (durum güncelleyicisinde olay hedefini okumak) — tip denetimi ve derleme yeşildi | `m7b-webhook-paneli.md` |
| 61 | M7c1: MCP rapor araçları + olay akışı API'si | çok uygulamalı süzgeç her sorguya tek tek eklendi ve her biri ayrı kırmayla ölçüldü; kohortun gelir kolu ölçülmüyordu (pencere dolmamış veri) — tutmayan kırma gösterdi | `m7c1-okuma-araclari.md` |
| 62 | M7c2: MCP yazma araçları | yetki yapıyla: izinsiz anahtar aracı hiç görmez (yalnız istemci onayına güvenilmedi); sahte Stripe yanıtı sabit olduğunda yazılan durum, gönderilen değeri ölçmüyordu — tutmayan kırma gösterdi | `m7c2-yazma-araclari.md` |
| 63 | CLAUDE.md kısaltma | her promptta yüklenen dosya 20 KB → 11 KB: tuzak tablosu anahtara indi, tam metin `tuzaklar.md`de; 14 tuzağın metni yalnız tabloda yaşıyormuş, silmeden önce taşındı; docs seçici okunur (grep + sed) | `CLAUDE.md` §4 |
| 64 | M8a1: Postmark + bastırma | köprü pasif alıcıda hata atmıyor (406 → olay, `send()` başarılı) — kaynağı okumadan "gitti" sanılırdı; kontrol grubu yanlış anahtarla yanıtın Postmark'tan geldiği gösterildi | `m8a1-postmark.md` |
| 65 | Uygulama rehberi | eski iki mimari notu koddan değil hafızadan yazılmıştı (Astro, mutlak yollar, ölçülmemiş yüzde); rehber koddan çıkarıldı (menü, rotalar, komutlar, dev DB tabloları) ve her iddia dosyayla karşılaştırıldı; `adresler.md`de Deneyler ve Webhook sayfaları eksikti | `uygulama-rehberi.md` |
| 66 | M8a2: aktivasyon dizisi | iki kırma yaması (A3 bu blokta, K2 M8a1de) SQL söz dizimini bozup tüm süiti hatayla düşürdü — sahte kırmızı; yama yalnız koşulu etkisiz kılacak biçimde (AND false) yeniden yazıldı | `m8a2-aktivasyon.md` |
