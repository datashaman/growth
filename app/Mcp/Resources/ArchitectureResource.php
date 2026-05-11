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

#[Name('Architecture')]
#[Description('Architecture views, addressed concerns, and design elements for a project.')]
#[MimeType('application/json')]
class ArchitectureResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/architecture');
    }

    public function handle(Request $request): Response
    {
        $project = Project::with([
            'designViews.concerns',
            'designViews.elements' => fn ($q) => $q->orderBy('kind')->orderBy('name'),
        ])->find($request->get('project'));

        if (! $project) {
            return Response::error("Project [{$request->get('project')}] not found.");
        }

        return $this->json([
            'type' => 'architecture',
            'title' => "Architecture - {$project->name}",
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
            ],
            'views' => $project->designViews
                ->sortBy(['viewpoint', 'name'])
                ->map(fn ($view): array => [
                    'id' => $view->id,
                    'name' => $view->name,
                    'viewpoint' => $view->viewpoint,
                    'description' => $view->description,
                    'concerns' => $view->concerns->map(fn ($concern): array => [
                        'id' => $concern->id,
                        'text' => $concern->text,
                    ])->all(),
                    'elements' => $view->elements->map(fn ($element): array => [
                        'id' => $element->id,
                        'kind' => $element->kind,
                        'name' => $element->name,
                        'type' => $element->type,
                        'purpose' => $element->purpose,
                    ])->all(),
                ])->values()->all(),
        ]);
    }
}
