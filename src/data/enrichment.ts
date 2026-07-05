// Human-authored enrichment layer — additive, lives in THIS repo, never touches
// the canonical package READMEs. For the vertical slice this is a typed map; it
// scales to per-package markdown sidecars (content/packages/{slug}.md) later.

export type Family = 'supply-chain' | 'utilities';

export interface Enrichment {
  /** One liftable sentence — the SEO/LLM tagline. */
  tagline: string;
  family: Family;
  /** The pain this package solves, in a sentence. */
  hook: string;
}

export const ENRICHMENT: Record<string, Enrichment> = {
  'sigstore-verify': {
    tagline: 'Verify Sigstore signatures, certificates, and transparency-log inclusion in pure PHP.',
    family: 'supply-chain',
    hook: 'Check the provenance of artifacts and GitHub build attestations from PHP — no cosign, no shelling out.',
  },
  'array-reader': {
    tagline: 'Read nested array data with types, defaults, and clear errors.',
    family: 'utilities',
    hook: 'Stop writing isset() ladders over decoded JSON and config arrays — pull typed values by path.',
  },
  'enum': {
    tagline: 'Ergonomic helpers for PHP native enums — labels, values, and lookups.',
    family: 'utilities',
    hook: 'Give your enums human labels and value maps without hand-rolling the same boilerplate each time.',
  },
};

export const FALLBACK: Enrichment = {
  tagline: '',
  family: 'utilities',
  hook: '',
};
