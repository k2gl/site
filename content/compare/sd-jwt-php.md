---
title: "SD-JWT in PHP?"
description: "Yes — issue, present and verify Selective Disclosure JWTs (RFC 9901) in pure PHP, with online tools to generate and decode them."
---

**Yes.** [`k2gl/sd-jwt`](/packages/sd-jwt) implements
[RFC 9901](https://www.rfc-editor.org/rfc/rfc9901) — Selective Disclosure for
JWTs — end to end: issuing with chosen claims disclosable, holder-side
presentations that reveal a subset, and verification that recomputes every
disclosure digest against the signed payload.

```php
use K2gl\Dsse\PublicKey;
use K2gl\SdJwt\SdJwtVerifier;

$claims = new SdJwtVerifier()
    ->verify($compactSdJwt, PublicKey::fromJwk($issuerJwk))
    ->claims();
```

Key binding (the holder proving possession) is supported on both the issuing and
verifying side; ES256/384/512, EdDSA and RS256+ signatures.

## The credential layer

For **SD-JWT VC** (`dc+sd-jwt`) — the profile used by OpenID4VC and the EU
Digital Identity Wallet — [`k2gl/sd-jwt-vc`](/packages/sd-jwt-vc) adds the
`vct` rules and issuer key discovery. See the
[digital identity overview](/identity) and the
[relying-party guide](/guides/verify-sd-jwt-vc-presentation).

## Try it in the browser

[Generate a demo SD-JWT](/tools/sd-jwt-issue) with the claims you choose
disclosable, then [take it apart in the debugger](/tools/sd-jwt) — disclosures,
recomputed digests, recreated claims, signature check.
