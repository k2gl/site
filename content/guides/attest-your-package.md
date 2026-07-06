---
title: "Attest your own Composer package"
description: "Add build-provenance attestations to your package's releases with one line, using composer-attest-action — so anyone can verify what they install."
order: 15
---

If you publish a Composer package, you can attest its provenance so consumers can
verify it. [`k2gl/composer-attest-action`](https://github.com/k2gl/composer-attest-action)
does the whole thing in one line.

## Add the workflow

Create `.github/workflows/attest.yml`, triggered on version tags:

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

## Why it matters

The crucial detail: Composer installs the **dist zipball**
(`api.github.com/repos/{owner}/{repo}/zipball/{commit}`), not a release tarball.
The action attests **both**, so a verifier checking what Composer actually
downloaded finds a matching attestation — signed by your repository's own GitHub
Actions identity, recorded in Sigstore's public transparency log. No keys to manage.

Consumers then verify with [`k2gl/composer-attest`](/guides/verify-provenance-at-install).
See the [supply-chain overview](/supply-chain).
