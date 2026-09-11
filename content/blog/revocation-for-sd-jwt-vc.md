---
title: "Revocation for SD-JWT VC, in PHP"
description: "k2gl/token-status-list implements the Token Status List draft — the mechanism SD-JWT VC uses to say a credential was revoked — with the draft's test vectors reproduced byte for byte."
date: 2026-09-11
---

Verifying an SD-JWT VC answers one question: did this issuer sign this credential,
and is the holder the one presenting it. It does not answer whether the issuer
still stands behind it. For that, SD-JWT VC points at a separate mechanism, and
the one the ecosystem settled on is the
[Token Status List](https://datatracker.ietf.org/doc/draft-ietf-oauth-status-list/):
the issuer publishes one signed bit array for many credentials, each credential
carries an index into it, and a relying party fetches the list once and reads a
couple of bits.

[`k2gl/token-status-list`](/packages/token-status-list) is that, in pure PHP. It
was the obvious next package: [`k2gl/sd-jwt-vc`](/packages/sd-jwt-vc) already
surfaced the `status` claim, but a PHP relying party had nothing to check it
against — Packagist had no implementation at all.

## The shape of it

A list is `bits` per status (1, 2, 4 or 8) packed from the least significant bit
of each byte, then DEFLATE-compressed in the zlib format and base64url-encoded.
That byte string travels inside a Status List Token, a JWT with `typ:
statuslist+jwt`, `sub` equal to its own URI, `iat`, and optionally `exp` and a
`ttl` for caching. A credential references it as

```json
{"status": {"status_list": {"idx": 42, "uri": "https://example.com/statuslists/1"}}}
```

On the relying-party side the whole of the draft's validation section is one
call:

```php
use K2gl\TokenStatusList\StatusListResolver;
use K2gl\TokenStatusList\StatusReference;

$resolver = new StatusListResolver($psr18Client, $psr17RequestFactory, $statusIssuerKey, cache: $psr16Cache);

$status = $resolver->check(StatusReference::fromClaim($credential->status()));
$status->isValid();      // 0x00
$status->isInvalid();    // 0x01 — revoked
$status->isSuspended();  // 0x02
```

The resolver sends `Accept: application/statuslist+jwt`, follows redirects a
bounded number of times, refuses any other content type, verifies the token
(allow-listed `alg`, no `crit`, signature, `sub` equal to the URI you asked for,
time window), inflates the list under a size limit, and reads the index — an
index past the end of the list is a rejection, not a `VALID`. With a PSR-16
cache it keeps the token for `ttl` seconds, bounded by `exp`, and verifies the
cached copy again before trusting it. Historical resolution (`?time=`) is there
too, with the check the draft asks for: a static host that ignores the query
and serves today's list is not allowed to pass it off as last month's.

The issuer side is the same package: build a list, flip bits, sign it with any
[k2gl/dsse](/packages/dsse) key.

## Two things worth knowing

**The test vectors reproduce exactly.** The draft's Appendix C gives four lists
of a million entries each and their encoded form. They were produced with zlib
at level 9, and PHP's zlib produces the same bytes, so the test suite asserts
the exact `lst` strings rather than a round trip. That is a stronger check than
it sounds: any mistake in bit order — the classic one this format invites — would
change the bytes.

**`gzuncompress()` does not enforce its length limit.** The function takes a
`max_length` argument, and a status list is exactly the kind of untrusted,
compressible input where you want one. It turned out to return the full output
regardless (a 1000-byte payload came back whole against a limit of 999). The
package inflates through `inflate_add()` in 4 KiB chunks instead and stops as
soon as the output exceeds the limit, and while at it rejects a truncated stream
and trailing bytes after the stream end. If you decompress untrusted zlib data in
PHP anywhere else, check what your limit actually does.

## With sd-jwt-vc

The two packages are wired at the seam: `VerifiedSdJwtVc::status()` returns the
claim, `StatusReference::fromClaim()` takes it. The
[guide](/guides/check-credential-revocation) walks through both ends. Draft -19
of SD-JWT VC, which `sd-jwt-vc` 2.0 tracks, also pins the Status List Token to
the JWT form — the one implemented here; the CWT form is deliberately out of
scope.

```bash
composer require k2gl/token-status-list
```
