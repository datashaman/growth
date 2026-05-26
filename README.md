# Growth

Growth is an engineering-process workbench for teams using client-side AI coding agents. It combines a Laravel web app, an MCP server surface, and a shared project database so agents and humans can work against the same project model.

Growth does not host or run an agent. Claude Code, Cursor, claude.ai, or another MCP host remains the client agent. Growth serves tools, resources, prompts, dashboards, and role context; records the work those clients perform; and keeps the product intent, plan, evidence, and governance trail coherent.

## What Growth Tracks

Growth models a software project from intent through delivery:

- product intent, stakeholders, concerns, sources, citations, and requirements
- architecture viewpoints, views, elements, and traceability
- verification plans, cases, runs, anomalies, and evidence assets
- project plans, milestones, roles, agents, RACI assignments, work items, dependencies, risks, releases, and deployments
- spec mockups for work items and UI requirements, including named alternatives and coverage gaps
- reviews, findings, decision events, change requests, impact analysis, and approval history
- decision requests, notifications, feedback, and recorded MCP tool activity
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
- mockups
- evidence
- changes
- reviews
- roles
- feedback
- notifications
- tool invocations

The MCP app resources include read-only project dashboards, gate status, trace graphs, and requirement exploration views that can be rendered by compatible MCP clients. The data resources include project index, canonical project documents, manifests and manifest sections, playbooks, rigor levels, artifact briefs, mockups, and evidence/readiness resources.

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
GROWTH_USER_EMAIL='user@example.com' php artisan mcp:start all
```

`GROWTH_USER_ID` is also supported. `GROWTH_WORKSPACE_ID` can override the user's active workspace for local stdio MCP. HTTP MCP clients and GitHub sync use Passport bearer tokens instead.

Available MCP capability-surface handles:

- `all` - complete power-user surface with every registered tool, resource, and prompt
- `management` - project lifecycle, adoption, GitHub sync scaffolding, manifest import/export, and starter templates
- `intake` - project intent, stakeholders, concerns, sources, citations, and requirements
- `architecture` - architecture viewpoints, views, elements, and coverage
- `planning` - delivery plans, roles, agents, milestones, work items, mockups, risks, releases, and deployments
- `verification` - verification plans, cases, runs, anomalies, check evidence, and readiness
- `governance` - reviews, change control, release readiness, impact analysis, and evidence gaps
- `readonly` - read-only project state, resources, summaries, traces, and lookup tools

The matching HTTP endpoints are `/mcp/all`, `/mcp/management`, `/mcp/intake`, `/mcp/architecture`, `/mcp/planning`, `/mcp/verification`, `/mcp/governance`, and `/mcp/readonly`.

The `readonly` and `all` surfaces also expose MCP apps for the project dashboard, gate status, requirement explorer, and trace graph. These apps render project health, readiness, implementation state, trace context, and resource links without granting write tools.

For project bootstrap and sync setup, connect to `management` or `all`. For broad local exploration, `all` is the most convenient starting surface; use role-scoped surfaces when a client should advertise a narrower tool set.

Mockup generation is handled through the `planning` and `all` surfaces. Agents
can read an owner-specific mockup design brief, upsert named mockup
alternatives, list mockup coverage across a project, filter to owners missing
coverage, and delete one owner's mockup set before regeneration. Mockup upserts
return non-blocking quality warnings for brittle patterns such as external
assets or whole-screen state pickers; materially different screens should
usually be separate named mockups.

## GitHub Sync

The `growth-sync` GitHub Action mirrors a repository's delivery activity into Growth: pull requests become delivery links, CI runs become check evidence, and deployments and published releases become deployment and release records on the bound project.

If the repository uploads a `growth-evidence` artifact from browser tests, the sync can also post a visual evidence gallery and cite it on the resolved work item.

### Install in an adopter repository

The quickest path is the `scaffold-github-sync` MCP tool. Given a project, it returns a ready-to-commit workflow file and the remaining one-time setup steps.

To wire it up by hand instead:

1. Copy [`actions/growth-sync/workflow.example.yml`](actions/growth-sync/workflow.example.yml) to `.github/workflows/growth-sync.yml` in the repository you want to track.
2. Ask the Growth operator to install the repository secret and variable:

   ```bash
   php artisan growth-sync:install <project-id> <sync-user-email> --growth-url=https://growth.example.com
   ```

   The command mints a workspace-bound Passport token with the `mcp:use` scope and writes it directly to `GROWTH_MCP_TOKEN` in GitHub Secrets without printing it. It also writes `GROWTH_URL` as a repository variable.
3. In Growth, set the project's `github_repo` field to the repository in `owner/repo` form so deployment and release events resolve to it.
4. If your CI runs on GitHub Actions, edit the `workflow_run.workflows` list in the copied workflow to name your CI workflow or workflows.

### CI sync

CI runs are recorded as check evidence through two triggers:

- `workflow_run` - for GitHub Actions CI. GitHub does not deliver `check_run` events for checks its own Actions create, so Actions CI is captured here instead. The `workflow_run.workflows` list names the CI workflows to watch; it cannot wildcard.
- `check_run` - for third-party CI, such as CircleCI or Buildkite, whose checks are created by their own GitHub Apps.

Both resolve the PR from the event payload. Runs triggered by fork pull requests carry no PR reference and are skipped.

### Attribution

Pull request and CI sync attribute each event to either a work item or, for CR-only work, a change request. The first source that resolves wins:

1. `Growth-Work-Item: <work-item-id>` commit trailer.
2. `Growth-Change-Request: <change-request-id-or-CR-number>` commit trailer.
3. `WI-<number>` branch reference, such as `WI-42-add-login` or `feature/wi-42`.
4. `CR-<number>` branch reference, such as `CR-7-doc-fix` or `feature/cr-007`.
5. Work item branch delivery link, bound with `upsert-delivery-link` using type `branch`.
6. Change request branch delivery link, bound with `upsert-change-request-delivery-link` using type `branch`.

The last matching trailer wins if several of the same trailer are present. A resolved trailer also binds the same-repository branch, so later trailer-less events on it still attribute. Fork pull requests do not create or use branch bindings because the same head branch name can exist in several forks.

Check-run evidence remains work-item scoped. If a CI event resolves only to a change request, growth-sync records no check-run evidence and does not create an unattributed-event exception. An event that matches no source is recorded as unattributed and surfaces on the Evidence page; the sync itself is not errored.

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

Growth is open-sourced under the GNU Affero General Public License v3.0 or later. See [`LICENSE`](LICENSE).

Forks, experiments, issues, and contributions are welcome.

Commercial hosting, support, implementation, and consulting are available from Datashaman.
