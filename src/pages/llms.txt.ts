import type { APIRoute } from 'astro';
import { getCollection } from 'astro:content';

// llmstxt.org index: a curated map an LLM/agent reads instead of crawling HTML.
// Every link points at the .md endpoint, with the tagline as its description.

export const GET: APIRoute = async ({ site }) => {
  const pkgs = (await getCollection('packages')).map((p) => p.data);
  const base = site ?? new URL('https://k2gl.com');

  const line = (p: (typeof pkgs)[number]) =>
    `- [${p.name}](${new URL(`/packages/${p.slug}.md`, base).href}): ${p.tagline}`;

  const family = (f: string) =>
    pkgs.filter((p) => p.family === f).map(line).join('\n');

  const txt = `# k2gl

> Open-source PHP packages for software supply-chain security (Sigstore, in-toto,
> SLSA, TUF) and everyday developer ergonomics. Each package below links to a
> clean markdown page with install, requirements, and usage.

## Supply-chain packages

${family('supply-chain')}

## Utility packages

${family('utilities')}

## Optional

- [Full docs, one file](${new URL('/llms-full.txt', base).href}): every package's docs concatenated for one-shot ingestion.
`;

  return new Response(txt, {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
};
