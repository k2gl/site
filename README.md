# k2gl.com

The documentation + blog site for the [k2gl](https://github.com/k2gl) family of
open-source PHP packages. Single domain, SEO-consolidated, and built to be read by
humans, search engines, and AI agents alike.

## How it works

Every package page is a projection of **one content substrate** per package:

- **README.md** (prose, synced verbatim — the package repo stays the source of truth)
- **composer.json** (structured metadata: install, requirements, dependency graph)
- human enrichment (tagline, family, hook) that lives here, never in the package repos

From that single substrate the build emits, for each package, all of:

| Route | For |
|-------|-----|
| `/packages/{name}` | the canonical HTML page (humans, search) |
| `/packages/{name}.md` | a clean markdown twin (agents/LLMs, `noindex`) |
| `/packages/{name}.json` | the structured feed |
| `/llms.txt`, `/llms-full.txt` | the [llmstxt.org](https://llmstxt.org) agent index |

Because they all read the same substrate, human docs and machine docs cannot drift.

## Develop

```bash
pnpm install
pnpm dev      # http://localhost:4321
pnpm build    # astro build + pagefind -> dist/
```

The content loader reads local sibling clones (`../{package}/README.md`) when
present, and falls back to `raw.githubusercontent.com` — so it builds hermetically
in CI without checking out the package repos.

## Stack

Astro 5 (static) · Pagefind search · Caddy 2 (serving) · portable Docker image.
No vendor lock-in — the image deploys to any VPS.

## Status

Vertical slice: `sigstore-verify`, `array-reader`, `enum`. Scaling to all packages.
