---
title: "VEX in PHP: suppress a CVE with an audit trail"
description: "Author an OpenVEX document in PHP, get its canonical id, query it, and optionally attest it as a signed in-toto statement — one package for the format, existing ones for the signature."
order: 48
---

A scanner maps your SBOM to CVEs; most of them don't actually reach the code you ship.
**VEX** is how you say so — `not_affected`, with a machine-readable reason — instead of
muting the alert in a spreadsheet. This walks authoring an OpenVEX document, its
content-addressable id, and (optionally) attesting it with the packages you already have.

## Author a document

```php
use K2gl\OpenVex\OpenVex;
use K2gl\OpenVex\Status;
use K2gl\OpenVex\Justification;

$document = OpenVex::create(author: 'Acme, Inc. <security@acme.example>')
    ->statement(
        vulnerability: 'CVE-2024-1234',
        status: Status::NotAffected,
        products: ['pkg:composer/acme/app@2.1.0'],
        justification: Justification::VulnerableCodeNotInExecutePath,
    )
    ->build();

file_put_contents('vex.json', $document->toJson());
```

A product is any IRI or [purl](https://github.com/package-url/purl-spec); pass a string
for the common case, or a full `Product` (with subcomponents, hashes and other
identifiers) when you need it. The status rules are enforced on construction — a
`not_affected` statement without a justification or impact statement throws rather than
producing an invalid document.

## The canonical id

Two documents with the same impact statements get the same `@id`, regardless of author or
timestamp — so a VEX document is content-addressable and easy to deduplicate. The hash is
byte-compatible with the [go-vex](https://github.com/openvex/go-vex) reference.

```php
$document->canonicalHash(); // "8ed99017…" — sha256 over the statements only
$document->generateId();    // "https://openvex.dev/docs/public/vex-8ed99017…"
```

## Read and query

```php
use K2gl\OpenVex\Document;
use K2gl\OpenVex\Status;

$document = Document::fromJson(file_get_contents('vex.json'));

foreach ($document->statementsFor('pkg:composer/acme/app@2.1.0') as $statement) {
    if ($statement->status === Status::NotAffected) {
        // suppress this CVE for that product; $statement->justification is the reason
    }
}
```

`statementsFor()` matches an IRI, purl, CPE or hash digest against each statement's
products and their subcomponents.

## Attest it (optional)

To make the document tamper-evident, wrap it as an in-toto statement — OpenVEX is an
official in-toto predicate type — and sign it with a DSSE envelope. This uses the
existing [`in-toto-attestation`](/packages/in-toto-attestation) and
[`dsse`](/packages/dsse) packages; the OpenVEX document array drops straight in as the
predicate.

```php
use K2gl\InToto\ResourceDescriptor;
use K2gl\InToto\Statement;
use K2gl\Dsse\EcdsaP256Signer;

$statement = new Statement(
    subject: [
        new ResourceDescriptor(
            name: 'app.phar',
            digest: ['sha256' => hash_file('sha256', 'dist/app.phar')],
        ),
    ],
    predicateType: 'https://openvex.dev/ns',
    predicate: $document->toArray(),
);

$envelope = $statement->sign(EcdsaP256Signer::fromPem($privatePem, keyId: 'vex-key'));
file_put_contents('vex.dsse.json', $envelope->toJson());
```

The `predicateType` is the bare `https://openvex.dev/ns` — the in-toto predicate id, not
the document's `@context` (`…/ns/v0.2.0`).

## Packages

[openvex](/packages/openvex) · [in-toto-attestation](/packages/in-toto-attestation) ·
[dsse](/packages/dsse). For the signing key in CI, see
[keyless signing in GitHub Actions](/guides/keyless-signing-in-actions); for the whole
provenance chain, [from in-toto statement to signed SLSA provenance](/guides/in-toto-to-slsa-bundle).
