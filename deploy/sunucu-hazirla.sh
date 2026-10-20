#!/usr/bin/env bash
# Yeni Ubuntu 24.04 sunucusunu bir kez hazırlar — root olarak:
#   bash sunucu-hazirla.sh > /root/hazirla.log 2>&1; echo EXIT=$?
# Tekrar koşmak güvenli (her adım "zaten var mı" diye bakar).
set -u
dur() { echo "HATA: $*"; exit 1; }
[ "$(id -u)" = "0" ] || dur "root olarak koş"
. /etc/os-release
[ "$ID $VERSION_ID" = "ubuntu 24.04" ] || dur "Ubuntu 24.04 bekleniyordu: $ID $VERSION_ID"

KULLANICI=dagitim
DEPO=https://github.com/yakupcinar/myweb2wave.git
DIZIN=/opt/myweb2wave

echo "== paketler + otomatik güvenlik güncellemeleri"
export DEBIAN_FRONTEND=noninteractive
apt-get update -q && apt-get upgrade -yq || dur "apt"
apt-get install -yq ca-certificates curl git ufw unattended-upgrades python3 || dur "paket"

echo "== Docker (resmi apt deposu)"
if ! command -v docker > /dev/null; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc || dur "docker anahtarı"
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $VERSION_CODENAME stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -q && apt-get install -yq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin || dur "docker"
fi
docker compose version || dur "compose yok"

echo "== dağıtım kullanıcısı ($KULLANICI): root'un ssh anahtarı kopyalanır"
id "$KULLANICI" > /dev/null 2>&1 || useradd --create-home --shell /bin/bash --groups docker "$KULLANICI"
install -d -m 700 -o "$KULLANICI" -g "$KULLANICI" /home/$KULLANICI/.ssh
install -m 600 -o "$KULLANICI" -g "$KULLANICI" /root/.ssh/authorized_keys /home/$KULLANICI/.ssh/authorized_keys || dur "root'ta ssh anahtarı yok"

echo "== güvenlik duvarı: yalnız 22, 80, 443"
# Not: Docker yayınlanan portları ufw'yi atlayarak açar; compose'da yalnız
# web 80/443 yayınlanıyor, db yayınlanmıyor (dagitim.md'de ölçüldü).
ufw allow OpenSSH && ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 443/udp || dur "ufw kural"
ufw --force enable || dur "ufw"

echo "== ssh: şifreyle giriş ve root girişi kapalı"
# Anahtar dağıtım kullanıcısına kopyalanmadan kapatılmaz (kilitlenmeyelim).
[ -s /home/$KULLANICI/.ssh/authorized_keys ] || dur "dağıtım kullanıcısının anahtarı boş"
printf 'PasswordAuthentication no\nPermitRootLogin no\nKbdInteractiveAuthentication no\n' > /etc/ssh/sshd_config.d/10-myweb2wave.conf
sshd -t || dur "sshd yapılandırması geçersiz"
systemctl reload ssh || dur "ssh yeniden yüklenemedi"

echo "== depo + yedek dizini + günlük yedek"
[ -d "$DIZIN/.git" ] || git clone -q "$DEPO" "$DIZIN" || dur "clone"
chown -R "$KULLANICI:$KULLANICI" "$DIZIN"
install -d -m 700 -o "$KULLANICI" -g "$KULLANICI" /var/backups/myweb2wave
echo "15 3 * * * $KULLANICI $DIZIN/deploy/yedek.sh >> /var/log/myweb2wave-yedek.log 2>&1" > /etc/cron.d/myweb2wave-yedek
touch /var/log/myweb2wave-yedek.log && chown "$KULLANICI" /var/log/myweb2wave-yedek.log

echo "HAZIRLIK TAMAM — sonraki: $KULLANICI ile gir, deploy/.env.uretim'i doldur, deploy/dagit.sh"
