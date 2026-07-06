---
title: "Gate dependency licenses at install"
description: "Fail composer install when a dependency's license falls outside your policy, using the composer-license-gate plugin."
order: 30
---

[`k2gl/composer-license-gate`](/packages/composer-license-gate) checks the licenses
of your whole dependency tree as Composer installs it, and can fail the build when
something falls outside your policy — a GPL package slipping into a proprietary
product, for instance.

## Install

```bash
composer require --dev k2gl/composer-license-gate
```

## Configure

Set an allow-list (or deny-list) in your root `composer.json`:

```json
{
  "extra": {
    "k2gl-license-gate": {
      "mode": "warn",
      "allow": ["MIT", "BSD-2-Clause", "BSD-3-Clause", "Apache-2.0", "ISC"]
    }
  }
}
```

- `mode`: `warn` (report and continue), `enforce` (fail the install), or `off`.
- `allow` / `deny`: SPDX identifiers; `*` is a suffix wildcard.
- `allow-packages`: exceptions for specific packages.
- `require-license`: treat a package with no declared license as a failure.

In `warn` it reports offenders; in `enforce` it aborts the install with a non-zero
exit. This runs at the install lifecycle, not as a separate CLI step — so the policy
is enforced wherever `composer install` runs, including CI.
