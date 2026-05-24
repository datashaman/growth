<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\Concerns\ReturnsStructuredJson;
use App\Models\Mockup;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Project Mockups')]
#[Description('Lists all project-level design system mockups — the layout template and component specimens — by name, with their latest revision timestamps and HTML resource URIs.')]
#[MimeType('application/json')]
class ProjectMockupsResource extends Resource implements HasUriTemplate
{
    use ReturnsStructuredJson;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('growth://projects/{project}/mockups');
    }

    public function handle(Request $request): Response
    {
        $projectId = $request->get('project');

        $mockups = Mockup::where('owner_type', 'project')
            ->where('owner_id', $projectId)
            ->orderBy('name')
            ->get();

        return $this->json([
            'project_id' => $projectId,
            'total' => $mockups->count(),
            'results' => $mockups->map(fn (Mockup $mockup): array => [
                'id' => $mockup->id,
                'name' => $mockup->name,
                'role' => $mockup->name === 'layout' ? 'layout_template' : 'component_specimen',
                'updated_at' => $mockup->updated_at?->toIso8601String(),
                'html_uri' => "growth://projects/{$projectId}/mockups/{$mockup->name}",
            ])->all(),
        ]);
    }
}
