---
title: "Check whether a credential was revoked"
description: "Resolve the status claim of an SD-JWT VC against a Token Status List in PHP — and publish one for the credentials you issue."
order: 42
---

A credential that verifies can still have been revoked. SD-JWT VC leaves the
*how* to a separate mechanism, the **Token Status List**: the issuer publishes
one signed, compressed bit array for many credentials, each credential carries
an index into it, and a relying party fetches the list once and reads a couple
of bits. [`k2gl/token-status-list`](/packages/token-status-list) implements both
ends.

## Install

```bash
composer require k2gl/sd-jwt-vc k2gl/token-status-list
```

## Relying party: verify, then check

Verify the presentation first — the spec is explicit that an expired credential
with a `VALID` status is still expired — then hand its `status` claim to the
resolver:

```php
use K2gl\Dsse\PublicKey;
use K2gl\SdJwt\KeyBinding;
use K2gl\SdJwtVc\SdJwtVcVerifier;
use K2gl\TokenStatusList\StatusListResolver;
use K2gl\TokenStatusList\StatusReference;

$credential = new SdJwtVcVerifier()->verifyPresentation(
    $presentation,
    PublicKey::fromJwk($issuerJwk),
    KeyBinding::required(audience: 'https://you.example', nonce: $nonce),
);

$resolver = new StatusListResolver(
    httpClient: $psr18Client,
    requestFactory: $psr17RequestFactory,
    key: PublicKey::fromJwk($statusIssuerJwk),
    cache: $psr16Cache, // optional: reuses the list for its ttl, bounded by exp
);

$status = $resolver->check(StatusReference::fromClaim($credential->status()));

$status->isValid();      // 0x00
$status->isInvalid();    // 0x01 — revoked
$status->isSuspended();  // 0x02
```

`check()` is the whole of the spec's validation section: GET the URI with
`Accept: application/statuslist+jwt`, verify the Status List Token (typ, allowed
algorithms, signature, `sub` equal to the referenced URI, `iat`/`exp`), inflate
the list and read the index — an index beyond the list is a rejection, not a
`VALID`. With a PSR-16 cache the token is reused for `ttl` seconds and verified
again on every read.

## Issuer: publish a list

```php
use K2gl\Dsse\EcdsaP256Signer;
use K2gl\TokenStatusList\Status;
use K2gl\TokenStatusList\StatusList;
use K2gl\TokenStatusList\StatusListTokenIssuer;
use K2gl\TokenStatusList\StatusReference;

$list = StatusList::create(size: 100_000, bits: 1);
$list->set(42, Status::invalid());

$compact = new StatusListTokenIssuer(EcdsaP256Signer::fromPem($privateKeyPem))->issue(
    uri: 'https://example.com/statuslists/1',
    statusList: $list,
    expiresAt: time() + 7 * 86400,
    ttl: 43200,
);
// serve $compact at that URI as application/statuslist+jwt

// and in each credential you issue:
$claims['status'] = new StatusReference('https://example.com/statuslists/1', index: 42)->toClaim();
```

Use `bits: 2` when you need `SUSPENDED`; 4 or 8 bits for application-specific
statuses. The list is packed from the least significant bit, and the encoding
reproduces the draft's test vectors byte for byte.

## Where it fits

[`k2gl/sd-jwt-vc`](/packages/sd-jwt-vc) hands you the claim; the draft requires
the Status List Token of an SD-JWT VC to be a JWT, which is the format this
package implements (CWT is out of scope). Context: the
[digital identity overview](/identity).
