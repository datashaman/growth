# Growth

Growth is an engineering-process workbench for teams using client-side AI coding agents. It combines a Laravel web app, an MCP server surface, and a shared project database so agents and humans can work against the same project model.

Growth does not host or run an agent. Claude Code, Cursor, claude.ai, or another MCP host remains the client agent. Growth serves tools, resources, prompts, dashboards, and role context; records the work those clients perform; and keeps the product intent, plan, evidence, and governance trail coherent.

## What Growth Tracks

Growth models a software project from intent through delivery:

- product intent, stakeholders, concerns, sources, citations, and requirements
- architecture viewpoints, views, elements, and traceability
- verification plans, cases, runs, anomalies, and evidence assets
- project plans, milestones, roles, agents, RACI assignments, work items, dependencies, risks, releases, and deployments
- reviews, findings, decision events, change requests, impact analysis, and approval history
- GitHub delivery evidence from pull requests, checks, deployments, and releases

The web app gives humans a project dashboard and artifact pages. The MCP surfaces expose the same model to trusted local and HTTP clients.

## Product Surfaces

The authenticated web app includes:

- dashboard
- intent
- requirements
- architecture
- verification
- plan
- evidence
- changes
- reviews
- feedback
- notifications

The MCP app resources include read-only project dashboards, gate status, trace graphs, and requirement exploration views that can be rendered by compatible MCP clients.

## Local Development

Requirements:

- PHP 8.4+
- Composer
- Node.js and npm
- SQLite for the default local database
- Redis if you want Horizon/Reverb-style local worker behavior

Set up the app:

```bash
composer setup
php artisan db:seed
```

Run the full local development stack:

```bash
composer dev
```

That starts the Laravel server, Vite, Reverb, Horizon listener, and log tailing process defined in `composer.json`.

Run tests:

```bash
composer test
npm run test:sync
```

The default seeder creates demo users and demo projects. Local seeded users use the password `password`.

## Local MCP Users

Create a local user, then run a trusted stdio MCP capability-surface server as that user:

```bash
php artisan user:create user@example.com --name="Example User"
GROWTH_USER_EMAIL='user@example.com' php artisan mcp:start intake
```

`GROWTH_USER_ID` is also supported. `GROWTH_WORKSPACE_ID` can override the user's active workspace for local stdio MCP. HTTP MCP clients and GitHub sync use Passport bearer tokens instead.

Available MCP capability-surface handles:

- `all` - complete power-user surface with every registered tool, resource, and prompt
- `intake` - project intent, stakeholders, concerns, sources, citations, and requirements
- `architecture` - architecture viewpoints, views, elements, and coverage
- `planning` - delivery plans, roles, agents, milestones, work items, risks, releases, and deployments
- `verification` - verification plans, cases, runs, anomalies, check evidence, and readiness
- `governance` - reviews, change control, release readiness, impact analysis, and evidence gaps
- `readonly` - read-only project state, resources, summaries, traces, and lookup tools

The matching HTTP endpoints are `/mcp/all`, `/mcp/intake`, `/mcp/architecture`, `/mcp/planning`, `/mcp/verification`, `/mcp/governance`, and `/mcp/readonly`.

The `readonly` and `all` surfaces also expose `show-project-dashboard`, an MCP app that renders a read-only project dashboard with readiness, implementation, schedule, capacity, and resource links.

## GitHub Sync

The `growth-sync` GitHub Action mirrors a repository's delivery activity into Growth: pull requests become delivery links, CI runs become check evidence, and deployments and published releases become deployment and release records on the bound project.

### Install in an adopter repository

The quickest path is the `scaffold-github-sync` MCP tool. Given a project, it returns a ready-to-commit workflow file and the remaining one-time setup steps.

To wire it up by hand instead:

1. Copy [`actions/growth-sync/workflow.example.yml`](actions/growth-sync/workflow.example.yml) to `.github/workflows/growth-sync.yml` in the repository you want to track.
2. Mint a Passport personal access token with the `mcp:use` scope for the Growth user the sync should act as.
3. Add the token as the `GROWTH_MCP_TOKEN` repository secret.
4. Set the workflow's `growth-url` input to your Growth instance URL. A `${{ vars.GROWTH_URL }}` repository variable keeps the URL out of the repo.
5. In Growth, set the project's `github_repo` field to the repository in `owner/repo` form so deployment and release events resolve to it.
6. If your CI runs on GitHub Actions, edit the `workflow_run.workflows` list in the copied workflow to name your CI workflow or workflows.

### CI sync

CI runs are recorded as check evidence through two triggers:

- `workflow_run` - for GitHub Actions CI. GitHub does not deliver `check_run` events for checks its own Actions create, so Actions CI is captured here instead. The `workflow_run.workflows` list names the CI workflows to watch; it cannot wildcard.
- `check_run` - for third-party CI, such as CircleCI or Buildkite, whose checks are created by their own GitHub Apps.

Both resolve the PR from the event payload. Runs triggered by fork pull requests carry no PR reference and are skipped.

### Attribution

Pull request and CI sync attribute each event to a work item, trying three sources in order. The first that resolves wins:

1. Branch name. A `WI-<number>` reference anywhere in the branch name, such as `WI-42-add-login` or `feature/wi-42`, resolves to the work item with that per-project number.
2. Commit trailer. A `Growth-Work-Item: <work-item-id>` git trailer on the commit, where the value is a work item ULID. The last trailer wins if several are present. A resolved trailer also binds the branch, so later trailer-less events on it still attribute.
3. Branch delivery link. A work item explicitly bound to the branch with the `upsert-delivery-link` tool using type `branch`.

An event that matches none of the three is recorded as an unattributed event and surfaces on the Evidence page. The sync is not errored.

### Action inputs

| Input          | Required | Description                                         |
| -------------- | -------- | --------------------------------------------------- |
| `growth-url`   | yes      | Base URL of the Growth instance.                    |
| `growth-token` | yes      | Passport token with the `mcp:use` scope.            |
| `github-token` | no       | Defaults to `${{ github.token }}` for commit reads. |

The workflow triggers on `pull_request` (`opened`, `synchronize`, `closed`), `check_run` (`completed`), `workflow_run` (`completed`), `deployment_status`, and `release` (`published`).

## Architecture Notes

The repo keeps product and architecture notes in:

- [`CONTEXT.md`](CONTEXT.md) - domain vocabulary and relationships
- [`ROADMAP.md`](ROADMAP.md) - current product capability map
- [`docs/architecture`](docs/architecture) - design notes and ADR-adjacent discovery
- [`docs/adr`](docs/adr) - accepted architectural decisions

Use the project vocabulary in `CONTEXT.md` when adding tools, resources, docs, or UI copy. In particular, Growth distinguishes client agents, MCP sessions, capability surfaces, roles, personas, lenses, and agent principals.

## License

Growth is currently a private Datashaman project. Add a public license here before distributing it outside the owning organization.
