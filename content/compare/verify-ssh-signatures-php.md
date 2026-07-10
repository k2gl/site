---
title: "Verify SSH signatures in PHP?"
description: "Yes — SSHSIG (ssh-keygen -Y, SSH-signed git commits) verifies in pure PHP with full allowed_signers semantics, and there's a browser tool to try it."
---

**Yes.** [`k2gl/sshsig`](/packages/sshsig) implements the OpenSSH
[SSHSIG](https://github.com/openssh/openssh-portable/blob/master/PROTOCOL.sshsig)
format — what `ssh-keygen -Y sign` produces and what git uses for SSH-signed
commits and tags — in pure PHP. Ed25519, ECDSA (P-256/384/521) and RSA.

## Both OpenSSH modes

- **`verify`** — against an `allowed_signers` list: principal match, namespace,
  key validity window. The full trust decision.
- **`check-novalidate`** — signature integrity only, when you just need "this key
  signed these bytes".

```php
use K2gl\Sshsig\AllowedSigners;
use K2gl\Sshsig\SshsigVerifier;

$verified = new SshsigVerifier()->verify(
    message: $bytes,
    armoredSignature: $armored,
    allowedSigners: AllowedSigners::fromFile('allowed_signers'),
    identity: 'alice@example.com',
    namespace: 'file',
);
```

Signing is covered too (`SshsigSigner`, OpenSSH and PKCS#8 keys).

## Try it without writing code

Paste a message and its `-----BEGIN SSH SIGNATURE-----` block into the
[SSH signature verifier](/tools/sshsig) — it runs on this package.

For Sigstore-backed signing with certificates and a transparency log instead,
see [the supply-chain overview](/supply-chain).
