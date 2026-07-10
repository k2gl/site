import type { APIRoute } from 'astro';
import { getCollection } from 'astro:content';
import { FAMILIES } from '../data/enrichment';

// llmstxt.org index: a curated map an LLM/agent reads instead of crawling HTML.
// Every link points at the .md endpoint, with the tagline as its description.

export const GET: APIRoute = async ({ site }) => {
  const pkgs = (await getCollection('packages')).map((p) => p.data);
  const base = site ?? new URL('https://k2gl.com');

  const line = (p: (typeof pkgs)[number]) =>
    `- [${p.name}](${new URL(`/packages/${p.slug}.md`, base).href}): ${p.tagline}`;

  // Sections come from the canonical product lines, so a package can never
  // silently fall outside every section.
  const sections = FAMILIES.map(
    (f) => `## ${f.label} packages

${pkgs.filter((p) => p.family === f.key).map(line).join('\n')}`,
  ).join('\n\n');

  const txt = `# k2gl

> Open-source PHP packages for software supply-chain security (Sigstore, in-toto,
> SLSA, TUF), digital identity credentials (SD-JWT, RFC 9901), and everyday
> developer ergonomics. Each package below links to a clean markdown page with
> install, requirements, and usage.

${sections}

## Optional

- [Full docs, one file](${new URL('/llms-full.txt', base).href}): every package's docs concatenated for one-shot ingestion.
`;

  return new Response(txt, {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
};
