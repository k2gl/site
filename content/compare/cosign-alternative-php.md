---
title: "A cosign alternative for PHP?"
description: "cosign is a Go CLI. For PHP, sigstore-verify and sigstore-sign do verification and signing in-process, no shelling out."
---

[cosign](https://github.com/sigstore/cosign) is Sigstore's Go command-line tool.
It's excellent, but it's a separate binary you shell out to. If you want Sigstore
**in your PHP process** — no `exec()`, no bundled binary — these are the PHP
equivalents:

| cosign does | In PHP |
|---|---|
| `cosign verify-blob` | [`k2gl/sigstore-verify`](/packages/sigstore-verify) |
| `cosign sign-blob` | [`k2gl/sigstore-sign`](/packages/sigstore-sign) |
| bundles (`.sigstore.json`) | [`k2gl/sigstore-bundle`](/packages/sigstore-bundle) |
| Rekor upload/lookup | [`k2gl/rekor-client`](/packages/rekor-client) |

The verifier and signer pass the official
[sigstore-conformance](https://github.com/sigstore/sigstore-conformance) suite, so
bundles they produce and verify interoperate with cosign and the other Sigstore
clients.

## When cosign is still the right tool

If you're not writing PHP — or you just want a CLI in CI — cosign is the mature,
canonical choice. These packages are for when you need Sigstore **as a library**
inside a PHP application. See the [supply-chain overview](/supply-chain).
