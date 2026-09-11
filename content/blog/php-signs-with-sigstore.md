---
title: "PHP signs with Sigstore now"
description: "k2gl/sigstore-sign closes the loop: keyful or keyless signing, Rekor v1 and v2, RFC 3161 timestamps, a v0.3 bundle — and the official conformance suite passes on the signing side as well as the verifying side."
date: 2026-09-11
---

When [`k2gl/sigstore-verify`](/packages/sigstore-verify) first passed the
official conformance suite, it did so with `skip-signing: true`: PHP could check a
Sigstore bundle, but could not produce one. Every other language client on
docs.sigstore.dev does both, and for a client that is the line between "a
verifier" and "a client". That line is behind us now.
[`k2gl/sigstore-sign`](/packages/sigstore-sign) signs an artifact or an
attestation, logs the entry to Rekor, gets an RFC 3161 timestamp, and assembles
the `.sigstore.json` bundle — and
[sigstore-conformance](https://github.com/sigstore/sigstore-conformance) v0.0.29
passes in full, signing included, against the live staging Fulcio, Rekor v2 and
timestamp authority.

## The flow

Keyful signing, with a key you hold:

```php
use K2gl\Dsse\EcdsaP256Signer;
use K2gl\RekorClient\{RekorClient, KeyDetails};
use K2gl\SigstoreSign\{SigstoreSigner, SigningKey, TsaClient};

$rekor = new RekorClient($psr18, $psr17, $psr17, 'https://rekor.sigstore.dev');
$tsa   = new TsaClient($psr18, $psr17, $psr17, 'https://timestamp.sigstore.dev');

$key = SigningKey::publicKey(
    signer: EcdsaP256Signer::fromPem($privateKeyPem, null),
    publicKeyDer: $publicKeyDer,
    keyDetails: KeyDetails::PKIX_ECDSA_P256_SHA_256,
    hint: $hexSha256OfThePublicKey,
);

$bundleJson = new SigstoreSigner($rekor, $tsa)->signArtifact($artifact, $key)->toJson();
```

Keyless signing, the way CI does it — no long-lived key at all:

```php
use K2gl\SigstoreSign\{AmbientCredentials, FulcioClient, FulcioSigningKey, SigstoreSigner};

$oidcToken = AmbientCredentials::githubActions($psr18, $psr17); // or ::gitlabCi()
$fulcio = new FulcioClient($psr18, $psr17, $psr17, 'https://fulcio.sigstore.dev');

$key = FulcioSigningKey::create($fulcio, $oidcToken); // ephemeral P-256 key + Fulcio certificate

$bundleJson = new SigstoreSigner($rekor, $tsa)->signArtifact($artifact, $key)->toJson();
```

`FulcioSigningKey` generates the ephemeral key, proves possession of it to
Fulcio by signing the token's subject, and hands back the same `SigningKey`
type the keyful path uses, so nothing downstream changes. The bundle comes
from [`k2gl/sigstore-bundle`](/packages/sigstore-bundle), the log entry from
[`k2gl/rekor-client`](/packages/rekor-client), the signature from
[`k2gl/dsse`](/packages/dsse) — each usable on its own.

## Three things the conformance suite taught us

Passing the verification half had already shaped the family; passing the signing
half surfaced a handful of interoperability bugs, each reproduced locally and
pinned with a regression test. Three are worth passing on to anyone writing a
client.

**Proof of possession is over the `email` claim, not `sub`.** For an email
identity, Fulcio expects the ephemeral key to sign the token's `email` value;
signing `sub`, which is what a first reading of the flow suggests, yields a
certificate request Fulcio rejects.

**Signatures in a bundle are DER.** A DSSE signer naturally emits raw `r||s`
ECDSA signatures, the JOSE convention. Sigstore carries ASN.1 DER. The bundle
builder converts; the verifier accepts both, and tells them apart by length.

**`signedTimestamp` is the whole response.** The bundle field holds the full DER
`TimeStampResponse` — status info plus token — not the extracted token, which is
what the protobuf field name invites you to store.

And one rule rather than a bug: the timestamp is not optional with Rekor v2. A
v2 entry carries no integrated time, so without an RFC 3161 timestamp the bundle
logs and assembles but has no verifiable signing time, and a verifier will
reject it. Pass a `TsaClient` when you sign against v2; a v1 entry brings its
own time.

## Where to sign

One more thing the suite does not test but production does: the default
Sigstore signing configuration, published as a TUF target next to the trusted
root, still lists only Rekor **v1**. Rekor v2 lives in a separate opt-in target.
A client that hard-codes a v2 URL cannot sign against the public instance by
default. `SigningConfig` reads the target and applies the spec's selection rules
— validity window, supported API version, the `ALL`/`ANY`/`EXACT` selector — so
the client asks for what it supports and gets the right log:

```php
$config = SigningConfig::fromJson($updater->downloadTarget($updater->getTargetInfo('signing_config.v0.2.json')));
$log = $config->rekorLog();

$rekor = new RekorClient($psr18, $psr17, $psr17, baseUrl: $log->url, apiVersion: RekorApiVersion::from($log->majorApiVersion));
```

`rekor-client` speaks both versions and, since 1.2.0, retries a submission the
log could not take — with the one refinement that makes retrying safe: a
duplicate is handled, not retried. Rekor answers a resubmission with 409; v1
points at the existing entry and the client follows it, v2 reports the index.
That is exactly the "entry landed, response got lost" case a retry exists for.

## Shorter still

If your package lives on GitHub, none of the above is necessary to *publish*
provenance:

```yaml
- uses: k2gl/composer-attest-action@v1
```

The Action builds and attests the release tarball and the exact dist zipball
Composer installs, so [`k2gl/composer-attest`](/packages/composer-attest) on the
consumer's side has something to verify — the [walkthrough](/blog/verify-composer-provenance)
covers that loop. `sigstore-sign` is for everything the Action is not: other
CI systems, other artifacts, your own keys, your own bundles.

```bash
composer require k2gl/sigstore-sign
```
