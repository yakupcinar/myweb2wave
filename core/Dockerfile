# Çekirdek API imajı — geliştirme, CI ve üretim aynı tabandan (pre-setup B2).
# Taban digest'e sabit: etiketler yeniden derleniyor, etiket tek başına
# tekrarlanabilir değil.
FROM dunglas/frankenphp:1.13.1-php8.4-bookworm@sha256:14007e6207a57651cb402970a44e5f2df15bfeffcbdeb5bbb322271949878b07 AS taban

RUN install-php-extensions pdo_pgsql intl opcache zip

COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

WORKDIR /app

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
COPY src src/
COPY templates templates/

RUN composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console cache:warmup \
    && useradd --system --no-create-home cekirdek \
    && chown -R cekirdek /app/var /data/caddy /config/caddy

USER cekirdek

HEALTHCHECK --interval=10s --timeout=3s --retries=6 \
    CMD curl -fsS http://localhost:8080/health || exit 1
