# syntax=docker/dockerfile:1

# ── Builder: SSG stage lives ONLY here, so the runtime is stack-agnostic ──
FROM node:20-alpine AS builder
RUN corepack enable
WORKDIR /app

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . .
# Loader raw-fetches each package README + composer.json from GitHub (no sibling
# clones in the image), then astro build + pagefind emit the static site.
RUN pnpm build

# ── Runtime: tiny static server, no Node ──
FROM caddy:2-alpine
COPY Caddyfile /etc/caddy/Caddyfile
COPY --from=builder /app/dist /srv
