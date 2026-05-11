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

#[Name('Intent')]
#[Description('Intent, stakeholders, concerns, and sources for a project.')]
#[MimeType('application/json')]
class IntentResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/intent');
    }

    public function handle(Request $request): Response
    {
        $project = Project::with([
            'stakeholders' => fn ($q) => $q->orderBy('name'),
            'concerns.raisedBy',
            'sources' => fn ($q) => $q->orderBy('title'),
        ])->find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        return $this->json([
            'type' => 'intent',
            'title' => "Intent - {$project->name}",
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
            ],
            'sections' => [
                [
                    'title' => 'Stakeholders',
                    'items' => $project->stakeholders->map(fn ($stakeholder): array => [
                        'id' => $stakeholder->id,
                        'name' => $stakeholder->name,
                        'role' => $stakeholder->role,
                        'kind' => $stakeholder->kind,
                        'description' => $stakeholder->description,
                    ])->all(),
                ],
                [
                    'title' => 'Concerns',
                    'items' => $project->concerns->map(fn ($concern): array => [
                        'id' => $concern->id,
                        'raised_by' => $concern->raisedBy?->name,
                        'text' => $concern->text,
                    ])->all(),
                ],
                [
                    'title' => 'Sources',
                    'items' => $project->sources->map(fn ($source): array => [
                        'id' => $source->id,
                        'title' => $source->title,
                        'kind' => $source->kind,
                        'uri' => $source->uri,
                        'external_ref' => $source->external_ref,
                    ])->all(),
                ],
            ],
        ]);
    }
}
