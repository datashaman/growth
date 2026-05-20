# Growth

Growth is a project-governance application with two top-level surfaces sharing
one Eloquent layer:

- **Webapp** — Livewire/Flux MVC views (`routes/web.php`, `app/Livewire`,
  `resources/views/pages/**`). The read-only sweep landed in #185, so most
  pages query Eloquent directly; do not route them through MCP.
- **MCP server** — `routes/ai.php` registers stdio + HTTP transports for
  several surface servers (`AllServer`, `IntakeServer`, `ReadonlyServer`, …)
  backed by the tool/resource/prompt classes under `app/Mcp/`.

Read `CONTEXT.md` and `docs/adr/` before touching unfamiliar areas — they own
the domain vocabulary and the architectural decisions (`docs/agents/domain.md`).

## Stack

- PHP 8.4+ (Forge runs 8.4, dev on 8.5), Laravel 13
- laravel/mcp v0, laravel/sanctum v4, laravel/passport (OAuth on the MCP HTTP transport)
- Pest 4 (browser plugin), PHPUnit 12, Pint v1
- DB: **Postgres in CI, SQLite locally** — raw SQL must work on both

## Code conventions

- Typed signatures and return types everywhere; PHP 8 constructor property
  promotion.
- Curly braces on every control structure, even one-liners.
- Prefer PHPDoc blocks over inline comments; reserve inline for non-obvious
  logic.
- Use array-shape PHPDoc for structured arrays.
- Enum keys: TitleCase (`FavoritePerson`, `Monthly`).
- Descriptive identifiers (`isRegisteredForDiscounts`, not `discount()`).
- Reuse existing components and helpers — check siblings before introducing
  a new one. No new top-level directories without asking.

## Testing

- Every change ships with a test. Pest feature tests are the default; unit
  tests only for pure logic.
- Run with `php artisan test --compact`, filtered to the affected file or
  `--filter=name`. Don't run the whole suite to check one thing.
- Use factories (and their states) for model setup; don't hand-roll fixtures.
- Don't write tests that assert deleted code is absent. Just delete the code.
- No verification scripts or one-off tinker recipes when a test would do.

## Code style

- After editing any PHP file, run `vendor/bin/pint --dirty --format agent`.
  Pint in `--format agent` mode emits JSON. Never run `pint --test`; just let
  it fix.

## Frontend

- Asset changes require `npm run build` (or `npm run dev` / `composer run dev`).
  If a UI change doesn't appear, ask the user which they're running.

## Deployment

- Deploys to Laravel Forge and Vapor in parallel. Reverb on Forge, Pusher on
  Vapor (same protocol; `BROADCAST_CONNECTION` is the only switch).

## Artisan

- Generate files with `php artisan make:*` and pass `--no-interaction`. Generic
  PHP classes go through `make:class`.
- When creating a model, generate its factory and seeder in the same go.
- Inspect routes: `php artisan route:list --path=mcp` (etc.).
- Inspect config: `php artisan config:show <dot.path>` or read `config/`
  directly.
- Tinker for ad-hoc checks: `php artisan tinker --execute 'Model::query()...;'`
  — always single-quote the outer string, double-quote PHP strings inside.

## Per-area guidance

Each surface has its own `CLAUDE.md` next to the code, auto-loaded when you
work in that directory:

- `app/Mcp/Tools/CLAUDE.md` — MCP tool contract, attributes, response shapes,
  workspace scoping, sampling.
- `app/Mcp/Resources/CLAUDE.md` — data resources vs MCP UI app resources.
- `resources/views/mcp/CLAUDE.md` — MCP UI app blade conventions, JS
  scaffolding, payload-key contract.
- `tests/Feature/Mcp/CLAUDE.md` — Passport actor setup, JSON-RPC POST shape,
  `readResource` helper, `FakeTransporter` for sampling.

## Agent skills (cross-cutting)

- **Issue tracker** — GitHub Issues in `datashaman/growth`, driven by `gh`.
  See `docs/agents/issue-tracker.md`.
- **Triage labels** — five canonical labels (`needs-triage`, `needs-info`,
  `ready-for-agent`, `ready-for-human`, `wontfix`).
  See `docs/agents/triage-labels.md`.
- **Domain docs** — `CONTEXT.md` + `docs/adr/` at the repo root.
  See `docs/agents/domain.md`.
- **Project skills** — domain-specific instructions under `.claude/skills/`
  (mirrored to `.agents/skills/` and `.github/skills/`):
  `laravel-best-practices`, `mcp-development`, `pest-testing`. Activate the
  matching skill when working in that domain.
