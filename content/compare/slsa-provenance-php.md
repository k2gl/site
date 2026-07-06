---
title: "SLSA provenance in PHP?"
description: "Yes — k2gl/slsa-provenance models SLSA v1 and v0.2 provenance predicates as typed PHP, ready to wrap in an in-toto Statement and sign."
---

**Yes.** [`k2gl/slsa-provenance`](/packages/slsa-provenance) models
[SLSA](https://slsa.dev) provenance predicates (v1 and v0.2) as typed PHP — the
builder, the build definition, materials and byproducts — instead of hand-built
arrays.

## Where it fits

A SLSA predicate is only half of an attestation. The full flow in PHP:

1. **Build the predicate** — [`k2gl/slsa-provenance`](/packages/slsa-provenance).
2. **Wrap it in a Statement** — [`k2gl/in-toto-attestation`](/packages/in-toto-attestation)
   (subject + predicateType + predicate).
3. **Sign it** — [`k2gl/dsse`](/packages/dsse) envelope, or a full Sigstore bundle
   via [`k2gl/sigstore-sign`](/packages/sigstore-sign).
4. **Verify it** — [`k2gl/sigstore-verify`](/packages/sigstore-verify).

## Install

```bash
composer require k2gl/slsa-provenance
```

See the [supply-chain overview](/supply-chain) for how the pieces compose.
