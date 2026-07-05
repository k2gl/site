// @ts-check
import { defineConfig } from 'astro/config';
import mdx from '@astrojs/mdx';
import sitemap from '@astrojs/sitemap';

// Single canonical domain (no subdomains): SEO weight stays consolidated.
export default defineConfig({
  site: 'https://k2gl.com',
  integrations: [
    mdx(),
    // The machine .md/.json/llms.txt endpoints are excluded from the sitemap so
    // they don't cannibalize the canonical HTML pages.
    sitemap({
      filter: (page) => !page.endsWith('.md') && !page.endsWith('.json'),
    }),
  ],
});
