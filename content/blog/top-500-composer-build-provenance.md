---
title: "Zero of the top 500 Composer packages ship build provenance"
description: "We downloaded the exact dist zips Composer installs for the 500 most popular Packagist packages and checked each for a verifiable GitHub build attestation. The number is zero."
date: 2026-07-11
---

Since 2024, any GitHub repository can publish **build provenance** for its
artifacts: a signed, transparency-logged statement that *this exact file* was
built by *this workflow* at *this commit*. It's one line in a release workflow;
npm has shown provenance on package pages since 2023, and PyPI accepts
attestations under PEP 740. So how much of the PHP ecosystem publishes it?

We checked. The answer, as of July 2026, is **zero**.

## Method

For each of the **500 most popular packages** on Packagist (by
[popularity](https://packagist.org/explore/popular)):

1. Resolve the latest stable release from Packagist's p2 metadata.
2. Download **the exact dist zip Composer would install** and hash it (SHA-256).
3. Ask GitHub for attestations bound to that digest.
4. Fully verify any answer as a Sigstore bundle — certificate chain, transparency
   log, and that the signing identity is a GitHub Actions workflow of the
   package's own repository.

That is the same check [`k2gl/composer-attest`](/packages/composer-attest) runs
at `composer install`; the study reused its verifier as a library.

Two guards against fooling ourselves. A **positive control**: packages that are
known to attest went through the identical pipeline and came back `verified`.
And **independent spot checks**: for a sample of the negatives we queried
GitHub's attestation API directly with the same digests — same 404s.

## The numbers

| | |
|---|---|
| Packages checked | 500 |
| With an installable release | 499 * |
| Publish **any** build attestation | **0** |
| Attestation present but failing verification | 0 |

\* [`roave/security-advisories`](https://packagist.org/packages/roave/security-advisories)
is a constraint-only meta-package with no releases — nothing to attest.

Not "few verify correctly". Not "some are broken". **Not one of the 499
installable packages publishes an attestation for its dist at all** — Symfony,
Laravel, PHPUnit, Guzzle, Doctrine, all of them install as unattested zips.

## Why, and why it's fixable

Nothing about this is hard anymore. For a package released from GitHub, provenance
is one workflow line:

```yaml
- uses: k2gl/composer-attest-action@v1
```

(or GitHub's own `actions/attest-build-provenance` — the
[guide](/guides/attest-your-package) shows both). The gap is a chicken-and-egg:
nobody attests because no installer asks, and no installer asks because there is
nothing to check. Both halves are now one line each — the asking side is
`composer require --dev` [`k2gl/composer-attest`](/packages/composer-attest),
which verifies attestations as Composer downloads packages and, for now, mostly
prints "no attestation". The more maintainers add the Action, the more that
output changes.

## Check any package yourself

The checker from this study is online: paste a `vendor/package` into the
[Composer attestation checker](/tools/composer-attestations) and see the verdict —
with the signing workflow and commit when there is one. It's one of eight
[tools](/tools) running on the same open-source packages this site documents:
a [Sigstore bundle inspector](/tools/sigstore-bundle), a
[DSSE debugger](/tools/dsse), an [SD-JWT debugger](/tools/sd-jwt) and
[generator](/tools/sd-jwt-issue), an [SSH signature verifier](/tools/sshsig), a
[provenance viewer](/tools/provenance), and a
[PEM ⇄ JWK converter](/tools/pem-jwk) that never uploads your keys.

## Links & next steps

- Attest your own package (one line): [guide](/guides/attest-your-package)
- Verify what you install: [guide](/guides/verify-provenance-at-install)
- How the whole stack fits together: [supply-chain security for PHP](/supply-chain)
