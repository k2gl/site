import type { APIRoute, GetStaticPaths } from 'astro';
import { getCollection } from 'astro:content';

// Per-package structured feed — the shared substrate exposed verbatim. The .md
// twin, the JSON-LD and the agent eval all read the same facts, so nothing drifts.

export const getStaticPaths: GetStaticPaths = async () => {
  const pkgs = await getCollection('packages');
  return pkgs.map((p) => ({ params: { name: p.data.slug }, props: { pkg: p.data } }));
};

export const GET: APIRoute = ({ props }) => {
  const pkg = props.pkg;

  const feed = {
    name: pkg.name,
    slug: pkg.slug,
    tagline: pkg.tagline,
    family: pkg.family,
    description: pkg.description,
    php: pkg.php,
    requires: pkg.requires,
    install: pkg.install,
    keywords: pkg.keywords,
    api: pkg.api,
    links: pkg.links,
  };

  return new Response(JSON.stringify(feed, null, 2), {
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      'X-Robots-Tag': 'noindex',
    },
  });
};
