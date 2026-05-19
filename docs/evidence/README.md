# Visual evidence

This directory holds **visual evidence** — screenshots of real rendered pages,
organised so they can be cited against the work item a branch is delivering.

The development loop is otherwise text-only: diffs, test counts, CI status. It
never shows what the screen actually renders. Visual evidence closes that gap.

## A language-neutral contract

Visual evidence is a Growth capability for **any tracked project**, whatever its
stack. Growth does not care *how* the screenshots are produced — only that a
project follows this contract:

1. The project's browser tests write full-page PNGs to `docs/evidence/<slug>/`,
   one folder per captured subject (see [Layout](#layout)).
2. CI uploads that directory as a build artifact named **`growth-evidence`**.
3. growth-sync ingests that artifact on the CI run it belongs to — posting the
   per-PR gallery comment and, when the branch resolves to a work item, citing
   the gallery on it as an `evidence` delivery link.

A Laravel project produces the PNGs with the Pest browser suite (below); a Node
project would use Playwright or Cypress, a Python project pytest-playwright, a
Go project chromedp, an Elixir project Wallaby. The capture tooling is the
project's own. Steps 2 and 3 — the artifact name and the growth-sync ingestion
— are the shared, language-neutral part.

## Layout

```
docs/evidence/<work-item>/   one folder per work item, named by its slug
docs/evidence/<branch>/      fallback when a branch resolves to no work item
```

Each folder holds full-page PNG screenshots, one per captured state. The folder
name is the project's choice — growth-sync ingests whatever folders the artifact
contains and groups the gallery by them. Non-PNG files and files at the artifact
root are ignored.

## Uploading the artifact

The CI job that runs the browser tests uploads `docs/evidence/` under the fixed
artifact name growth-sync looks for:

```yaml
- name: Upload visual evidence
  if: always()
  uses: actions/upload-artifact@v7
  with:
    name: growth-evidence
    path: docs/evidence
    if-no-files-found: ignore
```

growth-sync needs `actions: read` (to find and download the artifact) and
`pull-requests: write` (to post the gallery comment) — see
`actions/growth-sync/workflow.example.yml`.

## What growth-sync does with it

On the `workflow_run` of the CI run that produced the artifact, growth-sync:

- records the matched work item's `evidence` delivery link, then uploads every
  screenshot to Growth scoped to that link, and posts **exactly one** gallery
  comment on the pull request with the Growth-hosted images embedded inline,
  grouped by folder. The comment is found by a hidden marker and updated in
  place on every push, never duplicated;
- cites the gallery on the matched work item by re-recording that `evidence`
  delivery link with the posted comment's URL.

When the branch resolves to **no work item** there is no delivery link to
upload against — not a failure. The gallery is still posted, but falls back to
a **manifest**: the screenshot names grouped by folder, with a link to the run
artifact (download it to keep the screenshots, since GitHub Actions artifacts
expire). Such a gallery is posted uncited.

Inline embedding works because Growth hosts the images at durable, public URLs
(see `EvidenceAssetController`), so a comment posted through the API can render
them even for a private repository.

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
gitignored. Routing this repo's own curated captures into `docs/evidence/<slug>/`
is per-project capture tooling and is deliberately not wired up here — this
repo's value to the contract is the growth-sync ingestion, which is
language-neutral and exercised by `actions/growth-sync`'s test suite.
