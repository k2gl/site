---
title: "in-toto attestations in PHP?"
description: "Yes — k2gl/in-toto-attestation builds and parses in-toto Statements in PHP, ready to carry any predicate (SLSA, SBOM) and be signed with DSSE."
---

**Yes.** [`k2gl/in-toto-attestation`](/packages/in-toto-attestation) builds and
parses [in-toto](https://in-toto.io) attestation Statements in PHP — the
`subject` + `predicateType` + `predicate` structure that tools like cosign and the
GitHub attestations API expect.

## The Statement is a wrapper

An in-toto Statement carries a predicate about one or more subjects. In PHP:

- **The predicate** — e.g. [`k2gl/slsa-provenance`](/packages/slsa-provenance) for
  build provenance, or any custom predicate (SBOM, test results).
- **The Statement** — [`k2gl/in-toto-attestation`](/packages/in-toto-attestation)
  wraps the predicate around its subjects.
- **The signature** — [`k2gl/dsse`](/packages/dsse) envelopes the Statement;
  [`k2gl/sigstore-sign`](/packages/sigstore-sign) produces a full bundle, and
  [`k2gl/sigstore-verify`](/packages/sigstore-verify) verifies it (this is exactly
  what GitHub build-provenance attestations are).

## Install

```bash
composer require k2gl/in-toto-attestation
```

See the [supply-chain overview](/supply-chain).
