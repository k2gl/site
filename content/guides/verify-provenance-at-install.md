---
title: "Verify build provenance at composer install"
description: "Add the composer-attest plugin so Composer checks each dependency's GitHub build-provenance attestation as it downloads it."
order: 10
---

[`k2gl/composer-attest`](/packages/composer-attest) verifies GitHub
build-provenance attestations for packages **as Composer downloads them** — no
separate step in CI to remember.

## Install

```bash
composer require --dev k2gl/composer-attest
```

Composer will ask to trust the plugin the first time (it runs during install).

## Configure

Set the policy in your root `composer.json`:

```json
{
  "extra": {
    "k2gl-attest": {
      "mode": "warn",
      "require-attestation": false
    }
  }
}
```

- `mode`: `warn` (report only), `enforce` (fail the install on a bad attestation),
  or `off`.
- `require-attestation`: treat a package that publishes *no* attestation as a
  failure. Off by default, since most packages don't publish one yet.

## What you'll see

```
  ✓ attestation verified for k2gl/sigstore-verify
  · no attestation for some/other-package
```

Under `enforce`, a package whose attestation fails verification aborts the install.

## How it works

On each package download the plugin hashes the dist, asks GitHub for an attestation
bound to that digest, and verifies the Sigstore bundle with
[`k2gl/sigstore-verify`](/packages/sigstore-verify) — checking the certificate
chain, transparency-log inclusion, and that the signing identity is a GitHub
Actions workflow of the package's own repository.

Most packages don't attest yet, so expect a lot of "no attestation" today — that's
the state of the ecosystem, not a plugin bug. To attest your **own** packages, see
[Sign and verify a blob](/guides/sign-and-verify-a-blob) and the
[supply-chain overview](/supply-chain).
