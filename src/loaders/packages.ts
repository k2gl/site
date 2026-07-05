import type { Loader, LoaderContext } from 'astro/loaders';
import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { ENRICHMENT, FALLBACK, familyOf } from '../data/enrichment';

/**
 * The single content substrate. Each package page, its .md twin, its .json feed,
 * llms.txt and the JSON-LD are all projections of THIS object — so human docs and
 * machine docs cannot drift.
 *
 * Source of truth: the package's own README.md (prose, verbatim) + composer.json
 * (all structured metadata). Read from the local sibling clone when present
 * (hermetic, network-free), else fetched from raw.githubusercontent (the CI path).
 */
export function packagesLoader(slugs: string[]): Loader {
  return {
    name: 'k2gl-packages',
    async load({ store, parseData, logger }: LoaderContext): Promise<void> {
      store.clear();
      for (const slug of slugs) {
        const [readme, composerRaw] = await Promise.all([
          readSource(slug, 'README.md'),
          readSource(slug, 'composer.json'),
        ]);
        const composer = JSON.parse(composerRaw) as ComposerJson;
        const enrichment = ENRICHMENT[slug] ?? FALLBACK;

        const requires = Object.entries(composer.require ?? {})
          .filter(([name]) => name.startsWith('k2gl/'))
          .map(([name, constraint]) => ({ name, constraint: String(constraint) }));

        const data = {
          slug,
          name: composer.name,
          description: composer.description ?? '',
          tagline: enrichment.tagline || (composer.description ?? ''),
          family: familyOf(enrichment.category),
          category: enrichment.category,
          hook: enrichment.hook,
          keywords: composer.keywords ?? [],
          php: String(composer.require?.php ?? ''),
          requires,
          install: `composer require ${composer.name}`,
          readme,
          links: {
            github: `https://github.com/${composer.name}`,
            packagist: `https://packagist.org/packages/${composer.name}`,
          },
        };

        store.set({ id: slug, data: await parseData({ id: slug, data }) });
        logger.info(`substrate: ${slug}`);
      }
    },
  };
}

interface ComposerJson {
  name: string;
  description?: string;
  keywords?: string[];
  require?: Record<string, string>;
}

async function readSource(slug: string, file: string): Promise<string> {
  const local = resolve(process.cwd(), '..', slug, file);
  try {
    return await readFile(local, 'utf-8');
  } catch {
    const url = `https://raw.githubusercontent.com/k2gl/${slug}/main/${file}`;
    const res = await fetch(url);
    if (!res.ok) {
      throw new Error(`Could not read ${file} for ${slug}: local miss and ${url} -> ${res.status}`);
    }
    return await res.text();
  }
}
