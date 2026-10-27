# Çekirdek API imajı — geliştirme, CI ve üretim aynı tabandan (pre-setup B2).
# Taban digest'e sabit: etiketler yeniden derleniyor, etiket tek başına
# tekrarlanabilir değil.
FROM dunglas/frankenphp:1.13.1-php8.4-bookworm@sha256:14007e6207a57651cb402970a44e5f2df15bfeffcbdeb5bbb322271949878b07 AS taban

RUN install-php-extensions pdo_pgsql intl opcache zip gd
COPY docker/myweb2wave.ini $PHP_INI_DIR/conf.d/myweb2wave.ini

COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Panel (B5; E2.4 sürükle-bırak düzenleyicisi dahil): React derlemesi. Çıktı
# (içerik özetli adlar + manifest) üretim aşamasına kopyalanır; Node imajı
# huniyle aynı, digest'e sabit.
FROM node:24.21.0-bookworm-slim@sha256:d6aa754f16b3197301076f047b5def2f02ea1dbbc2ca920407d46d7ec7f87b20 AS panel
WORKDIR /panel
COPY panel/package.json panel/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY panel/ ./
RUN npm run build

# Üretim (deploy/compose.yaml): kod imajın içinde, geliştirme bağımlılıkları
# ve testler yok, kök olmayan kullanıcı. TLS öndeki Caddy'de; çekirdek iç
# ağda :8080 dinler (kök olmayan kullanıcı 80'e bağlanamaz).
FROM taban AS uretim

ENV APP_ENV=prod APP_DEBUG=0 SERVER_NAME=:8080

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY .env ./
COPY bin bin/
COPY config config/
COPY migrations migrations/
COPY public public/
COPY --from=panel /public/varliklar/panel public/varliklar/panel/
COPY src src/
COPY templates templates/

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console cache:warmup \
    && useradd --system --no-create-home cekirdek \
    && mkdir -p /app/var/medya \
    && chown -R cekirdek /app/var /data/caddy /config/caddy

USER cekirdek

HEALTHCHECK --interval=10s --timeout=3s --retries=6 \
    CMD curl -fsS http://localhost:8080/health || exit 1
