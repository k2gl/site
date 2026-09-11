---
title: "Credential revocation in PHP?"
description: "Yes — check whether an SD-JWT VC was revoked or suspended with a Token Status List (draft-ietf-oauth-status-list), and publish lists for the credentials you issue, in pure PHP."
---

**Yes.** Revocation for SD-JWT VC is the **Token Status List**
([draft-ietf-oauth-status-list](https://datatracker.ietf.org/doc/draft-ietf-oauth-status-list/)):
the issuer publishes one signed, zlib-compressed bit array, each credential's
`status` claim points at it with an index, and a relying party reads a couple of
bits. [`k2gl/token-status-list`](/packages/token-status-list) does both sides in
pure PHP:

```php
use K2gl\TokenStatusList\StatusListResolver;
use K2gl\TokenStatusList\StatusReference;

$resolver = new StatusListResolver($psr18Client, $psr17RequestFactory, $statusIssuerKey, cache: $psr16Cache);

$resolver->check(StatusReference::fromClaim($credential->status()))->isValid();
```

The token is verified fail-closed (typ, allowed algorithms, signature, subject,
time window), the list is inflated under a size limit, and an index outside the
list is a rejection rather than a `VALID`. Walkthrough, issuer side included:
[check whether a credential was revoked](/guides/check-credential-revocation).

## What it is not

It answers "is this credential still good according to its issuer?" — nothing
else. Expiry is checked by the credential verifier, and a credential that is
expired stays expired whatever the list says. Aggregation endpoints and the CWT
form of the list are out of scope; the draft requires the JWT form for SD-JWT VC
anyway.

## The credential itself

Verifying the presentation comes first: [`k2gl/sd-jwt-vc`](/packages/sd-jwt-vc)
— see [EUDI wallet relying party in PHP?](/compare/eudi-relying-party-php)
