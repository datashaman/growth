<?php

namespace App\Mcp\Tools\Plan;

use App\Models\DesignView;
use App\Models\SpecMockup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Add or refine a spec mockup on a work item or a requirement — a self-contained HTML page expressing a UI idea. Before generating the HTML, read the owner\'s mockup design brief (`growth://owners/{owner_type}/{owner_id}/mockup-design-brief`) so the artifact reflects requirements, existing mockups, and architecture context. Without `name` the owner\'s default mockup is updated in place; pass `name` to hold a named layout alternative alongside the default.')]
class UpsertMockup extends Tool
{
    use ResolvesMockupOwner;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'owner_type' => 'required|string|in:work_item,requirement',
            'owner_id' => ['required', 'string', $this->ownerExistsRule($request->get('owner_type'))],
            'name' => 'sometimes|string|max:255',
            'html' => 'required|string',
        ]);

        $mockup = SpecMockup::firstOrCreate([
            'owner_type' => $data['owner_type'],
            'owner_id' => $data['owner_id'],
            'name' => $data['name'] ?? SpecMockup::DEFAULT_NAME,
        ]);
        $created = $mockup->wasRecentlyCreated;

        $revision = $mockup->appendRevision($data['html']);
        $owner = self::OWNER_MODELS[$data['owner_type']]::findOrFail($data['owner_id']);

        return Response::structured([
            'id' => $mockup->id,
            'owner_type' => $mockup->owner_type,
            'owner_id' => $mockup->owner_id,
            'name' => $mockup->name,
            'revision' => $revision->number,
            'created' => $created,
            'design_brief' => $this->designBrief($owner->project_id, $data['owner_type'], $data['owner_id']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->enum(['work_item', 'requirement'])->description('The spec entity the mockup belongs to')->required(),
            'owner_id' => $schema->string()->description('ULID of the work item or requirement that owns the mockup')->required(),
            'name' => $schema->string()->description('Optional label for the mockup. Omit to update the owner\'s single default mockup in place; pass a value to hold a named alternative alongside the default.'),
            'html' => $schema->string()->description('A self-contained HTML document — the mockup. Inline the styles and scripts (CDN links are fine); it renders sandboxed, isolated from the Growth app. Read the owner\'s mockup design brief first; if the artifact diverges from that brief, make the mismatch visible in the mockup or its notes.')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'owner_type' => $schema->string()->required(),
            'owner_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'revision' => $schema->integer()->description('Number of the revision this call appended')->required(),
            'created' => $schema->boolean()->description('Whether this call created the mockup')->required(),
            'design_brief' => $schema->object()->description('Brief resource to read before generating or refining this mockup')->required(),
        ];
    }

    /**
     * @return array{uri:string,guidance:string,architecture_available:bool,architecture_views:list<array{id:string,viewpoint:string,name:string,elements_count:int}>}
     */
    private function designBrief(string $projectId, string $ownerType, string $ownerId): array
    {
        $views = DesignView::query()
            ->where('project_id', $projectId)
            ->withCount('elements')
            ->orderBy('viewpoint')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'project_id', 'viewpoint', 'name']);

        return [
            'uri' => "growth://owners/{$ownerType}/{$ownerId}/mockup-design-brief",
            'guidance' => $views->isNotEmpty()
                ? 'Read the mockup design brief before generating HTML; it bundles requirements, existing mockups, and architecture context for this owner.'
                : 'Read the mockup design brief before generating HTML; no architecture views are captured for this project yet, but owner requirements and existing mockups may still matter.',
            'architecture_available' => $views->isNotEmpty(),
            'architecture_views' => $views->map(fn (DesignView $view): array => [
                'id' => $view->id,
                'viewpoint' => $view->viewpoint,
                'name' => $view->name,
                'elements_count' => $view->elements_count,
            ])->all(),
        ];
    }
}
