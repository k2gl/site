---
title: "OpenVEX in PHP?"
description: "Yes — k2gl/openvex reads, writes and canonicalizes OpenVEX documents, with the same content-addressable @id as the go-vex reference."
---

**Yes.** [`k2gl/openvex`](/packages/openvex) models
[OpenVEX](https://github.com/openvex/spec) documents as typed PHP — statements,
vulnerabilities, products and subcomponents — and produces the canonical, go-vex
compatible document `@id`. No dependencies beyond `ext-json`.

VEX (Vulnerability Exploitability eXchange) answers what a scanner can't: a CVE shows
up in your SBOM, but does it actually affect the artifact you ship? An OpenVEX statement
records that judgement — `not_affected`, `affected`, `fixed` or `under_investigation` —
with a machine-readable justification, so a consumer can suppress the noise with an audit
trail.

## Where it fits

VEX is one layer of the supply-chain story:

1. **SBOM** lists what's inside; a scanner maps it to CVEs.
2. **VEX** — [`k2gl/openvex`](/packages/openvex) — states which of those CVEs actually
   affect the artifact, and why.
3. **Attest it** (optional) — wrap the document in an
   [in-toto Statement](/packages/in-toto-attestation) (`predicateType`
   `https://openvex.dev/ns`) and sign it with a [DSSE envelope](/packages/dsse), the same
   way [SLSA provenance](/packages/slsa-provenance) is attested.

## Install

```bash
composer require k2gl/openvex
```

See the [VEX in PHP guide](/guides/vex-in-php) for authoring and signing, and the
[supply-chain overview](/supply-chain) for how the pieces compose.
