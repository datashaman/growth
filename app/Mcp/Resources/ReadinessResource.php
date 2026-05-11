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

#[Name('Readiness')]
#[Description('Lifecycle readiness gate results for agent planning and release decisions.')]
#[MimeType('application/json')]
class ReadinessResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function __construct(private readonly ReadinessGateEvaluator $readiness) {}

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/readiness');
    }

    public function handle(Request $request): Response
    {
        $project = Project::find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        $readiness = $this->readiness->evaluate($project);

        return $this->json([
            'type' => 'readiness',
            'title' => "Readiness - {$project->name}",
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'status' => $readiness['status'],
            'gates' => collect($readiness['gates'])->map(fn (array $gate): array => [
                ...$gate,
                'findings' => collect($gate['findings'])->map(fn (array $finding): array => [
                    ...$finding,
                    'message' => AlignmentText::sanitize($finding['message']),
                ])->all(),
            ])->all(),
            'implementation_summary' => $readiness['implementation_summary'],
        ]);
    }
}
