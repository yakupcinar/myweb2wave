# Üretim girişi: huni derlenir, statik çıktısı Caddy'nin içine konur.
# Bağlam deponun kökü; ne gireceği kökteki .dockerignore'da.

FROM node:24.21.0-bookworm-slim@sha256:d6aa754f16b3197301076f047b5def2f02ea1dbbc2ca920407d46d7ec7f87b20 AS huni

WORKDIR /app/funnel
COPY funnel/package.json funnel/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY funnel/ ./
COPY huni-bilesenleri/ ../huni-bilesenleri/
# Huni çekirdeğin adresini derlemede gömer; boşsa derleme durur
# (yoksa sayfa sessizce funnel/.env'deki localhost'a istek atar).
ARG PUBLIC_CORE_URL
ARG PUBLIC_META_PIXEL_ID=
ARG PUBLIC_TIKTOK_PIXEL_ID=
RUN test -n "$PUBLIC_CORE_URL" && npm run build

FROM caddy:2.11.7@sha256:685ae3d7c34d9b5f8caeddc5d14051db7bbd88ff1f5a2fa0c16bd8ca328fb7af

COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY --from=huni /app/funnel/dist /srv
