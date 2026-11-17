# Üretim girişi: huni derlenir, statik çıktısı Caddy'nin içine konur.
# Bağlam deponun kökü; ne gireceği kökteki .dockerignore'da.

FROM node:26.12.1-bookworm-slim@sha256:88e480776c6149ff1d098cd304f44bf18057fb5d284a3329b78162d29821e28c AS huni

WORKDIR /app/funnel
COPY funnel/package.json funnel/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY funnel/ ./
COPY huni-bilesenleri/ ../huni-bilesenleri/
# Huni çekirdeğin adresini derlemede gömer; boşsa derleme durur
# (yoksa sayfa sessizce funnel/.env'deki localhost'a istek atar).
ARG PUBLIC_CORE_URL
ARG PUBLIC_TIKTOK_PIXEL_ID=
RUN test -n "$PUBLIC_CORE_URL" && npm run build

FROM caddy:2.11.7@sha256:685ae3d7c34d9b5f8caeddc5d14051db7bbd88ff1f5a2fa0c16bd8ca328fb7af

COPY deploy/Caddyfile /etc/caddy/Caddyfile
COPY --from=huni /app/funnel/dist /srv
