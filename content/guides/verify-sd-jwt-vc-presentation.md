---
title: "Verify an SD-JWT VC presentation"
description: "Accept a dc+sd-jwt credential as a relying party: check the issuer signature, the disclosures, and the holder's key binding — in PHP."
order: 40
---

A wallet hands your service a **presentation**: the issuer-signed SD-JWT, the
disclosures the holder chose to reveal, and a Key Binding JWT proving the holder
controls the credential. [`k2gl/sd-jwt-vc`](/packages/sd-jwt-vc) verifies all
three.

## Install

```bash
composer require k2gl/sd-jwt-vc
```

## Verify

```php
use K2gl\Dsse\PublicKey;
use K2gl\SdJwt\KeyBinding;
use K2gl\SdJwtVc\SdJwtVcVerifier;

$verified = new SdJwtVcVerifier()->verifyPresentation(
    $presentation,                       // the compact ~-separated string
    PublicKey::fromJwk($issuerJwk),      // the issuer's public key
    KeyBinding::required(
        audience: 'https://verifier.example.com',
        nonce: $nonceYouIssued,
        maxAgeSeconds: 300,
    ),
);

$verified->vct();      // the credential type
$verified->claims();   // only the claims the holder disclosed
```

The verifier recomputes every disclosure digest against the signed payload —
a disclosure the issuer never committed to is rejected, not silently accepted.
Key binding ties the presentation to *your* audience and nonce, so a credential
lifted from another verifier replays nowhere.

Beyond a pinned key, issuer keys can come from `x5c` certificate chains or the
issuer's `/.well-known` metadata — see the [package page](/packages/sd-jwt-vc).

## Poke at one first

Generate a demo credential with the [SD-JWT generator](/tools/sd-jwt-issue), then
take it apart in the [debugger](/tools/sd-jwt) — or start from the
[digital identity overview](/identity).
