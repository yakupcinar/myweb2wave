# CLAUDE.md — proje anayasası

<!-- TODO: proje adı, tek cümlelik tanım -->
**Proje:** _henüz adı yok_ — _ne yaptığını tek cümleyle yaz_.
**Yığın:** Vite + Preact (statik huni, CDN) · Symfony (çekirdek API) · Postgres ·
Stripe (test) — `docs/pre-setup.md` §B2, gerekçesi ve reddedilenlerle.

> **Bu dosya bir yönlendirici.** Yalnız her oturumda geçerli değişmezler
> burada; ayrıntı § Referanslar'daki dosyalarda. Her oturumda ve her alt
> ajanda yükleniyor — her satır her prompta bedel yazar. Docs dosyalarını
> tam okuma; `grep -n` + `sed -n` ile yalnız ilgili aralığı oku.

---

## 1. Yığın ve ortam

<!-- TODO: yığın seçilince doldur -->
- Dil / çatı: huni = TypeScript · Vite çok sayfalı + Preact (React adları `preact/compat`); çekirdek =
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
| AI araçları (A0/A1) | `… core bin/console app:huni-arac keepchat --liste` · `… app:huni-arac keepchat taslak_kaydet < istek.json` · `ekran_goruntusu` → `core/var/ekran/*.jpg` (`ekran` kabı + `huni-dev` gerekir) |
| Özellik deposu (T2) | `… core bin/console app:ozellikler <ziyaret>` · `--kisi <kişi>` · `--sozluk` |
| Panel (React, B5; Puck dahil) | `docker compose run --rm panel npm run build` · `… panel npm test` |
| Panel ekran döngüsü (A26) | `bin/panel-ekran [sayfa…]` → `core/var/ekran/panel/*.jpg` + `olcum.json` (önce panel derlenmeli; test kullanıcısıyla) |

---

## 4. Tuzak indeksi — hata vermeden YANLIŞ CEVAP üretenler

> Burada yalnız anahtar var; belirti, ölçüm ve doğrusu
> **[docs/tuzaklar.md](docs/tuzaklar.md)**'de. Konuya girerken yalnız o
> bölümü oku: `grep -n "^## §N " docs/tuzaklar.md`. Yeni tuzak bulunca tam
> metni oraya, anahtarı buraya yaz (kural 7).

- **Kabuk ve ölçüm:** §1 `| tail` çıkış kodunu yutar · §2 `grep -i` ile ı/İ · §5 biçimlendiricinin liste kipi 0 döner · §8 yama tutmadıysa KOŞULMADI, skip geçmiş sayılmaz · §9 çift tırnak içindeki ters tırnak çalışır · §14 önce ortamı say (`df -h`) · §15 `kill $!` / `pkill -f` · §16 macOS'ta timeout / sed / grep / zsh farkları · §20 ANSI'li çıktıda `grep '^OK'` · §21 CI'da eski SHA · §22 `compose run -d` 0 döner · §23 `curl -g` · §49 `python3 -c '…'` içinde kesme işareti
- **Test yöntemi:** §3 bekleneni bağımsız hesapla · §4 DB kısıtını ayrı ölç · §6 "hangi hata" · §10 "ŞART" yorumunu ölç · §12 yedek zincir ve dolu sütun · §13 gizli sıra/zaman varsayımı · §47 sahte de aynı memory_limit'i paylaşır
- **Sistem ve araç:** §7 bildirmek uygulamak değil · §11 yanıt kimden geldi · §17 log'da "yok" = sorgu hatası · §19 PHPUnit `<env force>` · §59 test istemcisi `Accept-Language: en-us` ekler · §24 `null://null` postayı atar · §26 ortam dosyasında `$` · §29 Twig'de `app` adı · §31 `upload_max_filesize` · §37 iCloud " 2" kopyaları · §38 Docker'daki Vite dosya değişikliğini görmez · §48 YAML'da tırnaksız virgül
- **Ön yüz:** §18 SwiftUI boş kapsayıcı · §25 TS nesne yaymada fazla alan · §27 Astro slot stili (eski) · §30 Cache-Control · §32 Vitest CSS boş · §33 `waitFor` · §34 gizli sekmede animasyon · §35 betikle doldurulan alan · §36 CORS: köken + yöntem · §39 `hidden` öğe metin sorgusunda · §40 tema `useLayoutEffect` · §42 Puck `resolveData` · §43 Puck stil sızıntısı · §44 happy-dom `step` · §45 bağlama kökü flex · §46 `display` `[hidden]`'ı ezer · §51 panel sayfasına sunucu rotası
- **Veri ve para:** §28 JSONB anahtar sırası · §41 PG sıralama ≠ JS (`COLLATE "C"`) · §50 Stripe fiyatında `currency`'yi zorla · §52 kartlı deneme `paid`, tutar 0 · §53 olay imleci `(islem, id)` · §54 yeni Stripe API'de fatura bağları `parent` / kalem / `InvoicePayment` · §55 `IN` NULL'da NULL · §56 PHP 8.4 `round()` ön yuvarlamaz · §57 Postmark pasif alıcıda `send()` başarılı döner · §58 `#L` belge bağlantısı kod değişince kayar

---

## 5. Referanslar

| Dosya | Ne için |
|---|---|
| [PLAN.md](PLAN.md) | **Aktif sprint**: şu anki 3-4 görev, backlog. Bitmiş iş `docs/decisions/`e taşınır |
| [docs/pre-setup.md](docs/pre-setup.md) | **Kararlar ve NEDEN'leri** + reddedilen alternatifler + risk tablosu |
| [docs/decisions/acik-uclar.md](docs/decisions/acik-uclar.md) | Karara dönüşmemiş gözlemler (Ç-no) |
| [docs/tuzaklar.md](docs/tuzaklar.md) | §4'teki tuzak anahtarlarının tam metni, ölçümü ve doğrusu |
| [docs/adresler.md](docs/adresler.md) | Panel sayfaları, huni sayfaları, portlar ve API uçları (yerel) |
| [docs/uygulama-rehberi.md](docs/uygulama-rehberi.md) | **Uygulamayı okumak için**: panel sayfa sayfa (ne görürsün · ne yapılır · arkada ne çalışır), arka plan işleri, tablolar, mimari kurallar |
| [.tours/](.tours/) | **İnteraktif kod turları** (CodeTour): 3 modüllü uçtan uca mimari, para ve AI yürüyüşleri |
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
| Hata vermeden yanlış sonuç üreten şey | `docs/tuzaklar.md` + bu dosyada §4'e **tek anahtar** |
| Karar + reddedilen alternatif + sebep | `docs/pre-setup.md` |
| Blok kaydı (ölçüm, kırma denemesi, CI koşumu) | `docs/decisions/<konu>.md` |
| Şu an yapılan / sıradaki iş | `PLAN.md` §1-§2 |
| Fark ettim ama karar vermedim | `docs/decisions/acik-uclar.md` (Ç-no) + tetiklediği blok |
| Bir ajanın **kalıcı** bilmesi gereken şey | `.claude/agents/<ad>.md` ya da skill |
| Yeni yöntem / ders (okuyucu için anlatım) | `docs/yontem/<bölüm>.md` 1–5 satır + `07-yolculuk.md` tek satır |

⚠️ **Kapanış denetimi:** turu bitirmeden, kapanış mesajındaki her
"fark ettim / karar vermedim" cümlesi için `grep` ile depodaki karşılığını
göster. Bulamadığın cümleyi **yaz**. Türkçe ararken `grep -i` kullanma (§2).
