#!/usr/bin/env bash
# Üretime dağıtım — sunucuda, deponun bir kopyasında koşar:
#   git pull && deploy/dagit.sh > /tmp/d.log 2>&1; echo EXIT=$?
# Bitiş ölçütü "komut çalıştı" değil, çalışan sistemden okunan sürüm
# (CLAUDE.md kural 10): /health → version == bu commit.
set -u
cd "$(dirname "$0")/.." || exit 1

ENV_DOSYASI=deploy/.env.uretim
dc() { docker compose --env-file "$ENV_DOSYASI" -f deploy/compose.yaml "$@"; }
dur() { echo "HATA: $*"; exit 1; }

[ -f "$ENV_DOSYASI" ] || dur "$ENV_DOSYASI yok — deploy/env.uretim.ornek'ten kopyala"

# Commit edilmemiş değişiklik varsa sürüm yalan söyler.
[ -z "$(git status --porcelain)" ] || dur "çalışma ağacı temiz değil"
APP_VERSION=$(git rev-parse --short=12 HEAD) || dur "git sürümü okunamadı"
export APP_VERSION
echo "== sürüm $APP_VERSION"

# Ön kontrol: boş değeri compose (:?) yakalar; buradakiler boş olmayan
# ama YANLIŞ değerler. Değerler ekrana basılmaz.
yapi=$(dc config --format json) || dur "compose yapılandırması geçersiz (boş değer?)"
printf '%s' "$yapi" | python3 -c '
import json, sys
e = json.load(sys.stdin)["services"]["core"]["environment"]
h = []
if not e["STRIPE_SECRET_KEY"].startswith("sk_test_"):
    h.append("STRIPE_SECRET_KEY sk_test_ değil (test modu kararı C6)")
if not e["STRIPE_WEBHOOK_SECRET"].startswith("whsec_"):
    h.append("STRIPE_WEBHOOK_SECRET whsec_ ile başlamıyor")
if len(e["APP_SECRET"]) < 32:
    h.append("APP_SECRET 32 karakterden kısa (kod özetlerinin anahtarı)")
if len(e["UYGULAMA_ANAHTARI_KEEPCHAT"]) < 32:
    h.append("UYGULAMA_ANAHTARI_KEEPCHAT 32 karakterden kısa")
if e["MAILER_DSN"].startswith("null:"):
    h.append("MAILER_DSN null — e-posta sessizce atılır (tuzak §24)")
for x in h:
    print("HATA:", x)
sys.exit(1 if h else 0)
' || exit 1

echo "== derleme"
dc build || dur "derleme"

echo "== veritabanı + göç öncesi yedek"
dc up -d --wait db || dur "db ayağa kalkmadı"
deploy/yedek.sh || dur "yedek alınamadı — göç yapılmadı"

echo "== göç"
dc run --rm -T core php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || dur "göç"

echo "== servisler"
dc up -d --wait --remove-orphans || dur "servisler sağlıklı değil"

echo "== ölçüm (dışarıdan, TLS üzerinden)"
API_ALANI=$(printf '%s' "$yapi" | python3 -c 'import json,sys; print(json.load(sys.stdin)["services"]["web"]["environment"]["API_ALANI"])')
HUNI_ALANI=$(printf '%s' "$yapi" | python3 -c 'import json,sys; print(json.load(sys.stdin)["services"]["web"]["environment"]["HUNI_ALANI"])')
saglik=$(curl -fsS --max-time 20 "https://$API_ALANI/health") || dur "https://$API_ALANI/health yanıt vermedi"
echo "$saglik"
printf '%s' "$saglik" | APP_VERSION="$APP_VERSION" python3 -c '
import json, os, sys
d = json.load(sys.stdin)
sys.exit(0 if d.get("status") == "ok" and d.get("db") == "ok" and d.get("version") == os.environ["APP_VERSION"] else 1)
' || dur "/health beklenen sürümü ($APP_VERSION) ya da db:ok döndürmüyor"
huni=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "https://$HUNI_ALANI/keepchat/") || dur "huni yanıt vermedi"
[ "$huni" = "200" ] || dur "https://$HUNI_ALANI/keepchat/ → $huni"

echo "DAGITIM TAMAM sürüm=$APP_VERSION"
