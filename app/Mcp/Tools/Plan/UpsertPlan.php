<?php

namespace App\Mcp\Tools\Plan;

use App\Models\ProjectPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update the delivery plan for a Growth project.')]
class UpsertPlan extends Tool
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
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'status' => $schema->string()->description('Plan lifecycle status')->enum(ProjectPlan::STATUSES),
            'scope_summary' => $schema->string()->description('What is in scope and out of scope'),
            'objectives' => $schema->string()->description('Project objectives'),
            'deliverables_summary' => $schema->string()->description('High-level deliverables'),
            'approach' => $schema->string()->description('Delivery approach, sequencing, and collaboration model'),
            'organization_summary' => $schema->string()->description('How people and agents are organized'),
            'assumptions' => $schema->string()->description('Planning assumptions'),
            'constraints' => $schema->string()->description('Fixed delivery constraints'),
            'budget_summary' => $schema->string()->description('Budget, funding, and cost summary'),
        ];
    }
}
