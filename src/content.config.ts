import { defineCollection, z } from 'astro:content';
import { packagesLoader } from './loaders/packages';

// Vertical slice: the hard triad — sigstore-verify (complex deps),
// array-reader (worst-case example heuristic), enum (deviant README headings).
export const SLICE = ['sigstore-verify', 'array-reader', 'enum'];

const packages = defineCollection({
  loader: packagesLoader(SLICE),
  schema: z.object({
    slug: z.string(),
    name: z.string(),
    description: z.string(),
    tagline: z.string(),
    family: z.enum(['supply-chain', 'utilities']),
    hook: z.string(),
    keywords: z.array(z.string()),
    php: z.string(),
    requires: z.array(z.object({ name: z.string(), constraint: z.string() })),
    install: z.string(),
    readme: z.string(),
    links: z.object({ github: z.string().url(), packagist: z.string().url() }),
  }),
});

export const collections = { packages };
