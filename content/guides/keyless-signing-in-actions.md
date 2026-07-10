---
title: "Keyless signing in GitHub Actions"
description: "Exchange the workflow's OIDC token for a Fulcio certificate and produce a Sigstore bundle from PHP — no long-lived keys anywhere."
order: 25
---

Keyless signing means no key to store, rotate or leak: the workflow proves its
identity with an OIDC token, [Fulcio](https://github.com/sigstore/fulcio) issues a
certificate valid for minutes, and the signature lands in the Rekor transparency
log. [`k2gl/sigstore-sign`](/packages/sigstore-sign) does the whole exchange.

## Let the job mint an identity token

```yaml
permissions:
  id-token: write   # the only thing keyless signing needs
```

## Sign

```php
use K2gl\SigstoreSign\AmbientCredentials;
use K2gl\SigstoreSign\FulcioSigningKey;
use K2gl\SigstoreSign\SigstoreSigner;

// Inside the Actions job: pick up the ambient OIDC token…
$token = AmbientCredentials::githubActions($httpClient, $requestFactory, 'sigstore');

// …trade it for a short-lived certificate, sign, log to Rekor:
$key = FulcioSigningKey::create($fulcio, $token);
$bundle = new SigstoreSigner($rekor)->signArtifact(
    file_get_contents('dist/app.phar'),
    $key,
);

file_put_contents('app.phar.sigstore.json', $bundle->toJson());
```

The clients (`$fulcio`, `$rekor` from [`k2gl/rekor-client`](/packages/rekor-client))
take whatever PSR-18 HTTP client you already use.

## Verify it later

Anyone — cosign, `gh attestation verify`, or
[`k2gl/sigstore-verify`](/packages/sigstore-verify) — can now check the bundle and
pin the identity to *your repository's workflow*. Paste one into the
[bundle inspector](/tools/sigstore-bundle) to see exactly what got recorded.

Signing a **Composer package release**? Skip the code entirely —
[attest your package](/guides/attest-your-package) with the one-line Action, which
wraps this flow.
