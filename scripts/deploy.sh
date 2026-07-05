#!/usr/bin/env bash
# Pull the latest (or pinned) image and bring the site up, then smoke-test it.
# Run from the directory holding docker-compose.yml + .env.
set -euo pipefail
cd "$(dirname "$0")"

COMPOSE="docker compose -f docker-compose.yml"
[ -f docker-compose.yml ] || COMPOSE="docker compose -f ../deploy/docker-compose.yml"

$COMPOSE pull
$COMPOSE up -d
sleep 3
exec "$(dirname "$0")/smoke.sh"
