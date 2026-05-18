# Visual evidence

This directory holds **visual evidence** — screenshots of real rendered pages,
organised so they can be cited against the work item a branch is delivering.

The development loop is otherwise text-only: diffs, test counts, CI status. It
never shows what the screen actually renders. Visual evidence closes that gap.

## A language-neutral contract

Visual evidence is a Growth capability for **any tracked project**, whatever its
stack. Growth does not care *how* the screenshots are produced — only that a
project follows the contract:

1. The project's browser tests write full-page PNGs to `docs/evidence/<slug>/`.
2. CI uploads that directory as a build artifact.
3. growth-sync ingests it — posting the per-PR gallery and citing it on the
   matched work item.

A Laravel project produces the PNGs with the Pest browser suite (below); a Node
project would use Playwright or Cypress, a Python project pytest-playwright, a
Go project chromedp, an Elixir project Wallaby. The capture tooling is the
project's own. The folder layout and the growth-sync ingestion are the shared,
language-neutral part.

## Layout

```
docs/evidence/<work-item>/   one folder per work item, named by its slug
docs/evidence/<branch>/      fallback when a branch resolves to no work item
```

Each folder holds full-page PNG screenshots, one per captured state.

## Status

This is the **foundation slice** ([#243](https://github.com/datashaman/growth/issues/243)).
It establishes:

- the Pest browser suite (`tests/Browser/`, driven by Playwright) — *this*
  repo's capture tooling, since this repo is a Laravel app;
- its own CI job that runs the suite;
- this folder convention.

The **ingestion** half — growth-sync resolving branch → work item, posting the
idempotent per-PR screenshot gallery, and citing it on the work item — is the
follow-up slice ([#253](https://github.com/datashaman/growth/issues/253)), and
is where the language-neutral product capability lives. Until it lands, this
directory stays empty by design.

## Browser tests

Browser tests live in `tests/Browser/` and are a separate PHPUnit test suite.
They are **excluded from the default run** (`php artisan test`) because they
need Playwright and a browser binary. Run them explicitly:

```bash
php artisan test --testsuite=Browser
```

One-time local setup:

```bash
npm install
npx playwright install
```

Screenshots written during a run land in `tests/Browser/Screenshots/`, which is
gitignored — once #253 lands, this repo's capture will route curated captures
here under `docs/evidence/<work-item>/` instead.
