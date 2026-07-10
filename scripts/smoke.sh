#!/usr/bin/env bash
# Fail loudly if the live site is not serving its key routes.
set -euo pipefail

DOMAIN="${SITE_DOMAIN:-k2gl.com}"
BASE="${SMOKE_BASE:-https://$DOMAIN}"

fail=0
for path in / /packages /packages/sigstore-verify /packages/sigstore-verify.md /identity /llms.txt; do
	code=$(curl -sk -o /dev/null -w "%{http_code}" "$BASE$path" || echo 000)
	if [ "$code" = "200" ]; then
		echo "ok   $path"
	else
		echo "FAIL $path -> $code"
		fail=1
	fi
done

[ "$fail" = 0 ] || { echo "smoke failed"; exit 1; }
echo "smoke passed against $BASE"
