// Human-authored enrichment layer — additive, lives in THIS repo, never touches
// the canonical package READMEs.

export type Family = 'supply-chain' | 'identity' | 'utilities';
export type Category = 'plugins' | 'sigstore' | 'attestation' | 'signatures' | 'identity' | 'utilities';

/** Cross-links from a package to the site surfaces that feature it. */
export interface RelatedLinks {
  /** Slug in content/guides. */
  guide?: string;
  /** Tool slug — /tools/<slug>. */
  tool?: string;
  /** Slugs in content/compare (a package can answer several questions). */
  compare?: string[];
}

export interface Enrichment {
  /** One liftable sentence — the SEO/LLM tagline. */
  tagline: string;
  category: Category;
  /** The pain this package solves, in a sentence. */
  hook: string;
  /** Reach for it when… (optional sidecar). */
  whenToUse?: string[];
  /** Look elsewhere when… (optional sidecar). */
  whenNotToUse?: string[];
  /** Guide / online tool / Q&A pages about this package. */
  related?: RelatedLinks;
}

/**
 * The three product lines — canonical order and labels for the home page,
 * /packages, the footer and llms.txt.
 */
export const FAMILIES: { key: Family; label: string; blurb: string; landing: string }[] = [
  {
    key: 'supply-chain',
    label: 'Supply-chain security',
    blurb: 'Verify and produce provenance: Sigstore in pure PHP, the attestation formats underneath, and Composer plugins that check dependencies at install.',
    landing: '/supply-chain',
  },
  {
    key: 'identity',
    label: 'Digital identity & credentials',
    blurb: 'SD-JWT and SD-JWT VC (RFC 9901) — the selective-disclosure credential formats behind OpenID4VC and the EU Digital Identity Wallet.',
    landing: '/identity',
  },
  {
    key: 'utilities',
    label: 'Developer utilities',
    blurb: 'Small, focused PHP libraries for everyday work.',
    landing: '/packages#utilities',
  },
];

/** Display order + labels for grouping packages by type. */
export const CATEGORIES: { key: Category; family: Family; label: string; short: string; blurb: string }[] = [
  { key: 'plugins', family: 'supply-chain', label: 'Composer plugins', short: 'Plugins', blurb: 'Plugins that check dependencies as Composer installs them.' },
  { key: 'sigstore', family: 'supply-chain', label: 'Sigstore & signing', short: 'Sigstore', blurb: 'Verify and produce Sigstore signatures and bundles in pure PHP.' },
  { key: 'attestation', family: 'supply-chain', label: 'Attestation formats', short: 'Attestation', blurb: 'The formats underneath — DSSE, in-toto, SLSA, TUF — as typed PHP.' },
  { key: 'signatures', family: 'supply-chain', label: 'Signatures & notes', short: 'Signatures', blurb: 'SSH signatures and signed-note formats used by transparency logs.' },
  { key: 'identity', family: 'identity', label: 'Digital identity & credentials', short: 'Identity', blurb: 'SD-JWT and SD-JWT VC: selective-disclosure credentials, issued and verified in PHP.' },
  { key: 'utilities', family: 'utilities', label: 'Developer utilities', short: 'Utilities', blurb: 'General-purpose PHP libraries.' },
];

export function familyOf(category: Category): Family {
  // Derived from CATEGORIES so a new category can never silently land in the
  // wrong product line.
  const entry = CATEGORIES.find((c) => c.key === category);

  if (!entry) throw new Error(`Unknown category: ${category}`);

  return entry.family;
}

export const ENRICHMENT: Record<string, Enrichment> = {
  // ── Composer plugins ─────────────────────────────────────────────────
  'composer-attest': {
    tagline: 'Composer plugin: verify GitHub build-provenance attestations at install time.',
    category: 'plugins',
    hook: 'Check that each package you install was really built by its repository’s CI — as Composer downloads it.',
    whenToUse: [
      'You want provenance checks on dependencies without a separate CI step.',
      'You install packages that publish GitHub build-provenance attestations.',
      'You want to fail an install when an attestation is present but invalid.',
    ],
    whenNotToUse: [
      'Your dependencies don’t attest their dist yet — you’ll mostly see "no attestation" (most of the ecosystem, today).',
      'You need signature verification of arbitrary blobs — use sigstore-verify directly.',
    ],
    related: {
      guide: 'verify-provenance-at-install',
      tool: 'composer-attestations',
      compare: ['verify-github-attestations-php'],
    },
  },
  'composer-license-gate': {
    tagline: 'Composer plugin: gate dependency licenses against an allow/deny policy.',
    category: 'plugins',
    hook: 'Fail the install when a disallowed license (say GPL in a proprietary product) slips in transitively.',
    whenToUse: [
      'You need to enforce a license policy on the whole dependency tree, at install.',
      'You want the check to fail the build, not just print a report.',
    ],
    whenNotToUse: [
      'You only want a one-off report — a CLI license checker run in CI may be simpler.',
      'You’re checking provenance, not licenses — use composer-attest.',
    ],
    related: {
      guide: 'gate-dependency-licenses',
    },
  },

  // ── Sigstore & signing ───────────────────────────────────────────────
  'sigstore-verify': {
    tagline: 'Verify Sigstore signatures, certificates, and transparency-log inclusion in pure PHP.',
    category: 'sigstore',
    hook: 'Check the provenance of artifacts and GitHub build attestations from PHP — no cosign, no shelling out.',
    whenToUse: [
      'You need Sigstore verification inside a PHP process, not via the cosign CLI.',
      'You verify GitHub attestations, DSSE bundles, or signed artifacts.',
      'You want conformance-tested verification you can rely on.',
    ],
    whenNotToUse: [
      'You only need to verify dependencies at install — use composer-attest, which builds on this.',
      'You’re not in PHP — cosign or the other language clients may fit better.',
    ],
    related: {
      guide: 'sign-and-verify-a-blob',
      tool: 'sigstore-bundle',
      compare: ['php-sigstore-client', 'cosign-alternative-php'],
    },
  },
  'sigstore-sign': {
    tagline: 'Produce Sigstore signatures and bundles from PHP — keyful or keyless (Fulcio/OIDC).',
    category: 'sigstore',
    hook: 'Sign artifacts and attestations the Sigstore way, then hand off a bundle any verifier accepts.',
    whenToUse: [
      'You need to produce Sigstore bundles from PHP, keyless (Fulcio + OIDC) or with your own key.',
      'You’re building a signing step into a PHP tool or release pipeline.',
    ],
    whenNotToUse: [
      'You only need to verify — use sigstore-verify.',
      'You’re signing release artifacts in GitHub Actions — composer-attest-action wraps this.',
    ],
    related: {
      guide: 'sign-and-verify-a-blob',
      tool: 'sigstore-bundle',
    },
  },
  'sigstore-bundle': {
    tagline: 'Build and read Sigstore bundles (.sigstore.json) in PHP.',
    category: 'sigstore',
    hook: 'Assemble the DSSE/message-signature bundle that carries a signature, its cert, and its log entry.',
    whenToUse: [
      'You’re assembling or parsing a .sigstore.json bundle at a low level.',
      'You’re building tooling on top of the bundle format.',
    ],
    whenNotToUse: [
      'You want the full sign or verify flow — sigstore-sign / sigstore-verify use this for you.',
    ],
    related: {
      tool: 'sigstore-bundle',
    },
  },
  'rekor-client': {
    tagline: 'A PSR-18 client for the Rekor transparency log (v2 / rekor-tiles).',
    category: 'sigstore',
    hook: 'Submit and retrieve transparency-log entries with the HTTP client you already use.',
    whenToUse: [
      'You need to submit to or query a Rekor v2 transparency log directly.',
      'You want to bring your own PSR-18 HTTP client.',
    ],
    whenNotToUse: [
      'You want the full signing flow — sigstore-sign submits to Rekor for you.',
      'You need the legacy Rekor v1 REST API (this targets v2).',
    ],
    related: {
      tool: 'sigstore-bundle',
    },
  },

  // ── Attestation formats ──────────────────────────────────────────────
  'dsse': {
    tagline: 'Sign and verify DSSE envelopes (Dead Simple Signing Envelope) in PHP.',
    category: 'attestation',
    hook: 'The signing envelope in-toto and Sigstore are built on — PAE encoding and ECDSA/Ed25519 signers included.',
    whenToUse: [
      'You need to sign or verify a DSSE envelope directly (PAE, ECDSA/Ed25519).',
      'You’re implementing in-toto or a Sigstore-adjacent format.',
    ],
    whenNotToUse: [
      'You want a full Sigstore bundle with cert + log entry — sigstore-sign / sigstore-verify build on this.',
    ],
    related: {
      tool: 'dsse',
      compare: ['dsse-php'],
    },
  },
  'in-toto-attestation': {
    tagline: 'Build and parse in-toto attestation Statements in PHP.',
    category: 'attestation',
    hook: 'Wrap any predicate (SLSA provenance, SBOM, …) in the in-toto Statement format tools expect.',
    whenToUse: [
      'You’re producing or reading an in-toto Statement around a predicate.',
      'You need the subject/predicate structure tools like cosign expect.',
    ],
    whenNotToUse: [
      'You want to sign the statement — wrap it in DSSE (dsse) or a Sigstore bundle.',
    ],
    related: {
      guide: 'in-toto-to-slsa-bundle',
      tool: 'provenance',
      compare: ['in-toto-php'],
    },
  },
  'slsa-provenance': {
    tagline: 'Model SLSA provenance predicates in PHP.',
    category: 'attestation',
    hook: 'Describe how an artifact was built — builder, invocation, materials — in the SLSA v1 shape.',
    whenToUse: [
      'You’re producing a SLSA provenance predicate for a build.',
      'You need the typed SLSA v1 structure rather than hand-built arrays.',
    ],
    whenNotToUse: [
      'You need the Statement wrapper around the predicate — use in-toto-attestation.',
    ],
    related: {
      guide: 'in-toto-to-slsa-bundle',
      tool: 'provenance',
      compare: ['slsa-provenance-php'],
    },
  },
  'tuf': {
    tagline: 'A pure-PHP client for The Update Framework (TUF).',
    category: 'attestation',
    hook: 'Resolve and verify TUF metadata so you can trust what a repository claims to distribute.',
    whenToUse: [
      'You need to resolve and verify TUF metadata (root, targets, snapshot, timestamp).',
      'You’re building a client that trusts a TUF-secured repository.',
    ],
    whenNotToUse: [
      'You only want Sigstore’s trust root — sigstore-verify pulls it via TUF for you.',
    ],
    related: {
      compare: ['tuf-client-php'],
    },
  },

  // ── Signatures & notes ───────────────────────────────────────────────
  'sshsig': {
    tagline: 'Sign and verify with the SSH signature format (SSHSIG) in PHP.',
    category: 'signatures',
    hook: 'Use the same SSHSIG format as `ssh-keygen -Y` to sign and verify blobs from PHP.',
    whenToUse: [
      'You sign or verify with SSH keys in the SSHSIG format (ssh-keygen -Y compatible).',
      'You want signing that interoperates with existing SSH key infrastructure.',
    ],
    whenNotToUse: [
      'You want Sigstore/transparency-log-backed signing — use sigstore-sign / sigstore-verify.',
    ],
    related: {
      tool: 'sshsig',
      compare: ['verify-ssh-signatures-php'],
    },
  },
  'signed-note': {
    tagline: 'Read and write signed notes (Go sumdb / Rekor checkpoint format).',
    category: 'signatures',
    hook: 'Parse and verify the note-with-signatures format transparency logs use for checkpoints.',
    whenToUse: [
      'You parse or verify signed notes (Go checksum database, Rekor checkpoints).',
      'You’re working with transparency-log checkpoints directly.',
    ],
    whenNotToUse: [
      'You want a general Sigstore bundle — use sigstore-bundle.',
    ],
  },

  // ── Digital identity & credentials ───────────────────────────────────
  'sd-jwt': {
    tagline: 'Selective Disclosure for JWTs (RFC 9901): issue, present, verify.',
    category: 'identity',
    hook: 'Issue and verify SD-JWTs — the credential format behind OpenID4VC and the EU Digital Identity Wallet.',
    whenToUse: [
      'You issue credentials where the holder decides which claims to reveal (SD-JWT, RFC 9901).',
      'You verify SD-JWT presentations, with or without Key Binding, e.g. as an EUDI relying party.',
    ],
    whenNotToUse: [
      'You want plain JWTs with all claims visible — any JWT library covers that.',
    ],
    related: {
      tool: 'sd-jwt',
      compare: ['sd-jwt-php'],
    },
  },
  'sd-jwt-vc': {
    tagline: 'SD-JWT Verifiable Credentials (dc+sd-jwt): issue and verify.',
    category: 'identity',
    hook: 'The credential layer over SD-JWT — vct rules and issuer key discovery for EUDI-style relying parties.',
    whenToUse: [
      'You verify dc+sd-jwt credentials as a relying party (issuer metadata, x5c, or pinned keys).',
      'You issue SD-JWT VCs and want the vct/protected-claims rules enforced.',
    ],
    whenNotToUse: [
      'You need the raw SD-JWT format without the credential rules — use sd-jwt directly.',
    ],
    related: {
      guide: 'verify-sd-jwt-vc-presentation',
      tool: 'sd-jwt',
      compare: ['eudi-relying-party-php'],
    },
  },

  // ── Developer utilities ──────────────────────────────────────────────
  'array-reader': {
    tagline: 'Read nested array data with types, defaults, and clear errors.',
    category: 'utilities',
    hook: 'Stop writing isset() ladders over decoded JSON and config arrays — pull typed values by path.',
    whenToUse: [
      'You read decoded JSON or config arrays and want typed access with defaults.',
      'You want a clear error when a path is missing or the wrong type.',
    ],
    whenNotToUse: [
      'You want full schema validation — reach for a validator.',
      'You’d rather map into typed objects — use a serializer/DTO layer.',
    ],
    related: {
      guide: 'typed-array-access',
    },
  },
  'enum': {
    tagline: 'Ergonomic helpers for PHP native enums — labels, values, and lookups.',
    category: 'utilities',
    hook: 'Give your enums human labels and value maps without hand-rolling the same boilerplate each time.',
    whenToUse: [
      'You want labels, value maps, and from-label lookups on native enums.',
      'You’re repeating the same enum boilerplate across cases.',
    ],
    whenNotToUse: [
      'Plain native enums already cover your needs.',
    ],
  },
  'entity-exist': {
    tagline: 'A Symfony validator constraint that asserts an entity exists.',
    category: 'utilities',
    hook: 'Validate that an id (or composite key) really points at a row, right in your constraint set.',
    whenToUse: [
      'You use the Symfony Validator and need to assert a row exists by id or composite key.',
    ],
    whenNotToUse: [
      'You’re not using the Symfony Validator.',
      'The lookup needs custom query logic — a bespoke constraint may fit better.',
    ],
  },
  'phpunit-fluent-assertions': {
    tagline: 'Fluent, readable assertions for PHPUnit.',
    category: 'utilities',
    hook: 'Write fact($value)->is(...) chains instead of remembering assertEquals argument order.',
    whenToUse: [
      'You want more readable, chainable assertions in PHPUnit tests.',
    ],
    whenNotToUse: [
      'You prefer PHPUnit’s native assertions, or you’re not on PHPUnit.',
    ],
  },
  'app-env': {
    tagline: 'A small, typed helper for reading application environment.',
    category: 'utilities',
    hook: 'Read and branch on the app environment without scattering getenv() and string checks.',
    whenToUse: [
      'You branch on the application environment and want it typed in one place.',
    ],
    whenNotToUse: [
      'You need a full configuration system — use a config component.',
    ],
  },
};

export const FALLBACK: Enrichment = {
  tagline: '',
  category: 'utilities',
  hook: '',
};
