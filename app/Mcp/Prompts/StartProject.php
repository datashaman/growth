<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('start-project')]
#[Description('Start a Growth project by creating the project, capturing intent, and turning it into initial requirements.')]
class StartProject extends Prompt
{
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'name',
                description: 'Working project name.',
                required: true,
            ),
            new Argument(
                name: 'summary',
                description: 'Short description of the product, workflow, or change.',
                required: false,
            ),
            new Argument(
                name: 'rigor_level',
                description: 'Rigor level from 1 to 4. Defaults to 2.',
                required: false,
            ),
        ];
    }

    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'summary' => 'nullable|string|max:2000',
            'rigor_level' => 'nullable|integer|between:1,4',
        ]);

        $name = $data['name'];
        $summary = $data['summary'] ?? 'No summary provided yet.';
        $rigorLevel = $data['rigor_level'] ?? 2;

        $system = <<<'MD'
You are helping the user start a Growth project. Work from repository to sync to evidence:

Before creating or planning the Growth project, make sure the implementation workspace exists:

1. Create a dedicated folder for the project, using the requested project name as the default folder name unless the user specifies another location.
2. Initialize a git repository in that folder if one does not already exist.
3. Create the GitHub repository for that git repository and push the initial branch. If a GitHub owner or visibility is missing, ask only for that missing detail.
4. Use the GitHub repository name in owner/repo form when creating or updating the Growth project so the project is bound to its repository.

Fast path (preferred for greenfield): read `growth://template/rigor-N` matching the target rigor, fill in TODO placeholders from the user's summary, then call `apply-manifest` once. This creates the project plus its stakeholders, concerns, requirements, architecture view, plan, and verification plan/case in a single transaction. For L3+, follow up with `baseline-plan` and `upsert-review`.

Manual path (only when the manifest doesn't fit):

1. Create the project with `upsert-project`, including the GitHub repository binding.
2. Capture stakeholders and concerns with `upsert-stakeholder` and `upsert-concerns`.
3. Add sources with `upsert-source` when the user provides briefs, links, transcripts, tickets, or docs.
4. Convert intent into requirements with `upsert-requirements`.

Once the project exists and is bound to GitHub, set up growth-sync before moving into detailed planning:

1. Call `scaffold-github-sync` for the project.
2. Write the returned workflow to `.github/workflows/growth-sync.yml` in the project repository.
3. Commit and push the growth-sync workflow.
4. Complete the returned setup steps. For the `GROWTH_MCP_TOKEN` secret and `GROWTH_URL` variable, do not ask the project user to run console commands and do not generate or return the Growth token through MCP. Instead, hand the Growth operator the exact command `php artisan growth-sync:install {project_id} {sync_user_email} --growth-url={growth_url}` so the token is generated server-side and written directly to GitHub Secrets without being shown to the model or user.

In both paths, read `growth://playbook` and `growth://projects/{id}` as the project takes shape.

Keep the first turn narrow: set up the folder, git repository, GitHub repository, Growth project binding, and growth-sync first; then ask for the smallest missing intent needed to fill in the manifest or define the first requirements.
MD;

        $user = <<<MD
Start a Growth project named "{$name}" at rigor level {$rigorLevel}.

Summary:
{$summary}
MD;

        return [
            Response::text($system)->asAssistant(),
            Response::text($user),
        ];
    }
}
