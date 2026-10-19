# Çekirdek API imajı — geliştirme, CI ve üretim aynı tabandan (pre-setup B2).
# Taban digest'e sabit: etiketler yeniden derleniyor, etiket tek başına
# tekrarlanabilir değil.
FROM dunglas/frankenphp:1.13.1-php8.4-bookworm@sha256:14007e6207a57651cb402970a44e5f2df15bfeffcbdeb5bbb322271949878b07

RUN install-php-extensions pdo_pgsql intl opcache zip

COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

WORKDIR /app
