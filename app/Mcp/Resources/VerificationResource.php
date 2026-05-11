<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\Concerns\ReturnsStructuredJson;
use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Verification')]
#[Description('Verification plans, acceptance checks, runs, and anomalies for a project.')]
#[MimeType('application/json')]
class VerificationResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/verification');
    }

    public function handle(Request $request): Response
    {
        $project = Project::with([
            'testPlans.cases.requirements:id,type,text',
            'testPlans.cases.runs',
            'anomalies.testRun.case:id,name,test_plan_id',
        ])->find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        return $this->json([
            'type' => 'verification',
            'title' => "Verification - {$project->name}",
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'plans' => $project->testPlans->sortBy(['level', 'name'])->map(fn ($plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'level' => $plan->level,
                'scope' => $plan->scope,
                'approach' => $plan->approach,
                'cases' => $plan->cases->map(function ($case): array {
                    $runs = $case->runs->groupBy('status')->map->count();

                    return [
                        'id' => $case->id,
                        'name' => $case->name,
                        'objective' => $case->objective,
                        'expected_results' => $case->expected_results,
                        'covers' => $case->requirements->pluck('id')->values()->all(),
                        'runs' => [
                            'pass' => $runs['pass'] ?? 0,
                            'fail' => $runs['fail'] ?? 0,
                            'blocked' => $runs['blocked'] ?? 0,
                            'skipped' => $runs['skipped'] ?? 0,
                        ],
                    ];
                })->all(),
            ])->values()->all(),
            'anomalies' => $project->anomalies->map(fn ($anomaly): array => [
                'id' => $anomaly->id,
                'severity' => $anomaly->severity,
                'status' => $anomaly->status,
                'summary' => $anomaly->summary,
            ])->all(),
        ]);
    }
}
