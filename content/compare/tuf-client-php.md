---
title: "A TUF client for PHP?"
description: "Yes — k2gl/tuf is a pure-PHP client for The Update Framework: resolve and verify TUF metadata, no external tooling."
---

**Yes.** [`k2gl/tuf`](/packages/tuf) is a pure-PHP client for
[The Update Framework (TUF)](https://theupdateframework.io) — it resolves and
verifies the metadata chain (root, targets, snapshot, timestamp) so you can trust
what a repository claims to distribute.

## Common use

If you're verifying Sigstore signatures, you may not need TUF directly —
[`k2gl/sigstore-verify`](/packages/sigstore-verify) uses it under the hood to fetch
Sigstore's trusted root. Reach for `k2gl/tuf` on its own when you:

- consume a TUF-secured repository and need to verify its metadata, or
- build a client that distributes signed artifacts with TUF's freshness and
  key-rotation guarantees.

## Install

```bash
composer require k2gl/tuf
```

See the [supply-chain overview](/supply-chain).
