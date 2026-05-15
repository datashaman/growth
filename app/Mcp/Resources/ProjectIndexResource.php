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

#[Name('Project Index')]
#[Description('Project overview for the AI-aligned MCP layer, with links to intent, requirements, architecture, verification, plan, evidence, and readiness resources.')]
#[MimeType('application/json')]
class ProjectIndexResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}');
    }

    public function handle(Request $request): Response
    {
        $id = $request->get('project');
        $project = Project::withCount([
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
        ])->find($id);

        if (! $project) {
            return Response::error("Project [{$id}] not found.");
        }

        return $this->json([
            'type' => 'project_index',
            'title' => $project->name,
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'rigor_level' => $project->rigor_level,
            ],
            'resource_uris' => collect(['intent', 'requirements', 'architecture', 'verification', 'plan', 'evidence', 'readiness'])
                ->mapWithKeys(fn (string $resource): array => [$resource => "growth://projects/{$project->id}/{$resource}"])
                ->all(),
            'counts' => [
                'stakeholders' => $project->stakeholders_count,
                'concerns' => $project->concerns_count,
                'requirements' => $project->requirements_count,
                'architecture_views' => $project->design_views_count,
                'verification_plans' => $project->test_plans_count,
                'work_items' => $project->work_items_count,
                'changes' => $project->change_requests_count,
                'reviews' => $project->reviews_count,
                'releases' => $project->releases_count,
                'deployments' => $project->deployments_count,
            ],
        ]);
    }
}
