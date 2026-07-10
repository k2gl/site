---
title: "EUDI wallet relying party in PHP?"
description: "Yes — verify the EU Digital Identity Wallet's credential format (SD-JWT VC, dc+sd-jwt) in pure PHP: issuer signature, disclosures, key binding."
---

**Yes — the credential-format part.** When an EUDI wallet presents a credential,
what arrives at your service is an **SD-JWT VC** (`dc+sd-jwt`):
the issuer-signed token, the claims the holder chose to disclose, and a Key
Binding JWT tied to your audience and nonce.
[`k2gl/sd-jwt-vc`](/packages/sd-jwt-vc) verifies all of it in pure PHP:

```php
use K2gl\Dsse\PublicKey;
use K2gl\SdJwt\KeyBinding;
use K2gl\SdJwtVc\SdJwtVcVerifier;

$verified = new SdJwtVcVerifier()->verifyPresentation(
    $presentation,
    PublicKey::fromJwk($issuerJwk),
    KeyBinding::required(audience: 'https://you.example', nonce: $nonce, maxAgeSeconds: 300),
);
```

Issuer keys can be pinned, taken from `x5c` chains, or discovered via the
issuer's metadata; the `vct` and protected-claims rules of the SD-JWT VC spec are
enforced. Walkthrough: [verify an SD-JWT VC presentation](/guides/verify-sd-jwt-vc-presentation).

## Scope, honestly

The OpenID4VP transport — request objects, response modes, session management —
is your framework's territory; these packages cover the **credential format and
its cryptography**, which is the part that must be exactly right. Context and
specs: [digital identity overview](/identity).

## Poke at the format first

[Generate a demo credential](/tools/sd-jwt-issue), then
[decode it](/tools/sd-jwt) — both tools run on these packages.
