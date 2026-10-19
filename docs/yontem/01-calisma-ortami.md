# 1 · Çalışma ortamı

Proje bir yapay zekâ kodlama ajanıyla (Claude Code) geliştiriliyor. Ajan
güçlü ama iki zaafı var: **ölçmeden iddia etmeye** yatkın ve **bir oturumda
öğrendiğini bir sonrakinde unutuyor**. Ortamın her parçası bu iki zaafa
karşı kuruldu.

## Kural dosyası: `CLAUDE.md` bir yönlendirici

- Her oturumda ve her alt ajanda otomatik yüklenir → büyümesi her istemin
  maliyetine eklenir. **Satır bütçesi 300** (`wc -l CLAUDE.md`).
- İçinde yalnız her oturumda geçerli olanlar: yığın, komutlar, 15 değişmez
  kural, **tuzak indeksi** (tek satırlık iddialar). Ayrıntı başka dosyada.
- Ölçüm (2026-10-19, `ai-ortami-incelemesi.md` §7): basit bir soruda
  maliyetin çoğu keşif değil bu **sabit yük**; token tasarrufunun asıl
  kaldıracı CLAUDE.md'yi kısa tutmak.

## Skill'ler ve ajanlar (projede, depoyla sürümlü)

| Ad | Ne yapar |
|---|---|
| `/blok` | bir iş parçasının 9 adımı (bölüm 2) |
| `/kirma` | testin gerçekten ölçtüğünü kanıtlama (bölüm 2) |
| `/belge` | bilgiyi depoya yazma, kapanış denetimi |
| `/kontrol` | tam doğrulama → `bin/kontrol` (bölüm 6) |
| `sinayici` ajanı | kontrolü koşturur, yalnız özet döner (ana bağlamı korur) |
| `prompt-elestirmeni` ajanı | bir istemi harcamadan önce denetler (aşağıda) |

Kullanıcı düzeyindeki aynı içerikli `ritual` eklentisi bu projede
**kapalı** (`.claude/settings.json`): iki kopya her skill'i iki kez
listeliyor, hook'u iki kez koşturuyordu; proje kopyası depoyla gidiyor ve
projeye göre budanabiliyor (`pre-setup.md` C1).

## Kural değil kilit: hook

`git checkout <dosya>` commit edilmemiş işi sessizce siler; kural
yazılıydı ve yine tekrarlandı. Çözüm kural değil **kilit**:
`.claude/hooks/git-checkout-engel.sh` komutu reddeder; geri alma `cp` ile.
Ders: bir kural iki kez çiğnendiyse onu araca çevir.

## İki terminal düzeni

| Terminal | Rol | Komut |
|---|---|---|
| 1 | geliştirme — tek yazar, tek commit'çi | `claude` |
| 2 | istem eleştirmeni — hiçbir dosyaya yazmaz | `claude --agent prompt-elestirmeni --permission-mode plan -n prompt` |

Taslak istem 2. terminalde denetlenir (ölçüm mü iddia mı, durdurma koşulu,
tek davranış mı, kayıtlı bir kararla çelişiyor mu), düzeltilmiş hâli 1.
terminale yapıştırılır. 2. terminal her istemde dosyaları **diskten**
yeniden okur ve gün başında yeniden başlatılır — CLAUDE.md oturum başındaki
hâliyle yüklenir. Ölçüm: ilk denemede kayıtlı bir tutarsızlığı yakaladı
(`ai-ortami-incelemesi.md` §4).

## İzinler

- Ajan otomatik izin kipinde çalışır; yine de bazı eylemler **kullanıcı
  onayı** ister: inceleme olmadan PR birleştirme, kendi izinlerini
  değiştirme. Ajan kendine kalıcı izin **yazamaz** — kullanıcı `/permissions`
  ile ekler (ör. `Bash(gh pr merge:*)`, `.claude/settings.local.json`, git dışı).
- Dış servise yayın (depoyu açmak, PR) kullanıcı kararıdır; öncesinde ajan
  riski ölçer (bölüm 6, sır taraması).

## Denenip eklenmeyen: kod grafı (Graphify)

"Token'ı azaltır" iddiası KeepChat üzerinde iki klonla, aynı sorularla
ölçüldü: kazanç yok (+%4…+%15 token), çünkü ajan zaten hedefli `grep` ile
keşfediyor. Eklenmedi (`pre-setup.md` C2, `ai-ortami-incelemesi.md` §7).
Ders: bir aracın vaadini kendi iş yükünde **önce/sonra** ölç.
