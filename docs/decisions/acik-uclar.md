# Açık uçlar — karara dönüşmemiş gözlemler

> "Fark ettim ama karar vermedim" cümlesinin yeri. Her satır bir Ç-no
> alır; kapanınca silinmez, `~~Ç-no~~` + "✅ KAPANDI <tarih>" ve ölçümü.

| Ç | Gözlem | Kaynak | Tetikleyici |
|---|---|---|---|
| ~~Ç1~~ ✅ KAPANDI 2026-10-19 — proje kopyaları kaldı, `.claude/settings.json` → `enabledPlugins: {"ritual@skills-dir": false}`; ölçüm `ai-ortami-incelemesi.md` §5 | `ritual` eklentisi (kullanıcı düzeyi) ile proje `.claude/` kopyaları aynı: skill'ler iki kez listeleniyor, hook her Bash'te iki kez koşuyor. Hangisi kalacak? | `diff -rq` 2026-10-19 | ilk skill düzenlemesi |
| ~~Ç2~~ ✅ KAPANDI 2026-10-19 — artıklar silindi, belge yerleri CLAUDE.md §6'ya hizalandı; ölçüm §5 | Skill/ajanlarda KeepChat artıkları: `TuzakSayimiTest`, `docs/summary.md`, `docs/mimari.md`, `archive-summary.md`, `docs/plan/`, §B0 Laravel→Go, R1–R4, `postJson`/Pest örnekleri | `grep` 2026-10-19 | yığın kararı |
| Ç3 | `~/.claude/settings.json` `autoMode.environment` KeepChat'i (prod VPS, güvenilir repo) tarif ediyor — bu projede yanlış bağlam; KeepChat'in proje ayarına taşınmalı mı? | settings okuma | ilk dağıtım/ssh işi |
| Ç4 | `blok` skill'i "imza satırı yok" diyor; oturum talimatı Co-Authored-By istiyor. Kullanıcı kuralı önde — ama yazılı karar yok | blok/SKILL.md:76 | ilk commit |
| ~~Ç5~~ ✅ KAPANDI 2026-10-19 — `origin` = github.com/yakupcinar/myweb2wave (PRIVATE); `git ls-remote origin main` = yerel `main` = 28adf5a | Git remote yok — kural 11'deki push'un hedefi yok | `git remote -v` boş | ilk commit |
| Ç6 | Ürün kapsamı tanımsız: web2wave'in hangi dilimi MVP? (huni render + Stripe paywall + deep link önerisi) | ai-ortami-incelemesi §1 | pre-setup §A |
| Ç7 | İki terminal arası aktarım: kopyala-yapıştır mı, yerel oturum mesajı mı? Denenmedi | ai-ortami-incelemesi §4 | ilk T2 oturumu |
| ~~Ç8~~ ✅ KAPANDI 2026-10-19 — KeepChat'te ölçüldü, kazanç yok (+%4…+%15 token), eklenmedi; `ai-ortami-incelemesi.md` §7 | Graphify (kod grafı, token azaltma) şimdi eklenmedi — depoda kod yok. Kod büyüyünce aynı soruyla önce/sonra token ölçülüp karar verilecek | `ai-ortami-incelemesi.md` §6 | kaynak dosya sayısı ~100'ü geçince ya da bir keşif sorusu >50k token yediğinde |
