import type { APIRoute, GetStaticPaths } from 'astro';
import { getCollection } from 'astro:content';

// Clean markdown twin of each package page, for agents/LLMs (append-.md per
// llmstxt.org). Served noindex (Caddy adds X-Robots-Tag in production) and kept
// out of the sitemap, but linked from llms.txt and rel=alternate.

export const getStaticPaths: GetStaticPaths = async () => {
  const pkgs = await getCollection('packages');
  return pkgs.map((p) => ({ params: { name: p.data.slug }, props: { pkg: p.data } }));
};

export const GET: APIRoute = ({ props }) => {
  const pkg = props.pkg;

  const requirements = [
    pkg.php ? `- PHP ${pkg.php}` : null,
    ...pkg.requires.map((r: { name: string; constraint: string }) => `- ${r.name} ${r.constraint}`),
  ].filter(Boolean).join('\n');

  const apiSection = pkg.api.length > 0
    ? '\n## API\n\n' + pkg.api.map((cls: { name: string; kind: string; methods: string[] }) =>
        `### ${cls.name} (${cls.kind})\n\n` +
        (cls.methods.length > 0 ? cls.methods.map((m) => `- \`${m}\``).join('\n') : '_no public methods_'),
      ).join('\n\n') + '\n'
    : '';

  const md = `# ${pkg.name}

> ${pkg.tagline}

${pkg.hook}

## Install

\`\`\`bash
${pkg.install}
\`\`\`

## Requirements

${requirements || '- none declared'}

## Documentation

${pkg.readme.trim()}
${apiSection}
## Links

- GitHub: ${pkg.links.github}
- Packagist: ${pkg.links.packagist}
`;

  return new Response(md, {
    headers: {
      'Content-Type': 'text/markdown; charset=utf-8',
      'X-Robots-Tag': 'noindex',
    },
  });
};
