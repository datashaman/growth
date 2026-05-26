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

#[Name('Plan')]
#[Description('Delivery plan, milestones, roles, work items, releases, and deployments for a project.')]
#[MimeType('application/json')]
class PlanResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/plan');
    }

    public function handle(Request $request): Response
    {
        $project = Project::with([
            'projectPlan',
            'milestones' => fn ($q) => $q->orderBy('name'),
            'roles' => fn ($q) => $q->orderBy('name'),
            'workItems' => fn ($q) => $q->inWbsOrder(),
            'workItems.requirements:id,type,text',
            'workItems.responsibleRole:id,name',
            'releases' => fn ($q) => $q->withCount(['workItems', 'deployments'])->orderByDesc('created_at'),
            'deployments' => fn ($q) => $q->with('release:id,version')->orderByDesc('created_at'),
        ])->find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        return $this->json([
            'type' => 'plan',
            'title' => "Plan - {$project->name}",
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'plan' => $project->projectPlan ? [
                'id' => $project->projectPlan->id,
                'status' => $project->projectPlan->status,
                'scope_summary' => $project->projectPlan->scope_summary,
                'approach' => $project->projectPlan->approach,
            ] : null,
            'milestones' => $project->milestones->map(fn ($milestone): array => [
                'id' => $milestone->id,
                'name' => $milestone->name,
                'status' => $milestone->status,
            ])->all(),
            'work_items' => $project->workItems->map(fn ($workItem): array => [
                'id' => $workItem->id,
                'kind' => $workItem->kind,
                'name' => $workItem->name,
                'sort_order' => $workItem->sort_order,
                'status' => $workItem->status,
                'responsible_role' => $workItem->responsibleRole?->name,
                'covers' => $workItem->requirements->pluck('id')->values()->all(),
            ])->all(),
            'releases' => $project->releases->map(fn ($release): array => [
                'id' => $release->id,
                'version' => $release->version,
                'status' => $release->status,
                'work_items_count' => $release->work_items_count,
                'deployments_count' => $release->deployments_count,
            ])->all(),
            'deployments' => $project->deployments->map(fn ($deployment): array => [
                'id' => $deployment->id,
                'environment' => $deployment->environment,
                'status' => $deployment->status,
                'release' => $deployment->release?->version,
            ])->all(),
        ]);
    }
}
