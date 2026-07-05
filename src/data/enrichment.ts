// Human-authored enrichment layer — additive, lives in THIS repo, never touches
// the canonical package READMEs. A typed map for now; scales to per-package
// markdown sidecars (content/packages/{slug}.md) later.

export type Family = 'supply-chain' | 'utilities';

export interface Enrichment {
  /** One liftable sentence — the SEO/LLM tagline. */
  tagline: string;
  family: Family;
  /** The pain this package solves, in a sentence. */
  hook: string;
}

export const ENRICHMENT: Record<string, Enrichment> = {
  // ── Supply-chain family ──────────────────────────────────────────────
  'sigstore-verify': {
    tagline: 'Verify Sigstore signatures, certificates, and transparency-log inclusion in pure PHP.',
    family: 'supply-chain',
    hook: 'Check the provenance of artifacts and GitHub build attestations from PHP — no cosign, no shelling out.',
  },
  'sigstore-sign': {
    tagline: 'Produce Sigstore signatures and bundles from PHP — keyful or keyless (Fulcio/OIDC).',
    family: 'supply-chain',
    hook: 'Sign artifacts and attestations the Sigstore way, then hand off a bundle any verifier accepts.',
  },
  'sigstore-bundle': {
    tagline: 'Build and read Sigstore bundles (.sigstore.json) in PHP.',
    family: 'supply-chain',
    hook: 'Assemble the DSSE/message-signature bundle that carries a signature, its cert, and its log entry.',
  },
  'rekor-client': {
    tagline: 'A PSR-18 client for the Rekor transparency log (v2 / rekor-tiles).',
    family: 'supply-chain',
    hook: 'Submit and retrieve transparency-log entries with the HTTP client you already use.',
  },
  'dsse': {
    tagline: 'Sign and verify DSSE envelopes (Dead Simple Signing Envelope) in PHP.',
    family: 'supply-chain',
    hook: 'The signing envelope in-toto and Sigstore are built on — PAE encoding and ECDSA/Ed25519 signers included.',
  },
  'in-toto-attestation': {
    tagline: 'Build and parse in-toto attestation Statements in PHP.',
    family: 'supply-chain',
    hook: 'Wrap any predicate (SLSA provenance, SBOM, …) in the in-toto Statement format tools expect.',
  },
  'slsa-provenance': {
    tagline: 'Model SLSA provenance predicates in PHP.',
    family: 'supply-chain',
    hook: 'Describe how an artifact was built — builder, invocation, materials — in the SLSA v1 shape.',
  },
  'tuf': {
    tagline: 'A pure-PHP client for The Update Framework (TUF).',
    family: 'supply-chain',
    hook: 'Resolve and verify TUF metadata so you can trust what a repository claims to distribute.',
  },
  'sshsig': {
    tagline: 'Sign and verify with the SSH signature format (SSHSIG) in PHP.',
    family: 'supply-chain',
    hook: 'Use the same SSHSIG format as `ssh-keygen -Y` to sign and verify blobs from PHP.',
  },
  'signed-note': {
    tagline: 'Read and write signed notes (Go sumdb / Rekor checkpoint format).',
    family: 'supply-chain',
    hook: 'Parse and verify the note-with-signatures format transparency logs use for checkpoints.',
  },
  'composer-attest': {
    tagline: 'Composer plugin: verify GitHub build-provenance attestations at install time.',
    family: 'supply-chain',
    hook: 'Check that each package you install was really built by its repository’s CI — as Composer downloads it.',
  },
  'composer-license-gate': {
    tagline: 'Composer plugin: gate dependency licenses against an allow/deny policy.',
    family: 'supply-chain',
    hook: 'Fail the install when a disallowed license (say GPL in a proprietary product) slips in transitively.',
  },

  // ── Utility family ───────────────────────────────────────────────────
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
  'entity-exist': {
    tagline: 'A Symfony validator constraint that asserts an entity exists.',
    family: 'utilities',
    hook: 'Validate that an id (or composite key) really points at a row, right in your constraint set.',
  },
  'phpunit-fluent-assertions': {
    tagline: 'Fluent, readable assertions for PHPUnit.',
    family: 'utilities',
    hook: 'Write fact($value)->is(...) chains instead of remembering assertEquals argument order.',
  },
  'app-env': {
    tagline: 'A small, typed helper for reading application environment.',
    family: 'utilities',
    hook: 'Read and branch on the app environment without scattering getenv() and string checks.',
  },
};

export const FALLBACK: Enrichment = {
  tagline: '',
  family: 'utilities',
  hook: '',
};
