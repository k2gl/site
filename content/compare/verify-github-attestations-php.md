---
title: "Verify GitHub build attestations from PHP?"
description: "Yes — k2gl/sigstore-verify verifies GitHub build-provenance attestations in pure PHP, and composer-attest does it at install time. Cross-checked against gh."
---

**Yes**, two ways depending on what you need.

## In your code

[`k2gl/sigstore-verify`](/packages/sigstore-verify) verifies GitHub
build-provenance attestations (DSSE in-toto bundles) in pure PHP — certificate
chain, Rekor transparency-log inclusion, and that the signing identity is a GitHub
Actions workflow of the expected repository.

```bash
composer require k2gl/sigstore-verify
```

## At install time

[`k2gl/composer-attest`](/packages/composer-attest) is a Composer plugin that does
this automatically as you install dependencies — it hashes each package's dist,
asks GitHub for an attestation bound to that digest, and verifies it.

```bash
composer require --dev k2gl/composer-attest
```

## Is it correct?

The verifier is cross-checked against GitHub's own `gh attestation verify`
(sigstore-go): on a real attested package both agree — verified on the clean
artifact, rejected on a tampered one. Two independent implementations, same verdict.
See the [walkthrough](/blog/verify-composer-provenance).
