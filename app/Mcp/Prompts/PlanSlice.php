<?php

namespace App\Mcp\Prompts;

use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('plan-slice')]
#[Description('Plan the next implementation slice from captured capabilities, work items, and delivery evidence.')]
class PlanSlice extends Prompt
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
            'requirements as capabilities_count',
            'workItems',
            'milestones',
            'risks',
            'changeRequests',
        ])->findOrFail($data['project_id']);

        $uncoveredCapabilities = $project->requirements()
            ->doesntHave('workItems')
            ->count();

        $system = <<<'MD'
You are planning a thin Growth implementation slice.

Prefer a tracer-bullet slice that links capability, work item, verification, and evidence. Use `upsert-plan`, `upsert-milestone`, `upsert-work-item`, `link-work-item-to-capabilities`, `upsert-verification-case`, and `upsert-delivery-link` when useful.

Keep the plan small enough for one focused implementation pass.
MD;

        $user = <<<MD
Project: {$project->name} (`{$project->id}`)

Planning state:
- Capabilities: {$project->capabilities_count}
- Capabilities without work items: {$uncoveredCapabilities}
- Work items: {$project->work_items_count}
- Milestones: {$project->milestones_count}
- Risks: {$project->risks_count}
- Changes: {$project->change_requests_count}

Propose the next implementation slice and the Growth tool calls needed to capture it.
MD;

        return [
            Response::text($system)->asAssistant(),
            Response::text($user),
        ];
    }
}
