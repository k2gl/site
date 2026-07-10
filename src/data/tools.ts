// The online tools — single source for /tools, the tool switcher, the home
// strip and the related-links resolver on package pages.

export interface Tool {
  /** Route: /tools/<slug>. */
  slug: string;
  name: string;
  /** Switcher label. */
  short: string;
  blurb: string;
  live: boolean;
}

export const TOOLS: Tool[] = [
  {
    slug: 'sigstore-bundle',
    name: 'Sigstore bundle inspector',
    short: 'Sigstore bundle',
    blurb: 'Decode a .sigstore.json bundle — certificate, Fulcio extensions, transparency log — and verify it for real.',
    live: true,
  },
  {
    slug: 'dsse',
    name: 'DSSE envelope debugger',
    short: 'DSSE',
    blurb: 'Decode a DSSE envelope, preview its PAE, and check a signature against your public key.',
    live: true,
  },
  {
    slug: 'sd-jwt',
    name: 'SD-JWT debugger',
    short: 'SD-JWT',
    blurb: 'Decode an SD-JWT or SD-JWT VC: disclosures, recomputed digests, recreated claims, signature.',
    live: true,
  },
  {
    slug: 'composer-attestations',
    name: 'Composer attestation checker',
    short: 'Attestations',
    blurb: 'Does a Packagist package publish verifiable build provenance? Check any vendor/package.',
    live: true,
  },
  {
    slug: 'sshsig',
    name: 'SSH signature verifier',
    short: 'SSHSIG',
    blurb: 'Verify an SSHSIG signature the ssh-keygen -Y way — with or without an allowed_signers list.',
    live: true,
  },
  {
    slug: 'sd-jwt-issue',
    name: 'SD-JWT generator',
    short: 'SD-JWT issue',
    blurb: 'Issue a demo SD-JWT with the claims you choose disclosable, signed by a throwaway key.',
    live: true,
  },
  {
    slug: 'provenance',
    name: 'Provenance viewer',
    short: 'Provenance',
    blurb: 'Render an in-toto statement or SLSA provenance — subjects, builder, dependencies — from JSON or a DSSE envelope.',
    live: true,
  },
  {
    slug: 'pem-jwk',
    name: 'PEM ⇄ JWK converter',
    short: 'PEM⇄JWK',
    blurb: 'Convert keys between PEM and JWK entirely in your browser — nothing is uploaded.',
    live: true,
  },
];
