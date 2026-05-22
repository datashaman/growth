<?php

namespace App\Mcp\Prompts;

use App\Models\DesignElement;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('plan-slice')]
#[Description('Plan the next implementation slice from captured requirements, work items, and delivery evidence.')]
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
            'requirements as requirements_count',
            'workItems',
            'milestones',
            'risks',
            'changeRequests',
            'designViews',
        ])->findOrFail($data['project_id']);

        $uncoveredRequirements = $project->requirements()
            ->doesntHave('workItems')
            ->count();

        $architectureElements = DesignElement::query()
            ->whereHas('view', fn ($query) => $query->where('project_id', $project->id))
            ->count();

        $architectureGuidance = $project->design_views_count > 0
            ? 'Architecture context exists. Before proposing work, inspect `list-architecture-views`, `list-architecture-elements`, and `trace-query` for relevant views, concerns, and elements; treat architecture prose and element properties as agent-facing design context.'
            : 'No architecture views are captured yet. If the slice needs design context, propose the smallest architecture view/element capture needed before implementation.';

        $system = <<<'MD'
You are planning a thin Growth implementation slice.

Prefer a tracer-bullet slice that links requirement, work item, verification, and evidence. Use `upsert-plan`, `upsert-milestone`, `upsert-work-items`, `link-work-item-to-requirements`, `upsert-verification-cases`, and `upsert-delivery-link` when useful.

Architecture content is agent-facing design context, not ceremony. If architecture views or elements exist, inspect them before deriving the slice and preserve their prose/properties as design input instead of treating them as gate-only metadata.

Keep the plan small enough for one focused implementation pass.
MD;

        $user = <<<MD
Project: {$project->name} (`{$project->id}`)

Planning state:
- Requirements: {$project->requirements_count}
- Requirements without work items: {$uncoveredRequirements}
- Work items: {$project->work_items_count}
- Milestones: {$project->milestones_count}
- Risks: {$project->risks_count}
- Changes: {$project->change_requests_count}
- Architecture views: {$project->design_views_count}
- Architecture elements: {$architectureElements}

Architecture context:
{$architectureGuidance}

Propose the next implementation slice and the Growth tool calls needed to capture it.
MD;

        return [
            Response::text($system)->asAssistant(),
            Response::text($user),
        ];
    }
}
