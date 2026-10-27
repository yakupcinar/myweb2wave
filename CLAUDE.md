# CLAUDE.md — proje anayasası

<!-- TODO: proje adı, tek cümlelik tanım -->
**Proje:** _henüz adı yok_ — _ne yaptığını tek cümleyle yaz_.
**Yığın:** Astro (statik huni, CDN) · Symfony (çekirdek API) · Postgres ·
Stripe (test) — `docs/pre-setup.md` §B2, gerekçesi ve reddedilenlerle.

> **Bu dosya bir YÖNLENDİRİCİ (router).** İçinde yalnızca **her oturumda
> geçerli olan değişmezler** durur: yığın, komutlar, kurallar, tuzak
> indeksi. Ayrıntı — ölçümler, kararların gerekçeleri — § Referanslar'daki
> dosyalarda.
>
> ⚠️ **SATIR BÜTÇESİ: 300.** CLAUDE.md her oturumda VE her alt ajanda
> otomatik yükleniyor; büyümesi her prompta bedel yazar. Ölçüm:
> `wc -l CLAUDE.md`.
>
> Bu çalışma biçimi KeepChat projesinde (2026-09 → 2026-10) ~40 blokta
> oturdu; aşağıdaki kuralların her biri orada bir ölçümden ya da bir
> kayıptan doğdu.

---

## 1. Yığın ve ortam

<!-- TODO: yığın seçilince doldur -->
- Dil / çatı: huni = TypeScript · Astro (+ React adacıkları); çekirdek =
  PHP · Symfony (B2) — iskelet henüz yok
- Veri servisleri: Postgres 18.6 Docker'da, host portu **5435**; çekirdek
  FrankenPHP konteynerinde **8081**. Testler gerçek Stripe'a değil **stripe-mock**'a
  gider (`.env.test`); gerçek anahtar yalnız `core/.env.local` (git dışı). Makinede PHP yok — her şey `docker compose`.
- Test veritabanı / izolasyon: `app_test` + `TEST_TOKEN` son eki (paralel
  oturumda farklı `TEST_TOKEN` ver — kural 15).
- Üretim / dağıtım: _—_ — ⚠️ "push = üretim güncellendi" **varsayılmaz**;
  ölçüt çalışan sistemden okunan sürümdür (ör. `/health` → `version`).

---

## 2. Değişmez kurallar

1. **Ölçmeden iddia etme.** "Çalışıyor / geçti / muhtemelen" yok. Ölçen
   komutu koştur, çıktısını göster. Koşmadıysan **"koşmadım" de**.
2. **Bir iş, kırma denemeleri KIRMIZI olunca biter** — "testler yeşil"
   olunca değil. Her kararı tek tek boz, testin düştüğünü gör, geri al
   (`/kirma`). Deneme tutmazsa önce **testi** suçla.
3. **`git checkout <dosya>` YASAK** (`.claude/hooks/git-checkout-engel.sh`
   engelliyor) — commit edilmemiş işi sessizce siler. Geri alma için
   önce yedek: `cp <dosya> /tmp/x.bak`.
4. **Bilgi depoda durur, sohbette değil — ve bu ÖLÇÜLÜR.** Kapanış
   mesajındaki her *"fark ettim / karar vermedim / istersen geri alırım"*
   cümlesi **aynı turda** depoya yazılır (§6 tablosu); kapanıştan önce
   `grep` ile karşılığını göster (`/belge`).
5. **Belge ve yorumlar Türkçe; kodda ad KOMŞU koda uyar.** Var olan
   dosyada komşunun dili ve üslubu izlenir; toplu yeniden adlandırma
   yapılmaz.
6. **Cerrahi değişiklik + doğrulanabilir hedef.** (a) İstenmeyen
   soyutlama, "esneklik", imkânsız senaryo için hata yolu YAZILMAZ.
   (b) Komşu kodun biçimi, adlandırması, yorum üslubu olduğu gibi kalır —
   değişen her satır isteğe izlenebilmeli. (c) İş, geçen bir testle değil
   **ölçtüğü kanıtlanmış** bir testle biter.
7. **Bir olgu iki yerde yaşıyorsa her iki yön de güncellenir.** (Plan
   satırı + blok tanımı, açık uç tablosu + tetiklediği blok, iki
   istemcide aynı kural…) ⚠️ Bu tutarlılıktır; **üslup** yine dokunulmaz.
8. **Kontrol komutunun çıkış kodu BORUSUZ alınır.** `komut | tail` →
   `$?` `tail`inkidir, kırmızı süit yeşil görünür. Çıktıyı dosyaya yaz,
   kodu ayrı al: `komut > /tmp/log 2>&1; echo EXIT=$?`.
9. **Yerelde ağır yük yok; döngüyle süit koşma.** Uzun/yarış testleri tek
   koşum serbest; kabuk `for` döngüsüyle tekrar CI'ın ya da izole bir
   ortamın işi. Kayda **ortam + süre + çıkış kodu** yazılır.
10. **Commit edilmemiş değişiklik üretimde YOKTUR**; commit edilmiş olması
    da yetmeyebilir — dağıtım nasıl tetikleniyorsa onu ölç.
11. **Commit ve push:** iş kapandığında kontrol yeşil + kırma denemeleri
    kırmızıysa commit → push, **sormadan** (kullanıcı tercihi). Sormayan
    bir akışta tek kapı ölçümdür: kapı açık değilken commit edilmez.
    ⚠️ Commit mesajında ters tırnak kullanma (§4/§9) — `git commit -F -`
    + tırnaksız heredoc.
12. **Referans deposundan KOD alınmaz — lisans ÖLÇÜLÜR.** AGPL/GPL bir
    depodan tek satır kopyalamak projenin tamamını o lisansa çeker.
    Alınan şey yalnız **davranış**, kendi cümlemizle yazılır. Lisans
    referans başına ölçülür, "açık kaynak" diye tek torbaya konmaz.
13. **Alt ajana CLAUDE.md OTURUM BAŞINDAKİ hâliyle gider; `PLAN.md` ve
    `docs/**` HİÇ gitmez.** Ajanın bilmesi gereken kuralı promptuna elle
    yaz; kalıcıysa `.claude/agents/<ad>.md`'ye ya da skill'e koy.
14. **Soğuk açılış pahalı — oturumu sürdür.** `claude --continue` /
    `--resume`.
15. **Paralel oturum = oturum başına test veritabanı.** Worktree
    dosyaları ayırır, **DB'yi ayırmaz**; aynı test DB'sinde eşzamanlı iki
    süit birbirini düşürür ve belirti şema kusuru gibi görünür.

---

## 3. Komutlar

<!-- TODO: yığın seçilince doldur; `.claude/skills/kontrol/SKILL.md` ile aynı -->

| İş | Komut |
|---|---|
| **Tam doğrulama** | `bin/kontrol > /tmp/k.log 2>&1; echo EXIT=$?` · `/kontrol` · `sinayici` |
| Biçim | `docker compose run --rm -T core vendor/bin/php-cs-fixer fix` · `… funnel npm run format` |
| Statik analiz | `… core vendor/bin/phpstan analyse --memory-limit=1G` (max; bayraksız işçi 128M'de ölür) · `… funnel npm run check` |
| Test | `docker compose run --rm -T core vendor/bin/phpunit` · `… funnel npm test` (huni oynatıcısı) |
| Dar TDD turu | `… core vendor/bin/phpunit --filter <TestAdı>` |
| Çalıştır | `docker compose up -d --wait` → `curl localhost:8081/health` |
| Sözleşme → huni + panel tipleri + AI araç şeması | `docker compose run --rm contract npm run uret` + `… core bin/console app:huni-semasi` (`contract/openapi.yaml` değişince) |
| AI araçları (A0/A1) | `… core bin/console app:huni-arac keepchat --liste` · `… app:huni-arac keepchat taslak_kaydet < istek.json` |
| Panel (React, B5; Puck dahil) | `docker compose run --rm panel npm run build` · `… panel npm test` |

---

## 4. Tuzak indeksi — hata vermeden YANLIŞ CEVAP üretenler

> Her satır **iddiayı** taşır, yalnız başlığı değil. Tam metin ve yenileri:
> **[docs/tuzaklar.md](docs/tuzaklar.md)**. Yeni tuzak bulunca ikisine
> birden yazılır (kural 7).

| § | Tuzak — tek cümlede |
|---|---|
| **§1** | Bir kontrolü `\| tail`'a borulamak **çıkış kodunu yok eder**. |
| **§2** | `grep -i` Türkçe `ı`/`İ` ile **yanlış "bulunamadı"** der. Harf sınıfı kullan ya da `-i`'siz ara. |
| **§3** | Bir iddiayı **ölçtüğü kodun kendisiyle** hesaplama — beklenti de bozulur, test yeşil kalır. Bekleneni bağımsız hesapla. |
| **§4** | Uygulamadaki "bu ad alınmış mı" kontrolü, arkasındaki **DB kısıtını görünmez kılar**; kısıtı doğrudan ölçen ikinci test yaz. |
| **§5** | Biçimlendirici "liste" kipinde biçimsiz dosyada da **çıkış kodu 0** dönebilir (`gofmt -l`); ölçüt çıktının boş olması. |
| **§6** | "Hata döndü mü" yetmez — **hangi hata**: zaman aşımı da hatadır. Türüyle/koduyla karşılaştır. |
| **§7** | Bir yapılandırmayı **bildirmek, uygulandığı anlamına gelmez** (göç dosyası, CI yaml, deploy yaml). Etkisini çalışan sistemden oku. |
| **§8** | Uygulanmamış bir **kırma denemesi** testi suçsuz yere "ölçmüyor" ilan ettirir: yama tutmadıysa sonuç "yeşil" değil **KOŞULMADI**'dır. Derleme hatasıyla kırmızı olan deneme de sahtedir. Atlanan test (`skip`) BAŞARI görünür — `passed` mı `skipped` mi oku. |
| **§9** | **Çift tırnak içindeki ters tırnak kabukta KOMUT olarak çalışır** — `echo "… \`make test\` …"` gerçekten süiti başlatır, commit mesajı metni yer. |
| **§10** | *"Bu satır ŞART"* yorumu **ölçülmediyse büyük ihtimalle yanlıştır.** Savunmayı tek başına kaldır ve koştur; yorumu ölçümle yaz. |
| **§11** | Bir **aracın** yanıtı, ölçtüğün şeyin yanıtı sanılır (vekil ping'e kendisi cevap verir). Yanıtın kimden geldiğini işaretle (başlık, iz kimliği). |
| **§12** | **Yedek zincir kusuru maskeler**: alanın dolu olması asıl yolun çalıştığını kanıtlamaz. Bir sütunun DOLU olması da doğru olduğunu göstermez — *içinde ne var* diye sor. |
| **§13** | Yeşil bir süit **ölçülmemiş bir sıra/zaman varsayımı** taşıyabilir; yeni iş onu bozmaz, açığa çıkarır. Kırmızının üç şıkkı: yarış · mantık · **zaman aşımı**. |
| **§14** | Ölçümden önce **ortamı say**: dolu disk, eşzamanlı ağır koşum, iki test aynı simülatörde/DB'de — hepsi kodu suçlayan sahte kırmızı üretir. `df -h` ilk komut. |
| **§15** | `kill $!` alt kabuğu öldürür, içindeki komutu değil (öksüz süreç sonraki koşuyu bozar) — alt kabukta `exec`. `pkill -f <desen>` kendi kabuğunu da öldürür. |
| **§16** | macOS'ta `timeout` yok; `sh` macOS'ta bash, Ubuntu'da dash; BSD `sed` `\|`'yi tanımaz, düzenli ifadeli `grep` sabit metni **bulamayabilir** (`-F` kullan) — hiçbiri hata vermez. |
| **§17** | Log'da "yok" sonucu çoğu zaman **soru** hatasıdır (yanlış etiket, varsayılan zaman penceresi, yanlış log biçimi). Önce log'a yazıldığı bilinen bir kontrol grubunu ara. |
| **§18** | SwiftUI (ve benzeri bildirimsel UI): değiştirici **boş bir kapsayıcıya** (`Group { if … }`) bağlanırsa koşmaz — `.task`/`onAppear` hiç çalışmaz, hata yoktur. Gerçek kapsayıcı (`ZStack`) kullan. |
| **§19** | PHPUnit `<server>` gerçek ortam değişkenine **yenilir** (Symfony önce `$_ENV`) — `<env force>` kullan. |
| **§20** | Renkli çıktıda `grep '^OK'` satırı **sessizce kaybeder**; önce ANSI kodunu ayıkla. |
| **§21** | CI sonucunu SHA ile ararken push **olmadıysa eski koşu** gelir; önce "uygulandı mı" + "SHA yeni mi". |
| **§22** | `docker compose run -d` **0 döner**, içindeki süreç çoktan ölmüş olabilir — `docker ps` + "hazır" satırını bekle. |
| **§23** | `curl -s` + `[...]`'lı adres **sessizce boş** döner (glob) — `curl -g`. |
| **§24** | `MAILER_DSN=null://null` e-postayı **sessizce atar**, uç yine 202 — geliştirmede Mailpit. |
| **§25** | TypeScript **nesne yaymada (`...`) fazla alanı denetlemez** — yanlış alan adı tip denetiminden geçer; gövdeyi üretilen tipten kur. |
| **§26** | Şifre özetindeki **`$` tırnaksız ortam dosyasında sessizce bozulur** (dotenv ve compose) — giriş yalnız "yanlış şifre" der; tek tırnak. |
| **§27** | Astro bileşen **stili yuvadan (slot) gelen öğeye uygulanmaz** — derleme yeşil, düğme stilsiz; `is:global` ya da stili yuvayı dolduran dosyaya. |
| **§28** | Postgres **JSONB nesne anahtarlarının sırasını değiştirir** — sıraya bakan karşılaştırma geri okunan geçerli veriyi reddeder; anahtarı küme olarak karşılaştır. |
| **§29** | Twig'e **`app` adlı değişken geçmek global `app`'i ezer** — üretimde flaşlar sessizce kaybolur (testte 500); başka ad. |
| **§30** | **Cache-Control'süz statik dosya** tarayıcıda eski hâliyle çalışır (dağıtımdan sonra da) — adrese içerik özeti. |
| **§31** | PHP **`upload_max_filesize` (2M)** isteği uygulamadan önce keser — kodda yazan büyük sınıra hiç ulaşılmaz; PHP sınırını ölç ve ayarla. |
| **§32** | Vitest CSS içe aktarmasını (**`?raw` dahil**) **boş dizgeye** çevirir — CSS'te "yok" diyen test hep yeşil; `test.css.include`. |
| **§33** | Testing Library'de **metnin görünmesi `useEffect`'in koştuğunu garanti etmez** — effect'e bağlı iddia aralıklı kırmızı, olumsuz iddia yalancı yeşil; `waitFor`. |
| **§34** | **Gizli sekmede animasyon zaman çizelgesi durur** — opaklık 0'da "takılı" görünür, FCP/LCP yok; önce `visibilityState` + `document.timeline.currentTime` oku. |
| **§35** | **Betikle doldurulan alanın DOM değeri uygulamanın durumu değildir** — olay görünmeyen kopyaya gitmiş olabilir; önce uygulamanın kendi göstergesini oku. |
| **§36** | **CORS listesine eklenmeyen yeni uç tarayıcıda sessizce düşer** (durum 0; huni hatayı yutar) — sunucu ve happy-dom testleri görmez; önce `OPTIONS`+`Origin` testi. |
| **§37** | **iCloud'daki masaüstünde** üretilen dizinler (`var/cache`…) " 2" çakışma kopyası alır — `cache:clear` kod değişmeden kırmızı; önce `find . -name "* 2"`. |
| **§38** | **Docker'daki Vite sunucusu macOS'taki dosya değişikliğini görmeyebilir** — tarayıcı eski stili hatasız çizer; önce sunulan dosyayı `curl` ile oku, eskiyse kabı yeniden başlat. |

---

## 5. Referanslar

| Dosya | Ne için |
|---|---|
| [PLAN.md](PLAN.md) | **Aktif sprint**: şu anki 3-4 görev, backlog. Bitmiş iş `docs/decisions/`e taşınır |
| [docs/pre-setup.md](docs/pre-setup.md) | **Kararlar ve NEDEN'leri** + reddedilen alternatifler + risk tablosu |
| [docs/decisions/acik-uclar.md](docs/decisions/acik-uclar.md) | Karara dönüşmemiş gözlemler (Ç-no) |
| [docs/tuzaklar.md](docs/tuzaklar.md) | §4'teki tuzakların tam metni, ölçümü ve doğrusu |
| [docs/uygulama-entegrasyonu.md](docs/uygulama-entegrasyonu.md) | Bir uygulama (KeepChat…) çekirdeğe nasıl bağlanır: anahtar, uçlar, akış, App Store kuralları |
| [docs/yontem/](docs/yontem/README.md) | **Yöntem el kitabı** (okuyucu için, bölümlü): nasıl ve neden böyle çalışıyoruz; kayıtlara bağlantı verir, kopyalamaz |
| `.claude/skills/` | `blok` (işin dokuz adımı) · `kirma` (testin ölçtüğünü kanıtla) · `belge` (bilgiyi depoya yaz) · `kontrol` (tam doğrulama — **yığına göre doldur**) |
| `.claude/agents/` | `sinayici` (kontrolü koşturur, özet döner) · `prompt-elestirmeni` (promptu harcamadan önce denetler) |
| `.claude/hooks/` | `git-checkout-engel` (kural 3) |

---

## 6. Belge yapısı — nereye ne yazılır

| Yazdığın şey | Gideceği yer |
|---|---|
| Her oturumda geçerli **değişmez** kural | bu dosya (§2), **tek madde** |
| Hata vermeden yanlış sonuç üreten şey | `docs/tuzaklar.md` + bu dosyada §4'e **tek satır** |
| Karar + reddedilen alternatif + sebep | `docs/pre-setup.md` |
| Blok kaydı (ölçüm, kırma denemesi, CI koşumu) | `docs/decisions/<konu>.md` |
| Şu an yapılan / sıradaki iş | `PLAN.md` §1-§2 |
| Fark ettim ama karar vermedim | `docs/decisions/acik-uclar.md` (Ç-no) + tetiklediği blok |
| Bir ajanın **kalıcı** bilmesi gereken şey | `.claude/agents/<ad>.md` ya da skill |
| Yeni yöntem / ders (okuyucu için anlatım) | `docs/yontem/<bölüm>.md` 1–5 satır + `07-yolculuk.md` tek satır |

⚠️ **Kapanış denetimi:** turu bitirmeden, kapanış mesajındaki her
"fark ettim / karar vermedim" cümlesi için `grep` ile depodaki karşılığını
göster. Bulamadığın cümleyi **yaz**. Türkçe ararken `grep -i` kullanma (§2).
