<?php

namespace App\Mcp\Tools\Plan;

use App\Models\ProjectPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update the master Project Management Plan (delivery planning). Idempotent on project_id — one PMP per project.')]
class UpsertProjectPlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'status' => 'nullable|in:'.implode(',', ProjectPlan::STATUSES),
            'scope_summary' => 'nullable|string',
            'objectives' => 'nullable|string',
            'deliverables_summary' => 'nullable|string',
            'approach' => 'nullable|string',
            'organization_summary' => 'nullable|string',
            'assumptions' => 'nullable|string',
            'constraints' => 'nullable|string',
            'budget_summary' => 'nullable|string',
        ]);

        $plan = ProjectPlan::updateOrCreate(
            ['project_id' => $data['project_id']],
            collect($data)->except('project_id')->all(),
        );

        return Response::structured([
            'id' => $plan->id,
            'project_id' => $plan->project_id,
            'status' => $plan->status,
            'created' => $plan->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'status' => $schema->string()
                ->description('Plan lifecycle status')
                ->enum(ProjectPlan::STATUSES),
            'scope_summary' => $schema->string()
                ->description('internal planning what is in scope and out of scope'),
            'objectives' => $schema->string()
                ->description('project objectives'),
            'deliverables_summary' => $schema->string()
                ->description('high-level deliverables'),
            'approach' => $schema->string()
                ->description('methodology, lifecycle model, sequencing'),
            'organization_summary' => $schema->string()
                ->description('narrative of how the team is organized'),
            'assumptions' => $schema->string()
                ->description('planning assumptions'),
            'constraints' => $schema->string()
                ->description('fixed constraints (budget, schedule, regulatory)'),
            'budget_summary' => $schema->string()
                ->description('rules — budget, funding, and cost management summary'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
