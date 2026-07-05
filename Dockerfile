# syntax=docker/dockerfile:1

# ── Builder: SSG stage lives ONLY here, so the runtime is stack-agnostic ──
# glibc (not alpine/musl) so pagefind's prebuilt binary installs cleanly.
FROM node:22-slim AS builder
RUN corepack enable
WORKDIR /app

COPY package.json pnpm-lock.yaml ./
# --no-frozen here: pnpm 10 treats ignored build scripts as a hard error under
# --frozen, but esbuild/sharp binaries come from platform optional-deps and don't
# need their postinstall. CI runs --frozen and gates lockfile validity.
RUN pnpm install --no-frozen-lockfile

COPY . .
# Loader raw-fetches each package README + composer.json from GitHub (no sibling
# clones in the image), then astro build + pagefind emit the static site.
RUN pnpm build

# ── Runtime: tiny static server, no Node ──
FROM caddy:2-alpine
COPY Caddyfile /etc/caddy/Caddyfile
COPY --from=builder /app/dist /srv
