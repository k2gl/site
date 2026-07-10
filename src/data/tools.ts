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
];
