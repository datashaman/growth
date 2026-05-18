# Visual evidence

This directory holds **visual evidence** — screenshots of real rendered pages,
captured by the browser test suite — organised so they can be cited against the
work item a branch is delivering.

The development loop is otherwise text-only: diffs, test counts, CI status. It
never shows what the screen actually renders. Visual evidence closes that gap.

## Layout

```
docs/evidence/<work-item>/   one folder per work item, named by its slug
docs/evidence/<branch>/      fallback when a branch resolves to no work item
```

Each folder holds full-page PNG screenshots, one per captured state.

## Status

This is the **foundation slice** ([#243](https://github.com/datashaman/growth/issues/243)).
It establishes:

- the Pest browser suite (`tests/Browser/`, driven by Playwright);
- its own CI job that runs the suite;
- this folder convention.

The capture machinery that *populates* these folders — a `captureState()`
helper, branch → work-item resolution, the idempotent per-PR screenshot
gallery, and growth-sync citing the gallery on the work item — is the
follow-up slice. Until it lands, this directory stays empty by design.

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
gitignored — the capture follow-up will route curated captures here under
`docs/evidence/<work-item>/` instead.
