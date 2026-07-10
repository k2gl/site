---
title: "From in-toto statement to signed SLSA provenance"
description: "Build a SLSA v1 provenance predicate, wrap it in an in-toto statement, and sign it — first as a bare DSSE envelope, then as a full Sigstore bundle."
order: 45
---

Provenance is a stack of small formats: a **SLSA predicate** says how the artifact
was built, an **in-toto statement** binds that claim to the artifact's digest, and
a **DSSE envelope** (or a full Sigstore bundle) makes it tamper-evident. One
package per layer; this walks the whole chain.

## Describe the build

```php
use K2gl\InToto\ResourceDescriptor;
use K2gl\Slsa\BuildDefinition;
use K2gl\Slsa\Builder;
use K2gl\Slsa\Provenance;
use K2gl\Slsa\RunDetails;

$provenance = new Provenance(
    buildDefinition: new BuildDefinition(
        buildType: 'https://example.com/pipelines/build/v1',
        externalParameters: ['ref' => 'refs/tags/1.0.0'],
    ),
    runDetails: new RunDetails(builder: new Builder(id: 'https://ci.example.com')),
);

$statement = $provenance->toStatement([
    new ResourceDescriptor(
        name: 'app.phar',
        digest: ['sha256' => hash_file('sha256', 'dist/app.phar')],
    ),
]);
```

## Sign it

The bare-envelope route — your key, no network:

```php
use K2gl\Dsse\EcdsaP256Signer;

$envelope = $statement->sign(EcdsaP256Signer::fromPem($privatePem, keyId: 'release-key'));
file_put_contents('provenance.dsse.json', $envelope->toJson());
```

Or the Sigstore route — certificate plus transparency log, one call:

```php
use K2gl\SigstoreSign\SigstoreSigner;

$bundle = new SigstoreSigner($rekor)->signAttestation(
    $statement->toJson(),
    'application/vnd.in-toto+json',
    $key,   // e.g. keyless — see the Actions guide
);
```

## Look at what you made

Paste the envelope into the [provenance viewer](/tools/provenance) — subjects,
builder and parameters render decoded; a bundle goes to the
[bundle inspector](/tools/sigstore-bundle). Packages:
[in-toto-attestation](/packages/in-toto-attestation) ·
[slsa-provenance](/packages/slsa-provenance) · [dsse](/packages/dsse) ·
[sigstore-sign](/packages/sigstore-sign). For the keyless `$key`, see
[keyless signing in GitHub Actions](/guides/keyless-signing-in-actions).
