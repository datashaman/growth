<?php

namespace App\Mcp\Resources;

use App\Growth\Alignment\AlignmentText;
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

#[Name('Capabilities')]
#[Description('Capabilities and acceptance checks for a project, rendered with AI-aligned terminology.')]
#[MimeType('application/json')]
class CapabilitiesResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/capabilities');
    }

    public function handle(Request $request): Response
    {
        $project = Project::with([
            'requirements' => fn ($q) => $q->orderBy('doc')->orderBy('type')->orderBy('id'),
        ])->find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        $groups = $project->requirements->groupBy(fn ($requirement) => AlignmentText::docToLayer($requirement->doc));

        return $this->json([
            'type' => 'capabilities',
            'title' => "Capabilities - {$project->name}",
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'layers' => collect(['stakeholder', 'system', 'software'])
                ->map(fn (string $layer): array => [
                    'layer' => $layer,
                    'groups' => $groups->get($layer, collect())
                        ->groupBy('type')
                        ->map(fn ($requirements, string $type): array => [
                            'type' => $type,
                            'items' => $requirements->map(fn ($requirement): array => [
                                'id' => $requirement->id,
                                'priority' => $requirement->priority,
                                'text' => $requirement->text,
                                'rationale' => $requirement->rationale,
                                'acceptance_checks' => $requirement->acceptance_criteria ?? [],
                            ])->values()->all(),
                        ])->values()->all(),
                ])
                ->filter(fn (array $layer): bool => $layer['groups'] !== [])
                ->values()
                ->all(),
        ]);
    }
}
