import type { APIRoute } from 'astro';
import { getCollection } from 'astro:content';

const esc = (s: string): string =>
  s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export const GET: APIRoute = async ({ site }) => {
  const base = (site ?? new URL('https://k2gl.com')).href.replace(/\/$/, '');
  const posts = (await getCollection('blog')).sort(
    (a, b) => b.data.date.getTime() - a.data.date.getTime(),
  );

  const items = posts.map((p) => `    <item>
      <title>${esc(p.data.title)}</title>
      <link>${base}/blog/${p.id}</link>
      <guid>${base}/blog/${p.id}</guid>
      <description>${esc(p.data.description)}</description>
      <pubDate>${p.data.date.toUTCString()}</pubDate>
    </item>`).join('\n');

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>k2gl blog</title>
    <link>${base}/blog</link>
    <description>Notes on PHP supply-chain security, Sigstore, and the k2gl packages.</description>
    <atom:link href="${base}/rss.xml" rel="self" type="application/rss+xml"/>
${items}
  </channel>
</rss>
`;

  return new Response(xml, { headers: { 'Content-Type': 'application/xml; charset=utf-8' } });
};
