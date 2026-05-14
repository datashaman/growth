<?php

namespace App\Mcp\Tools\Assurance;

use App\Growth\Alignment\AlignmentText;
use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Build a Growth evidence bundle index with resources, artifact counts, and readiness gate status.')]
class BuildEvidenceBundle extends Tool
{
    public function __construct(private readonly ReadinessGateEvaluator $readiness) {}

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
        ]);

        $project = Project::withCount([
            'stakeholders',
            'concerns',
            'requirements',
            'designViews',
            'testPlans',
            'workItems',
            'reviews',
            'changeRequests',
            'releases',
            'deployments',
        ])->findOrFail($data['project_id']);

        $readiness = AlignmentText::sanitizeArray($this->readiness->evaluate($project));

        return Response::structured([
            'project_id' => $project->id,
            'project' => $project->name,
            'rigor_level' => $project->rigor_level,
            'readiness_status' => $readiness['status'],
            'resources' => [
                'index' => "growth://projects/{$project->id}",
                'intent' => "growth://projects/{$project->id}/intent",
                'capabilities' => "growth://projects/{$project->id}/capabilities",
                'architecture' => "growth://projects/{$project->id}/architecture",
                'verification' => "growth://projects/{$project->id}/verification",
                'plan' => "growth://projects/{$project->id}/plan",
                'evidence' => "growth://projects/{$project->id}/evidence",
                'readiness' => "growth://projects/{$project->id}/readiness",
            ],
            'counts' => [
                'stakeholders' => $project->stakeholders_count,
                'concerns' => $project->concerns_count,
                'capabilities' => $project->requirements_count,
                'architecture_views' => $project->design_views_count,
                'verification_plans' => $project->test_plans_count,
                'work_items' => $project->work_items_count,
                'reviews' => $project->reviews_count,
                'changes' => $project->change_requests_count,
                'releases' => $project->releases_count,
                'deployments' => $project->deployments_count,
            ],
            'gates' => $readiness['gates'],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
        ];
    }
}
