---
title: "Is there a Sigstore client for PHP?"
description: "Yes — a pure-PHP Sigstore verifier and signer that pass the official conformance suite, plus the attestation formats and a Composer plugin."
---

**Yes.** [`k2gl/sigstore-verify`](/packages/sigstore-verify) is a pure-PHP
implementation of Sigstore verification, and [`k2gl/sigstore-sign`](/packages/sigstore-sign)
handles signing (keyful and keyless). Both pass the official
[sigstore-conformance](https://github.com/sigstore/sigstore-conformance) suite.

## What it covers

- **Verification**: certificate chain and EKU, Rekor v1/v2 transparency-log
  inclusion, checkpoint, RFC 3161 timestamp, DSSE envelopes, and identity policy.
- **Signing**: Fulcio + ambient OIDC (keyless) or your own key, producing a bundle.
- The **formats underneath**, each as its own package:
  [dsse](/packages/dsse), [in-toto-attestation](/packages/in-toto-attestation),
  [slsa-provenance](/packages/slsa-provenance), [tuf](/packages/tuf),
  [rekor-client](/packages/rekor-client), [sigstore-bundle](/packages/sigstore-bundle).

## Installing

It's a library — bring your own PSR-18 HTTP client:

```bash
composer require k2gl/sigstore-verify
```

If what you actually want is to verify your dependencies' provenance at install
time, [`k2gl/composer-attest`](/packages/composer-attest) is a Composer plugin that
does exactly that. See the [supply-chain overview](/supply-chain).
