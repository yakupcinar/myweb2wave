#!/bin/bash
#
# `git checkout <dosya>` ENGELİ — geri alma `cp` ile yapılır.
#
# ★ Bu hook iki gerçek olaydan doğdu, ikisi de aynı oturumda:
#   · 4.6X.1 / 4.6Y — İZLENEN dosyada `checkout` FAZLASINI geri aldı:
#     o oturumda yazılan ama commit'lenmemiş kod sessizce gitti.
#   · B5 — İZLENMEYEN dosyada HİÇBİR ŞEY yapmadı: kırık kod beş kırma
#     denemesi boyunca yerinde kaldı ve her koşuda fazladan kırmızı verdi.
#
# İkisinin de çözümü aynı: `cp <dosya> /tmp/x.bak` ile yedekle, `cp` ile
# geri al. Kural CLAUDE.md'de yazılı ve YİNE DE tekrarlandı — bu yüzden
# kural değil KİLİT.
#
# ⚠️ Dal değiştirme ENGELLENMİYOR: `git checkout main`, `git checkout -b x`
#    meşru. Engellenen şey DOSYA geri alma.

KOMUT=$(jq -r '.tool_input.command // ""')

case "$KOMUT" in
  *"git checkout"*|*"git restore"*) ;;
  *) exit 0 ;;
esac

# `git checkout` sonrasındaki argümanlara bak: `--` varsa ya da bir
# argüman dosya gibi görünüyorsa (yol ayırıcı veya bilinen uzantı) → dosya
# geri alma.
DOSYA_GIBI=$(printf '%s' "$KOMUT" | python3 -c '
import sys, re
k = sys.stdin.read()
m = re.search(r"git\s+(checkout|restore)\s+(.*)", k)
if not m:
    sys.exit(0)
kuyruk = m.group(2).split("&&")[0].split(";")[0].split("|")[0]
if "--" in kuyruk.split():
    print("evet"); sys.exit(0)
for tok in kuyruk.split():
    if tok.startswith("-"):
        continue
    if "/" in tok or re.search(r"\.(php|md|json|ya?ml|js|vue|css|blade\.php|xml|neon|sh|alloy)$", tok):
        print("evet"); sys.exit(0)
')

if [ "$DOSYA_GIBI" = "evet" ]; then
  jq -n --arg k "$KOMUT" '{
    hookSpecificOutput: {
      hookEventName: "PreToolUse",
      permissionDecision: "deny",
      permissionDecisionReason: ("git ile DOSYA geri alma engellendi.\n\n  " + $k + "\n\nSebep: `git checkout <dosya>` İZLENMEYEN dosyada hiçbir şey yapmıyor, İZLENEN dosyada ise o oturumda yazılmış commitlenmemiş kodu da geri alıyor. İkisi de bu projede yaşandı.\n\nDoğrusu:  cp <dosya> /tmp/x.bak   →  değiştir  →  cp /tmp/x.bak <dosya>")
    }
  }'
  exit 0
fi

exit 0
