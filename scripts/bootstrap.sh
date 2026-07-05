#!/usr/bin/env bash
# Bring a fresh Ubuntu 22.04/24.04 VPS to a state that can run the site.
# Idempotent — safe to re-run. Run as root (or via sudo).
set -euo pipefail

echo "==> Docker"
if ! command -v docker >/dev/null 2>&1; then
	curl -fsSL https://get.docker.com | sh
fi

echo "==> Firewall (SSH + HTTP + HTTPS only)"
if command -v ufw >/dev/null 2>&1; then
	ufw allow OpenSSH
	ufw allow 80/tcp
	ufw allow 443/tcp
	ufw allow 443/udp
	ufw --force enable
fi

echo "==> Unattended security upgrades"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq unattended-upgrades
dpkg-reconfigure -f noninteractive unattended-upgrades || true

echo
echo "Bootstrap complete."
echo "Next: put deploy/docker-compose.yml + a filled .env in a directory here,"
echo "point k2gl.com A/AAAA at this host, then run scripts/deploy.sh."
