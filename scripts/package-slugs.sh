#!/bin/sh
# The catalog's package slugs, read from the PACKAGES list in src/content.config.ts,
# so that workflows never carry a second copy of it.
sed -n '/export const PACKAGES/,/^];/p' "$(dirname "$0")/../src/content.config.ts" \
  | grep -oE "'[a-z0-9-]+'" | tr -d "'" | tr '\n' ' '
