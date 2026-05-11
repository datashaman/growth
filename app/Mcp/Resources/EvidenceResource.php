<?php

namespace App\Mcp\Resources;

use App\Growth\Alignment\AlignmentText;
use App\Growth\Assurance\ReadinessGateEvaluator;
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

#[Name('Evidence')]
#[Description('Evidence index for implementation links, check runs, releases, deployments, and readiness gates.')]
#[MimeType('application/json')]
class EvidenceResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function __construct(private readonly ReadinessGateEvaluator $readiness) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/evidence');
    }

    public function handle(Request $request): Response
    {
        $project = Project::with([
            'workItems.deliveryLinks.checkRuns',
            'releases.deployments',
            'deployments',
        ])->find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        $links = $project->workItems->flatMap->deliveryLinks;

        return $this->json([
            'type' => 'evidence',
            'title' => "Evidence - {$project->name}",
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'delivery_links' => $links->map(fn ($link): array => [
                'id' => $link->id,
                'type' => $link->type,
                'ref' => $link->ref,
                'url' => $link->url,
                'description' => $link->description,
                'check_runs' => $link->checkRuns->map(fn ($check): array => [
                    'id' => $check->id,
                    'provider' => $check->provider,
                    'name' => $check->name,
                    'run_ref' => $check->run_ref,
                    'status' => $check->status,
                    'conclusion' => $check->conclusion,
                    'url' => $check->url,
                ])->all(),
            ])->all(),
            'releases' => $project->releases->map(fn ($release): array => [
                'id' => $release->id,
                'version' => $release->version,
                'status' => $release->status,
            ])->all(),
            'deployments' => $project->deployments->map(fn ($deployment): array => [
                'id' => $deployment->id,
                'environment' => $deployment->environment,
                'status' => $deployment->status,
            ])->all(),
            'readiness_gates' => collect($this->readiness->evaluate($project)['gates'])->map(fn (array $gate): array => [
                ...$gate,
                'findings' => collect($gate['findings'])->map(fn (array $finding): array => [
                    ...$finding,
                    'message' => AlignmentText::sanitize($finding['message']),
                ])->all(),
            ])->all(),
        ]);
    }
}
