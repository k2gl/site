import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';
import { packagesLoader } from './loaders/packages';

// All 17 published Composer packages (composer-attest-action is a GitHub Action,
// not a Composer package, so it is not in the catalog).
export const PACKAGES = [
  // supply-chain
  'sigstore-verify', 'sigstore-sign', 'sigstore-bundle', 'rekor-client',
  'dsse', 'in-toto-attestation', 'slsa-provenance', 'tuf',
  'sshsig', 'signed-note', 'composer-attest', 'composer-license-gate',
  // utilities
  'array-reader', 'enum', 'entity-exist', 'phpunit-fluent-assertions', 'app-env',
];

const packages = defineCollection({
  loader: packagesLoader(PACKAGES),
  schema: z.object({
    slug: z.string(),
    name: z.string(),
    description: z.string(),
    tagline: z.string(),
    family: z.enum(['supply-chain', 'utilities']),
    category: z.enum(['plugins', 'sigstore', 'attestation', 'signatures', 'utilities']),
    hook: z.string(),
    keywords: z.array(z.string()),
    php: z.string(),
    requires: z.array(z.object({ name: z.string(), constraint: z.string() })),
    install: z.string(),
    api: z.array(z.object({
      name: z.string(),
      kind: z.string(),
      methods: z.array(z.string()),
    })),
    readme: z.string(),
    links: z.object({ github: z.string().url(), packagist: z.string().url() }),
  }),
});

const blog = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './content/blog' }),
  schema: z.object({
    title: z.string(),
    description: z.string(),
    date: z.coerce.date(),
  }),
});

const compare = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './content/compare' }),
  schema: z.object({
    title: z.string(),
    description: z.string(),
  }),
});

const guides = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './content/guides' }),
  schema: z.object({
    title: z.string(),
    description: z.string(),
    order: z.number().default(100),
  }),
});

export const collections = { packages, blog, compare, guides };
