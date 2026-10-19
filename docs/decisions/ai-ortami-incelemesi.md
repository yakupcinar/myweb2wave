# AI ortamı incelemesi + iki terminal düzeni — 2026-10-19

> Kod yok, test yok: bu bir **inceleme kaydı**. Karar değil; kararlar
> `docs/pre-setup.md`'ye, açık kalanlar `acik-uclar.md`'ye (Ç1–Ç7).

## 1 · Ürün: web2wave nedir (ölçüm: web araması, satıcının kendi sayfaları)

web2wave.com bir **web-to-app huni kurucusu**: mobil uygulama için web'de
onboarding/quiz hunisi → web paywall → web ödemesi → uygulamaya derin
bağlantıyla dönüş. Mağaza komisyonunu (%15–30) web ödemesiyle atlatma iddiası.
Satıcının listelediği parçalar:

| Parça | Ayrıntı |
|---|---|
| Quiz/huni editörü | sürükle-bırak, dallanma mantığı, AI ile huni üretme |
| Paywall | Stripe · Paddle · PayPal · Primer; abonelik, tek seferlik |
| Deney | A/B testi |
| Uygulamaya dönüş | SDK + deep link; RevenueCat · Adapty · Qonversion |
| Atıf / analitik | AppsFlyer · Adjust · Amplitude · PostHog · GA |

⚠️ Kaynaklar pazarlama metni; fiyat ve sınırlar ölçülmedi. Kapalı kaynak —
kural 12: yalnız **davranış** alınır.

## 2 · Mevcut AI ortamı (ölçüm: `git ls-files`, `~/.claude/settings.json`,
`installed_plugins.json`, `diff -rq`)

- Proje: CLAUDE.md (159 satır / bütçe 300), 4 skill, 2 ajan, 1 hook.
- Kullanıcı düzeyi: `ritual` eklentisi (`blok/kirma/belge` + `sinayici` +
  aynı hook) — proje kopyalarıyla **bayt bayt aynı**; skill listesinde
  hem `blok` hem `ritual:blok` görünüyor, hook her Bash'te **iki kez** koşuyor.
- Eklentiler: swift-lsp, gopls-lsp, karpathy-skills, obsidian (4 skill'i kapalı).
- Model: Opus 5.5, effort medium, `defaultMode: auto`.
- Bellek dizini boş; git remote yok.
- Skill/ajanlarda KeepChat artıkları: Ç2.

## 3 · Sektördeki yöntemler — bu projeye uyarlanmış hüküm

| Yöntem/araç | Ne | Hüküm |
|---|---|---|
| Plan modu (Shift+Tab) | önce plan, onaydan sonra düzenleme | **al** — her blokta ilk adım |
| `claude --worktree` / git worktree | paralel oturum, ayrı dosya ağacı | ileride; DB ayrımı kural 15 |
| Alt ajan | ayrı bağlam, yalnız sonuç döner | var (`sinayici`, `prompt-elestirmeni`) |
| Hook | kuralı kilide çevirme | var (`git-checkout-engel`) |
| GitHub Spec Kit | spec → plan → tasks → implement | yalnız **ürün spec'i** aşaması için fikir; ritüelle çakışmayan kısmı |
| BMAD | PM/Architect/QA rol ajanları | **reddet** — ağır, mevcut blok ritüeliyle ikinci bir süreç doğurur |
| Kiro / OpenSpec / Tessl / Task Master | spec odaklı IDE/CLI'lar | ölçülmedi; ihtiyaç doğmadıkça ekleme |
| Tarayıcı MCP (Claude in Chrome / Playwright) | huniyi gerçek tarayıcıda yürütmek | **al** — blok adım 6 (gerçek istekle doğrula) huni için bu |

## 4 · İki terminal düzeni — değerlendirme

**T2 = prompt yazar/eleştirir, T1 = geliştirir.** Mantıklı; depo zaten
bunu öngörmüş (`prompt-elestirmeni`: "yanlış oturumdasın, çalışma oturumuna
geç"). Kazanç: T1'in bağlamı tartışmayla, T2'nin bağlamı kod dökümüyle dolmaz.

Kurallar (ihlali sessiz hata üretir):

1. **T2 hiçbir dosyaya yazmaz.** Komut (proje kökünde):
   `claude --agent prompt-elestirmeni --permission-mode plan -n prompt`
   Ölçüldü 2026-10-19 (`-p` ile): ajan yüklendi, rapor kendi biçiminde
   (`DURUM: yanlış soru`), 7 tur, Sonnet ile $0,10; açık bir tutarsızlığı
   (Ç6) yakaladı.
   İki oturum aynı `PLAN.md`'yi yazarsa biri diğerinin işini ezer.
2. **T2 her promptta diski yeniden okur** — T1 arada commit atmış olabilir.
   CLAUDE.md oturum başındaki hâliyle yüklenir (kural 13); uzun açık kalan
   T2 bayat kurala göre prompt yazar → T2'yi gün başında yeniden başlat.
3. **Tek yazar, tek commit'çi: T1.**
4. Aktarım kopyala-yapıştır. (Yerel oturumlar arası mesaj — `SendMessage` —
   mümkün ama denenmedi: Ç7.)
5. Küçük, tek davranışlı işte T2'yi atla; plan modu yeter. Düzenin bedeli
   iki oturumun token'ı.

## 5 · Ç1 + Ç2 temizliği — 2026-10-19

**Ç1 ölçümü.** Yeni bir oturum (`claude -p --output-format stream-json`)
init satırından okundu:

| | Eklenti `ritual` | Skill'ler | `sinayici` |
|---|---|---|---|
| Önce | yüklü | belge, blok, kirma, kontrol + `ritual:` üçü | 2 (`sinayici`, `ritual:sinayici`) |
| Sonra | yok | belge, blok, kirma, kontrol | 1 |

Kırma denemesi: "önce" satırı ayarın olmadığı durum, yani ayar kaldırılınca
tekrarlar geri geliyor. Karar ve reddedilenler `pre-setup.md` §C1.

**Ç2.** `blok`, `belge`, `kirma`, `prompt-elestirmeni`, hook yorumları
temizlendi. KeepChat'e ait örnekler kaynağı belirtilerek kaldı; var olmayan
dosya/test atıfları (`TuzakSayimiTest`, `docs/summary.md`, `docs/mimari.md`,
`archive-summary.md`, `docs/plan/`, `.claude/rules/`) silindi. `belge` ve
`kirma` artık blok kaydını PLAN.md'ye değil `docs/decisions/<konu>.md`'ye
yazdırıyor (CLAUDE.md §6 ile çelişiyordu). Ölçüm: artık deseni `grep` → tek
eşleşme ("Bu oturumda öğrenilen…"), o da genel bir kural.

⚠️ **Yolda kırılan:** hook'un deny mesajına `KeepChat'te` yazınca kesme
işareti jq'nun tek tırnaklı gövdesini kapattı; hook söz dizimi hatası verdi
ve **her Bash çağrısı** düştü. "KeepChat projesinde" ile düzeldi. Doğrulama:
`bash -n` → 0; `git checkout CLAUDE.md` ve `git restore .` → deny;
`git checkout main` ve `-b x` → izin. Gürültülü hata verdiği için tuzak
değil, ama hook dosyasında tırnak içi metne kesme işareti girmez.

## 6 · Token azaltma: Graphify — değerlendirme

Kullanıcının hatırladığı depo: **Graphify** (`safishamsi/graphify`, Apache-2.0,
~128k yıldız, son sürüm 0.9.92 / 2026-10-16 — `gh repo view` ile ölçüldü).
Kodu tree-sitter ile yerelde çözüp `graphify-out/graph.json`'a bir bilgi grafı
yazar; Claude Code'a bir skill + arama/okuma öncesi "önce grafa sor" hook'u
kurar; `graphify hook install` commit'te grafı yeniden kurar.

| İddia | Kaynak | Güven |
|---|---|---|
| 71,5× az token (52 dosya: 123k → 1,7k) | Graphify'ın kendi tanıtımı | düşük |
| Gerçek 3 depoda 5–10× | bağımsız deneyen yazar | orta |
| 10 araçlı aynı-görev testinde en iyi %43 (Graphify yoktu) | ComputingForGeeks | orta — iddiaların sınırı |

**Hüküm: şimdi ekleme.** (1) Depoda kod yok; ~600 satır belge zaten her
oturumda yükleniyor. (2) Graf keşfi ucuzlatır, düzenlemeyi değil.
(3) Bayat graf — hook "dosyayı okuma, grafa sor" der; graf güncel değilse
ajan sessizce eski yapıya göre cevap verir. (4) Belge/PDF kısmı LLM'e gider,
token harcar.

**Ne zaman, nasıl ölçülecek (Ç8):** aynı keşif sorusu ("ödeme akışı hangi
dosyalardan geçer?") yeni oturumda iki kez — Graphify'sız ve `graphify
install --project` + `graphify extract --code-only` ile — `stream-json`
`result.usage` toplam token'ı karşılaştırılır; cevabın **doğruluğu** da
elle kontrol edilir. Kazanç <%30 ya da cevap yanlışsa eklenmez.

## 7 · Graphify ölçümü — KeepChat, 2026-10-19

**Ortam.** macOS, `graphifyy 0.9.92` (`uv tool install`, `brew install uv`).
KeepChat `f301d4a` iki yerel klonda (scratchpad, gerçek dizine dokunulmadı):
A = Graphify'sız, B = `graphify extract . --code-only` (9 sn, çevrimdışı,
4460 düğüm / 14573 kenar) + `graphify install --project` (CLAUDE.md'ye bölüm
+ Bash/Grep/Read/Glob önüne iki PreToolUse hook'u + skill). Her hücre
`claude -p --model claude-opus-5-5 --output-format json`, n=1.

| Soru | Kol | Tur | Toplam token | Önbelleksiz token | $ | Doğru mu |
|---|---|---|---|---|---|---|
| Q1 `kripto.Yeni` çağrıları (doğru: 3) | A | 3 | 136 880 | 38 025 | 0,333 | ✅ 3/3 |
| | B | 3 | 142 009 | 40 250 | 0,352 | ✅ 3/3 |
| Q2 `oran.Yeni` sınırlayıcıları (doğru: 10) | A | 3 | 138 141 | 36 746 | 0,326 | ✅ 10/10 |
| | B | 3 | 143 735 | 39 144 | 0,345 | ✅ 10/10 |
| Q3 WS mesajı → DB zinciri | A | 7 | 362 621 | 50 306 | 0,498 | ✅ (elle kontrol) |
| | B | 8 | 418 013 | 46 896 | 0,478 | ✅ (elle kontrol) |

Doğru cevaplar bağımsız `grep` ile çıkarıldı (tuzak §3).

**Deneme gerçekten uygulandı mı (tuzak §8)?** Oturum dökümlerinden araç
çağrıları okundu: B'de üç sorunun üçünde de **ilk çağrı `graphify query`**,
A'da hiç yok. Ama B her seferinde ardından yine `grep`/`sed` ile kaynağa
gitti — graf okumanın **yerini almadı, önüne eklendi**.

**Sonuç: kazanç yok.** Toplam token B'de +%4 / +%4 / +%15; ücret ±%5
içinde (n=1, gürültü bandı ölçülmedi). Sebep: Claude bu depoda keşfi zaten
2-6 hedefli `grep`/`sed` ile yapıyor; maliyetin çoğu keşif değil, her
oturumun **sabit yükü** (Q1'de 2 araç çağrısına ~137k token, çoğu sistem
promptu + 366 satırlık CLAUDE.md, önbellekten). 71,5× iddiası, ajanın dosya
dosya okuduğu bir taban çizgisine göre; burada o taban yok.

Yan gözlem: `extract` aynı adı taşıyan düğümleri (`CallKit`, `Security`)
**düşürdüğünü** uyarıyla bildiriyor — graf eksik olabilir.

**Karar → `pre-setup.md` §C2:** eklenmez. Token için asıl kaldıraç sabit yük:
CLAUDE.md satır bütçesi (300) ve alt ajanla keşfi ana bağlamdan ayırmak.
