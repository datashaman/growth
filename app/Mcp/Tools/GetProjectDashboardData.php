<?php

namespace App\Mcp\Tools;

use App\Growth\Assurance\ReadinessGateEvaluator;
use App\Growth\Execution\ImplementationStatusSummarizer;
use App\Growth\Plan\PlanCapacitySummarizer;
use App\Growth\Plan\ScheduleHealthSummarizer;
use App\Mcp\Resources\ProjectDashboardApp;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Ui\Enums\Visibility;

#[Description('Return read-only data for the Growth project dashboard app.')]
#[IsReadOnly]
#[RendersApp(resource: ProjectDashboardApp::class, visibility: [Visibility::App])]
class GetProjectDashboardData extends Tool
{
    public function __construct(
        private readonly ReadinessGateEvaluator $readiness,
        private readonly ImplementationStatusSummarizer $implementationStatus,
        private readonly ScheduleHealthSummarizer $scheduleHealth,
        private readonly PlanCapacitySummarizer $capacity,
    ) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'nullable|string|owned_project',
        ]);

        $projects = Project::query()
            ->orderBy('created_at')
            ->limit(100)
            ->get(['id', 'name', 'description', 'integrity_level', 'created_at']);

        $selectedProject = null;
        if (isset($data['project_id'])) {
            $selectedProject = Project::withCount([
                'stakeholders',
                'concerns',
                'requirements',
                'designViews',
                'testPlans',
                'workItems',
                'changeRequests',
                'reviews',
                'releases',
                'deployments',
            ])->find($data['project_id']);

            if (! $selectedProject) {
                return Response::error("Project [{$data['project_id']}] not found.");
            }
        }

        return Response::structured([
            'projects' => $projects->map(fn (Project $project): array => $this->projectOption($project))->all(),
            'selected_project' => $selectedProject ? $this->dashboardProject($selectedProject) : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Optional project ULID to load into the dashboard.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'projects' => $schema->array()->required(),
            'selected_project' => $schema->object(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function projectOption(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'rigor_level' => $project->integrity_level,
            'created_at' => $project->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function dashboardProject(Project $project): array
    {
        return $this->projectOption($project) + [
            'counts' => [
                'stakeholders' => $project->stakeholders_count,
                'concerns' => $project->concerns_count,
                'capabilities' => $project->requirements_count,
                'architecture_views' => $project->design_views_count,
                'verification_plans' => $project->test_plans_count,
                'work_items' => $project->work_items_count,
                'changes' => $project->change_requests_count,
                'reviews' => $project->reviews_count,
                'releases' => $project->releases_count,
                'deployments' => $project->deployments_count,
            ],
            'resource_uris' => $this->resourceUris($project),
            'readiness' => $this->readiness->evaluate($project),
            'implementation' => $this->implementationStatus->summarize($project),
            'schedule' => $this->scheduleHealth->summarize($project),
            'capacity' => $this->capacity->summarize($project),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function resourceUris(Project $project): array
    {
        return collect(['intent', 'capabilities', 'architecture', 'verification', 'plan', 'evidence', 'readiness'])
            ->mapWithKeys(fn (string $resource): array => [$resource => "growth://projects/{$project->id}/{$resource}"])
            ->prepend("growth://projects/{$project->id}", 'index')
            ->all();
    }
}
