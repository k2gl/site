---
title: "Eight online tools for supply-chain and identity formats"
description: "Inspect Sigstore bundles, DSSE envelopes, SLSA provenance, SD-JWTs and SSH signatures in the browser — each tool runs the same open-source PHP packages it demonstrates."
date: 2026-07-11
---

k2gl.com now has a [tools section](/tools): eight small pages where you paste a
signature artifact and get it decoded — and, where it's meaningful, *actually
verified*, not just pretty-printed. One design decision drives all of them: **the
tool output is the package output**. Each page calls the same open-source PHP
packages this site documents, so what you see in the browser is exactly what
`composer require` gets you.

A note on privacy, since people paste real tokens into things like this: requests
are processed in memory, never stored, and request bodies are never logged. The
one tool where that isn't enough — the key converter — doesn't send your input
anywhere at all.

## Sigstore, end to end

The [**bundle inspector**](/tools/sigstore-bundle) takes a `.sigstore.json` —
cosign output, a GitHub attestation — and does full verification against the
Sigstore public-good trust root: certificate chain, transparency-log inclusion,
signature. It also decodes what's usually opaque: every Fulcio certificate
extension (which workflow signed, at which commit, triggered by what), the log
entries, the in-toto payload. As far as we know there was no online tool for
this before — the alternative was cosign and `jq`.

Underneath a bundle sit two more formats with tools of their own: the
[**provenance viewer**](/tools/provenance) renders in-toto statements and SLSA
provenance (v1 and v0.2), and the [**DSSE debugger**](/tools/dsse) shows an
envelope's payload and its exact PAE pre-authentication encoding — the bytes
that actually get signed, and the thing to compare when two implementations
disagree.

The [**Composer attestation checker**](/tools/composer-attestations) closes the
loop: type any `vendor/package` and it downloads the exact dist zip Composer
would install, hashes it, and verifies GitHub's build attestation for that
digest — the same check [`k2gl/composer-attest`](/packages/composer-attest) runs
at install time. Expect "no attestation" a lot: when we ran the 500 most popular
Packagist packages through it, none shipped one. Publishing provenance is
[one workflow line](/guides/attest-your-package); the ecosystem just hasn't
started yet.

## Identity formats

The [**SD-JWT debugger**](/tools/sd-jwt) takes an RFC 9901 token apart:
every disclosure with its recomputed digest (stray disclosures the issuer never
committed to get flagged), the recreated claim set, the key-binding JWT, and
signature verification against an issuer key. Its counterpart, the
[**generator**](/tools/sd-jwt-issue), issues a demo SD-JWT with the claims you
choose disclosable, signed by a throwaway key — generate in one tab, dissect in
the other. Context on why these formats matter now:
[digital identity for PHP](/identity).

## Two more

The [**SSH signature verifier**](/tools/sshsig) covers both OpenSSH modes for
SSHSIG blocks (`ssh-keygen -Y sign`, SSH-signed git commits): signature-only
integrity, or the full `allowed_signers` check with principal and namespace.

The [**PEM ⇄ JWK converter**](/tools/pem-jwk) is deliberately the odd one out:
it runs entirely in your browser via WebCrypto, because a tool people paste
*private keys* into has no business having a server side. RSA, EC, Ed25519,
both directions.

## How they're built

A small PHP API (FrankenPHP) behind the site runs the packages themselves;
verification failures come back as results, not errors — a failed check is
information. The pages are plain HTML forms with a little vanilla JS. If you'd
rather have the same capabilities in your own code, every tool page shows the
few lines of PHP that do the equivalent, with links to the packages.

Start with the [tools index](/tools) — or go straight to
[verifying your dependencies](/guides/verify-provenance-at-install).
