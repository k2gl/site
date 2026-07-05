import type { APIRoute } from 'astro';
import { getCollection } from 'astro:content';

// Every package's docs concatenated for one-shot LLM ingestion.
export const GET: APIRoute = async () => {
  const pkgs = (await getCollection('packages')).map((p) => p.data);

  const section = (p: (typeof pkgs)[number]) => `# ${p.name}

> ${p.tagline}

${p.hook}

## Install

\`\`\`bash
${p.install}
\`\`\`

${p.readme.trim()}`;

  const txt = `# k2gl — full documentation

${pkgs.map(section).join('\n\n---\n\n')}\n`;

  return new Response(txt, {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
};
