<?php

namespace App\Mcp\Prompts;

use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('capture-intent')]
#[Description('Review current project intent and ask for the next missing stakeholders, concerns, sources, or requirements.')]
class CaptureIntent extends Prompt
{
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'project_id',
                description: 'Project ULID.',
                required: true,
            ),
        ];
    }

    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::withCount([
            'stakeholders',
            'concerns',
            'sources',
            'requirements as requirements_count',
        ])->findOrFail($data['project_id']);

        $system = <<<'MD'
You are tightening Growth intent before more implementation work is planned.

Use these tools as needed: `upsert-stakeholder`, `upsert-concerns`, `upsert-source`, `upsert-requirements`, `upsert-citation`, and `list-requirements`.

Ask for missing intent only when it blocks a useful next requirement. Prefer concrete product behavior, constraints, evidence, and acceptance checks over broad discovery.
MD;

        $user = <<<MD
Project: {$project->name} (`{$project->id}`)

Current intent coverage:
- Stakeholders: {$project->stakeholders_count}
- Concerns: {$project->concerns_count}
- Sources: {$project->sources_count}
- Requirements: {$project->requirements_count}

Review the current state and propose the next intent-capture step.
MD;

        return [
            Response::text($system)->asAssistant(),
            Response::text($user),
        ];
    }
}
