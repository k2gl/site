---
title: "DSSE envelopes in PHP?"
description: "Yes — sign and verify Dead Simple Signing Envelopes (the format under in-toto, SLSA and Sigstore attestations) in pure PHP."
---

**Yes.** [`k2gl/dsse`](/packages/dsse) implements the
[Dead Simple Signing Envelope](https://github.com/secure-systems-lab/dsse) —
the signature wrapper underneath in-toto attestations, SLSA provenance and
Sigstore bundles. PAE (pre-authentication encoding), multiple signatures per
envelope, key IDs, ECDSA (P-256/384/521), Ed25519 and RSA.

```php
use K2gl\Dsse\Envelope;
use K2gl\Dsse\PublicKey;

// Throws unless a signature checks out; returns the authenticated payload.
$payload = Envelope::fromJson($json)->verify(PublicKey::fromPem($pem));
```

Keys load from PEM or JWK interchangeably — the same `PublicKey` class serves
the SD-JWT family too.

## Why PAE matters

DSSE never signs the raw payload: it signs
`DSSEv1 <len> <payloadType> <len> <payload>`, which binds the payload *type*
into the signature and kills type-confusion attacks. The
[DSSE debugger](/tools/dsse) shows the exact PAE preimage for any envelope you
paste — useful when your verifier and someone else's signer disagree.

## Up the stack

An envelope usually carries an in-toto statement —
[`k2gl/in-toto-attestation`](/packages/in-toto-attestation) types it,
[`k2gl/slsa-provenance`](/packages/slsa-provenance) types the SLSA predicate,
and a full Sigstore bundle adds the certificate and transparency log
([overview](/supply-chain)).
