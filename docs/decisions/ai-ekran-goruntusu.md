# AI ekran görüntüsü aracı (Ç72 (1)) — 2026-10-27

**Sorun:** AI yalnız JSON yazıyordu, ürettiği ekranı görmüyordu (web2wave'de `web2wave_render_quiz_screen` + "render → fark → yama" döngüsü var; `web2wave-platform-yapisi.md` § Ek quiz builder). Kullanıcı: "ekran görüntüsü ile başla".

## Karar
- **Araç `ekran_goruntusu`** (A0 katmanı → MCP, panel asistanı, konsol aynı anda): girdi `huni`, `adim` (varsayılan ilk adım), `surum` (`taslak` | `yayin`). Önce çekirdekte doğrulanır (huni var mı, yayın var mı, adım tanımda mı — hatada tarayıcıya gidilmez), sonra imzalı önizleme adresi (`&yayin=1` Ç70, `&adim=`) tarayıcıda çekilir. Önizleme = ziyaret yok, ödeme kapalı.
- **Oynatıcı:** önizlemede `?adim=<kimlik>` o adımdan başlar (tıklayarak ilerlemek yok — dallanma ve zorunlu sorular yüzünden kırılgan olurdu); gerçek ziyarette yok sayılır, ölçüm hep ilk adımdan. Bilinmeyen kimlik → ilk adım. Geri tuşu durumu başlangıç adımıyla yazılır.
- **Tarayıcı ayrı kapta:** `chromedp/headless-shell:155.0.8059.40` (digest sabit, 2026-10-07, ~150 MB; MIT + Chromium BSD). Çekirdek CDP ile bağlanır: `chrome-php/chrome` 1.16.1 (MIT, 2026-07-06; + `chrome-php/wrench` 1.9.2, `monolog` 3.12.1 — hepsi MIT, iki haftadan eski; var olan paket sürümü değişmedi). chrome-php `ext-sockets` istiyor → Dockerfile'a eklendi.
- **Görüntü:** 390×844, 2× piksel, JPEG %80 → ~52–60 KB. MCP'de `ImageContent` (+ metin), panel asistanında araç sonucunda görüntü bloğu, konsolda dosya `core/var/ekran/<app>-<huni>-<adim>.jpg` (Claude Code okur).
- **Talimat:** `Tema::aiYonergesi` (iki istemin ortak metni) "kaydettikten sonra en az ilk adımı ve ödeme ekranını gör, düzelt" der.
- Reddedilen: tam Playwright imajı (~2 GB; disk %96 dolu, §14) · tarayıcıyı çekirdek imajına koymak (imaj büyür, sayfa çökmesi çekirdeği etkiler) · Node render servisi (ikinci dil/servis; CDP doğrudan yetti) · tıklayarak adıma gitmek.

## Ölçülen tuzaklar (yerel ortam)
- Önizleme adresi `localhost:4321` / `localhost:8081` içeriyor; kabın içinde localhost kabın kendisi. `--host-rules` denendi: `run.sh` bağımsız değişkeni tırnaksız (`$@`) geçiyor, boşlukta bölündü. Çözüm: imajda zaten olan **socat** ile kabın içinde 4321 → `huni-dev:4321`, 8081 → `core:80`.
- DevTools yalnız **IP ya da localhost** Host başlığını kabul ediyor ("Host header is specified and is not an IP address") → çekirdek adı IP'ye çevirir; WebSocket kökeni için `--remote-allow-origins=http://127.0.0.1`.
- Chrome `/json/version`'da kendi iç adresini (127.0.0.1:9223) yazıyor → dış adresle değiştirilir.
- Astro geliştirme araç çubuğu görüntüye giriyordu → çekmeden önce kaldırılır (üretimde yok).
- `connectToBrowser` sonrası `Browser::close()` paylaşılan tarayıcıyı öldürür → yalnız sekme kapatılır, bağlantı kesilir.
- Oynatıcı bütün adımları DOM'da tutuyor (görünmeyen `hidden`) → "metin yok" iddiası yanlış ölçer; testte görünürlük ölçülür (ilk test yazımı bu yüzden yanlış kırmızıydı).

## Ölçüm
- Testler: oynatıcı 3 (adım seçimi, bilinmeyen adım, gerçek ziyarette yok sayılır) · `HuniAraclariTest` 2 (adres/sürüm/görüntü; hatalar tarayıcıya gitmeden) · `McpTest` (ImageContent) · `PanelAiTest` (araç sonucunda görüntü bloğu) · `HuniAracCommandTest` (dosya) · `TemaTest` (talimatta araç adı). Gerçek Chrome testte yok: `SahteEkranCekici` (`services.yaml` `when@test`).
- Kırma 10/10 kırmızı: K1 adım hep ilk (ilk yama `adim!` boşken çöktü → sahte kırmızı §8, K1b ile yeniden) · K2 gerçek ziyarette de adım uygulanır · K3 adres `adim` taşımaz · K4 `yayin=1` yok · K5 adım doğrulaması yok · K6 MCP görüntü eklemez · K7 panel yalnız metin · K8 konsol dosya yazmaz · K9 tarayıcı hatası yakalanmaz · K10 yayınlanmamışta taslağa düşer.
- Gereksiz savunma kaldırıldı (§10): `adim` biçim denetimi — adımın tanımda olup olmadığı denetimi bozuk kimliği zaten reddediyor (K5 ölçüyor).
- **Gerçek sistem (yerel, Docker Desktop macOS):** konsol ilk çekim 4,8 sn, ısınmış 1,5 sn; ödeme adımı yayındaki sürümle, fiyat Stripe'tan. **Gerçek MCP HTTP** (`/mcp`, geçici anahtar, sonra iptal): `tools/list`'te araç var; `tools/call` → `isError=false`, içerik `text` + `image/jpeg` 60 KB, yanıt 82 KB; `mcp_cagrilari` kaydı. Ziyaret sayısı çekimlerden önce/sonra 103/103. Tarayıcı durdurulunca: "ekran görüntüsü alınamadı: tarayıcı servisine ulaşılamadı (ekran:9222)", çıkış 1.
- `bin/kontrol` yeşil (273 çekirdek testi).
- CI (GitHub Actions, imaj Dockerfile'dan sıfırdan — `sockets` dahil): koşu 40504699887, SHA 91a0080, `KONTROL: YEŞİL`, 273 test.
- **Ölçülmedi:** gerçek Claude'un aracı kullanıp görünümü düzelttiği (claude.ai bağlayıcısı A5b'yi, panel anahtarı yok); üretim dağıtımı.

## Açık uçlar
Ç73 (claude.ai ~100 KB yanıt sınırı, uzun adımda aşılabilir), Ç74 (üretimde `ekran` servisi yok; yerelde huni sunucusu compose dışında).

## Ek 2026-10-27 — Ç75: ekran çekimi ziyaretçinin gördüğünü göstersin
G1 panosunda görüldü: önizlemede ödeme kapalı → düğme `:disabled` soluk, koyu temada neredeyse görünmüyor; üstte önizleme şeridi içeriği aşağı itiyor. AI bunu "okunmuyor" diye renkleri bozabilirdi.
- **Karar:** araç adrese `&ekran=1` ekler; oynatıcı bu işaretle (yalnız önizlemede anlamlı) şeridi göstermez ve ödeme düğmesini etkin çizer. Önizlemede ödeme **hiç başlamaz**: düğme işleyicisi `onizleme` ise döner — `disabled`'a artık güvenilmiyor. Panelden "Aç" (insan) eskisi gibi şerit + soluk düğme görür.
- Reddedilen: yalnız araç açıklamasına uyarı (a) — model görüntüyü metinden çok tartar; işaretsiz her önizlemeyi etkin çizmek — insan "ödeme kapalı" ipucunu kaybederdi.
- Bilinçli dışarıda: çerez/izin şeridi (yeni ziyaretçi görür) çekimde yok — tasarımın parçası değil.
- Gereksiz savunma kaldırıldı (§10): `ekranCekimi = onizleme && …` — işaret yalnız `onizleme && !ekranCekimi` içinde kullanılıyor, gerçek ziyarette gözlenebilir etkisi yok.
- Kırma 5/5 kırmızı, her biri doğru iddiada (§8 denetlendi): K1 işaret okunmaz → şerit var · K2 şerit hep · **K3 önizleme koruması yok → ödeme isteği (POST) gider** · K4 düğme hep kapalı · K5 araç `ekran=1` eklemez.
- Gerçek sistem: huni sunucusunun yeni kodu sunduğu `curl` ile doğrulandı (§38), ödeme adımı çekimi şeritsiz ve düğme normal mavi. `bin/kontrol` yeşil (273 çekirdek testi; oynatıcı 53).
