---
title: "Verify your Composer dependencies' provenance"
description: "Sign and verify PHP package provenance with Sigstore and GitHub build attestations, end to end."
date: 2026-07-05
---

When the xz backdoor landed in 2024, it was a wake-up call for every package
ecosystem: the code you install is only as trustworthy as the pipeline that built
it. PHP is no exception. Run `composer install` and you pull down code from dozens
of repositories — but nothing checks *who* built each package, or whether the
archive you received is the one its maintainer actually published.

`composer.lock` doesn't solve this. It pins a `dist` hash, so it verifies you got
the *same bytes* every time — but it says nothing about *where those bytes came
from*. If an attacker publishes a malicious release, the lock file faithfully pins
the malicious hash. Integrity is not provenance.

The rest of the software world has an answer to this now: **Sigstore** and
**build-provenance attestations**. npm ships Sigstore-signed provenance. GitHub
Actions can attest any build artifact, recording a signed statement — "this
artifact was built by *this workflow* in *this repository*" — in a public
transparency log. Until recently, PHP had no way to produce or consume any of it.

This post shows the whole loop working end to end, on real packages.

## The one subtlety that matters: attest the zipball

Here's the trap that makes naïve provenance for Composer *silently useless*.

Most "sign your release" setups attest a **release tarball** — the output of
`git archive`, uploaded as a GitHub release asset. But Composer doesn't install
that. It installs the **dist zipball**: `api.github.com/repos/{owner}/{repo}/zipball/{commit}`
— a different artifact with a different digest. Attest the tarball and your
attestation covers a file *nobody installs*. A verifier checking what Composer
actually downloaded finds nothing.

The fix is to attest the exact zipball Composer fetches. Its digest is
reproducible for a given commit, and — crucially — the commit is the same
reference Packagist records as the package's `dist`. Attest *that*, and the
attestation covers the bytes that land in `vendor/`.

## Signing: one line in your release workflow

[`k2gl/composer-attest-action`](https://github.com/k2gl/composer-attest-action) is
a GitHub Action that does exactly this — it attests both the tarball and the dist
zipball. Drop it into a workflow that runs on version tags:

```yaml
name: Attest
on:
  push:
    tags: ['[0-9]*.[0-9]*.[0-9]*']
permissions:
  id-token: write        # request the Sigstore signing certificate
  attestations: write    # record the attestation
  contents: write        # attach signed assets to the release
jobs:
  attest:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with: { persist-credentials: false }
      - uses: k2gl/composer-attest-action@v1
```

On every version tag it builds and attests the tarball, fetches and attests the
Composer dist zipball, and records both in Sigstore's public transparency log —
bound to your repository's GitHub Actions identity. No keys to manage; the signing
certificate is short-lived and issued against your workflow's OIDC token.

## Verifying: at install time

[`k2gl/composer-attest`](https://github.com/k2gl/composer-attest) is a Composer
plugin that verifies these attestations as packages are downloaded:

```bash
composer require --dev k2gl/composer-attest
```

As Composer downloads each package, the plugin hashes the dist, asks GitHub for an
attestation bound to that digest, and verifies the Sigstore bundle — checking the
certificate chain, the transparency-log inclusion, and that the signing identity is
a GitHub Actions workflow of the package's own repository. Configure how strict it
is in `composer.json`:

```json
{
  "extra": {
    "k2gl-attest": {
      "mode": "enforce",
      "require-attestation": false
    }
  }
}
```

In `warn` mode it reports and continues; in `enforce` it fails the install on a
bad attestation. Under the hood it reuses
[`k2gl/sigstore-verify`](https://github.com/k2gl/sigstore-verify), a pure-PHP
Sigstore verifier that passes the official sigstore-conformance suite.

## Does it work? Here's the proof

The entire k2gl package family attests its dist zipball on every release. Verifying
one against the live GitHub attestations API:

```
dsse zipball — hasAttestation: yes, verified: YES (k2gl/dsse)
```

The plugin even verifies *itself*: `composer-attest`'s own release is attested by
`composer-attest-action`, and the plugin confirms its own provenance. The loop is
closed — sign with the Action, verify with the plugin, on real published packages.

## The honest caveat

The verification is real, but adoption is a chicken-and-egg problem worth stating
plainly. Today almost no package on Packagist attests its dist zipball, so for a
typical project the plugin will mostly report "no attestation." That's not a bug in
the plugin — it's the state of the ecosystem. The plugin verifies whatever *is*
attested; the more maintainers add the Action, the more it can check.

That's why the Action matters as much as the plugin: it makes attesting a one-line
change, so the pool of verifiable packages can grow. The endgame is registry-level
support — if Packagist hosts attestations directly, client-side verification
becomes the default for the whole registry, and these tools verify it with no
changes.

## Links

- Sign your package: [`k2gl/composer-attest-action`](https://github.com/k2gl/composer-attest-action)
- Verify what you install: [`k2gl/composer-attest`](https://github.com/k2gl/composer-attest)
- The verifier underneath: [`k2gl/sigstore-verify`](https://github.com/k2gl/sigstore-verify)
