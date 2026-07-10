---
title: "Sign and verify a blob end to end"
description: "Produce a Sigstore bundle for an artifact with sigstore-sign, then verify it with sigstore-verify — keyless, in pure PHP."
order: 20
---

This walks through signing an arbitrary byte string (a "blob") keyless with
[`k2gl/sigstore-sign`](/packages/sigstore-sign) and verifying the resulting bundle
with [`k2gl/sigstore-verify`](/packages/sigstore-verify).

## Install

```bash
composer require k2gl/sigstore-sign k2gl/sigstore-verify
```

Both take a PSR-18 HTTP client and PSR-17 factories you supply.

## Sign

Keyless signing exchanges an OIDC identity token for a short-lived Fulcio
certificate, signs, and records the entry in Rekor — producing a bundle:

```php
use K2gl\SigstoreSign\FulcioSigningKey;
use K2gl\SigstoreSign\SigstoreSigner;

$key = FulcioSigningKey::create($fulcioClient, $oidcToken);
$bundle = new SigstoreSigner($rekorClient)->signArtifact($blob, $key);

file_put_contents('blob.sigstore.json', $bundle->toJson());
```

In CI you rarely construct the token by hand — see
[keyless signing in GitHub Actions](/guides/keyless-signing-in-actions), or let
`k2gl/composer-attest-action` wrap all of this for release artifacts.

## Verify

```php
use K2gl\Sigstore\SigstoreVerifier;
use K2gl\Sigstore\Bundle;
use K2gl\Sigstore\TrustedRoot;
use K2gl\Sigstore\IdentityPolicy;

$verifier = new SigstoreVerifier();

$verifier->verifyArtifact(
    Bundle::fromJson(file_get_contents('blob.sigstore.json')),
    $blob,
    TrustedRoot::fromSigstorePublicGood(),
    IdentityPolicy::sanRegex(
        '#^https://github\.com/your-org/your-repo/#',
        'https://token.actions.githubusercontent.com',
    ),
);
```

`verifyArtifact` throws on any failure — bad chain, missing transparency-log
inclusion, wrong identity. If it returns, the blob's provenance checks out.

The verifier passes the official
[sigstore-conformance](https://github.com/sigstore/sigstore-conformance) suite, so
bundles interoperate with cosign and other Sigstore clients. See the
[supply-chain overview](/supply-chain).
