<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Mockup;
use App\Models\Project;
use App\Support\MockupScreenshotAsset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Add or refine a project-level design system mockup — the shared layout template or a named component specimen. Use `name: layout` for the page chrome (nav, sidebar, footer) that wraps page mockups at preview time; use any other name (e.g. `forms`, `navigation`, `data-display`, `typography`) for a component specimen the AI reads as context when generating page mockups. Layout HTML must include `<div id="growth-content"></div>` as the content slot. Component specimens should show the component in all relevant states.')]
class UpsertProjectMockup extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'name' => 'required|string|max:255',
            'html' => 'required|string',
        ]);

        Project::findOrFail($data['project_id']);

        $mockup = Mockup::firstOrCreate([
            'owner_type' => 'project',
            'owner_id' => $data['project_id'],
            'name' => $data['name'],
        ]);
        $created = $mockup->wasRecentlyCreated;

        $revision = $mockup->appendRevision($data['html']);

        return Response::structured([
            'id' => $mockup->id,
            'project_id' => $data['project_id'],
            'name' => $mockup->name,
            'revision' => $revision->number,
            'revision_id' => $revision->id,
            'created' => $created,
            'warnings' => $this->warnings($data['name'], $data['html']),
            'resources' => [
                'list_uri' => "growth://projects/{$data['project_id']}/mockups",
                'html_uri' => "growth://projects/{$data['project_id']}/mockups/{$mockup->name}",
                'revision_html_uri' => "growth://mockups/{$mockup->id}/{$revision->id}/html",
                'guidance' => 'Read list_uri to see all project design system mockups. Read html_uri for the latest HTML of this named mockup. Read revision_html_uri for this specific revision\'s HTML.',
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'name' => $schema->string()->description('Name of the design system artifact. Use "layout" for the page chrome template (must include `<div id="growth-content"></div>`); use any other name (e.g. "forms", "navigation", "data-display") for a named component specimen.')->required(),
            'html' => $schema->string()->description('Self-contained HTML document. For the layout: include `<div id="growth-content"></div>` as the slot where page mockup content is injected at preview time. For specimens: show the component in all relevant states.')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'revision' => $schema->integer()->description('Number of the revision this call appended')->required(),
            'revision_id' => $schema->string()->description('ULID of the revision this call appended')->required(),
            'created' => $schema->boolean()->description('Whether this call created the mockup')->required(),
            'warnings' => $schema->array()->description('Non-blocking quality warnings')->required(),
            'resources' => $schema->object()->description('Resource URIs for this design system mockup')->required(),
        ];
    }

    /**
     * @return list<array{code:string,message:string}>
     */
    private function warnings(string $name, string $html): array
    {
        $warnings = [];

        if (preg_match('/<(?:script|link|img|iframe|video|audio|source)\b[^>]+\b(?:src|href)\s*=\s*["\'](?:https?:)?\/\//i', $html) === 1
            || preg_match('/url\(\s*["\']?(?:https?:)?\/\//i', $html) === 1) {
            $warnings[] = [
                'code' => 'external_assets',
                'message' => 'Design system mockup HTML references external scripts, styles, or assets. Keep design system artifacts self-contained with inline CSS/JS and embedded assets when possible.',
            ];
        }

        if ($name === 'layout' && ! str_contains($html, 'id="growth-content"')) {
            $warnings[] = [
                'code' => 'missing_content_slot',
                'message' => 'Layout mockup is missing the required content slot: <div id="growth-content"></div>. Without it, page mockups will not be injected into the layout at preview time.',
            ];
        }

        return $warnings;
    }
}
