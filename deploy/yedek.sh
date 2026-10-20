#!/usr/bin/env bash
# Postgres yedeği — dagit.sh göçten önce çağırır; ayrıca günlük cron
# (deploy/README.md). Yedek aynı sunucuda: disk giderse yedek de gider (Ç33).
set -u
cd "$(dirname "$0")/.." || exit 1

DIZIN=${YEDEK_DIZINI:-/var/backups/myweb2wave}
SAKLA_GUN=${YEDEK_SAKLA_GUN:-14}
mkdir -p "$DIZIN" || exit 1
dosya="$DIZIN/app-$(date -u +%Y%m%dT%H%M%SZ).dump"

# compose dosyası APP_VERSION'ı zorunlu tutuyor (dagit.sh verir); cron'dan
# çağrılınca yok. exec çalışan konteyneri değiştirmez, değer yalnız yer tutucu.
APP_VERSION=${APP_VERSION:-yedek} docker compose --env-file deploy/.env.uretim \
    -f deploy/compose.yaml exec -T db pg_dump -U app -Fc app > "$dosya.yarim"
kod=$?
# pg_dump yarıda kalırsa yarım dosya "yedek var" sanılmasın.
if [ $kod -ne 0 ] || [ ! -s "$dosya.yarim" ]; then
    rm -f "$dosya.yarim"
    echo "HATA: pg_dump çıkış=$kod"
    exit 1
fi
mv "$dosya.yarim" "$dosya"
chmod 600 "$dosya"
find "$DIZIN" -name 'app-*.dump' -mtime +"$SAKLA_GUN" -delete
echo "yedek: $dosya ($(wc -c < "$dosya") bayt)"
