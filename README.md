<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Local MCP Users

Create a local user, then run a trusted stdio MCP role server as that user:

```bash
php artisan user:create marlin@example.com --name=Marlin
GROWTH_USER_EMAIL='marlin@example.com' php artisan mcp:start intake
```

`GROWTH_USER_ID` is also supported. `GROWTH_TOKEN` remains available for older local configs, but bearer tokens are mainly intended for the HTTP MCP route.

Available MCP role handles:

- `all` — complete power-user surface with every registered tool, resource, and prompt
- `intake` — project intent, stakeholders, concerns, sources, citations, and requirements
- `architecture` — architecture viewpoints, views, elements, and coverage
- `planning` — delivery plans, roles, agents, milestones, work items, risks, releases, and deployments
- `verification` — verification plans, cases, runs, anomalies, check evidence, and readiness
- `governance` — reviews, change control, release readiness, impact analysis, and evidence gaps
- `readonly` — read-only project state, resources, summaries, traces, and lookup tools

The matching HTTP endpoints are `/mcp/all`, `/mcp/intake`, `/mcp/architecture`, `/mcp/planning`, `/mcp/verification`, `/mcp/governance`, and `/mcp/readonly`.

The `readonly` and `all` servers also expose `show-project-dashboard`, an MCP app that renders a read-only project dashboard with readiness, implementation, schedule, capacity, and resource links.

## GitHub Sync

The `growth-sync` GitHub Action mirrors a repository's delivery activity into
Growth: pull requests become delivery links, CI runs become check evidence,
and deployments and published releases become deployment and release records
on the bound project.

### Install in an adopter repository

1. Copy [`actions/growth-sync/workflow.example.yml`](actions/growth-sync/workflow.example.yml)
   to `.github/workflows/growth-sync.yml` in the repository you want to track.
2. Mint a Passport personal access token with the `mcp:use` scope for the
   Growth user the sync should act as.
3. Add the token as the `GROWTH_MCP_TOKEN` repository secret.
4. Set the workflow's `growth-url` input to your Growth instance URL (a
   `${{ vars.GROWTH_URL }}` repository variable keeps the URL out of the repo).
5. In Growth, set the project's `github_repo` field to the repository in
   `owner/repo` form so deployment and release events resolve to it.
6. If your CI runs on GitHub Actions, edit the `workflow_run.workflows` list
   in the copied workflow to name your CI workflow(s) — see CI sync below.

### CI sync

CI runs are recorded as check evidence through two triggers:

- `workflow_run` — for **GitHub Actions** CI. GitHub does not deliver
  `check_run` events for checks its own Actions create, so Actions CI is
  captured here instead. The `workflow_run.workflows` list names the CI
  workflow(s) to watch; it cannot wildcard, so each adopter must edit it.
- `check_run` — for **third-party CI** (CircleCI, Buildkite, ...) whose
  checks are created by their own GitHub Apps.

Both resolve the PR from the event payload; runs triggered by fork pull
requests carry no PR reference and are skipped.

### Trailer convention

Pull request and CI sync find the work item from a `Growth-Work-Item:`
git trailer on the commit. Add it to the commit a PR carries:

```
Add the widget

Growth-Work-Item: 01HXYZ...
```

The trailer value is a work item ULID. The last trailer wins if several are
present. Commits without the trailer are skipped, not errored.

### Action inputs and environment

| Input          | Required | Description                                         |
| -------------- | -------- | --------------------------------------------------- |
| `growth-url`   | yes      | Base URL of the Growth instance.                    |
| `growth-token` | yes      | Passport token with the `mcp:use` scope.            |
| `github-token` | no       | Defaults to `${{ github.token }}` for commit reads. |

The workflow triggers on `pull_request` (`opened`, `synchronize`, `closed`),
`check_run` (`completed`), `workflow_run` (`completed`), `deployment_status`,
and `release` (`published`).

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
