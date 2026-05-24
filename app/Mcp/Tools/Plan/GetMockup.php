<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Mockup;
use App\Support\MockupScreenshotAsset;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("Resolve a spec mockup by work item or requirement and return its metadata resource URIs. Without `name` returns the owner's default mockup; pass `name` to fetch a named alternative.")]
class GetMockup extends Tool
{
    use ResolvesMockupOwner;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'owner_type' => 'required|string|in:work_item,requirement',
            'owner_id' => ['required', 'string', $this->ownerExistsRule($request->get('owner_type'))],
            'name' => 'sometimes|string|max:255',
        ]);

        $hasExplicitName = array_key_exists('name', $data);
        $name = $data['name'] ?? Mockup::DEFAULT_NAME;

        $mockup = Mockup::with('currentRevision')
            ->where('owner_type', $data['owner_type'])
            ->where('owner_id', $data['owner_id'])
            ->where('name', $name)
            ->first();

        if (! $mockup && ! $hasExplicitName) {
            $mockups = Mockup::with('currentRevision')
                ->where('owner_type', $data['owner_type'])
                ->where('owner_id', $data['owner_id'])
                ->orderBy('name')
                ->get();

            if ($mockups->count() === 1) {
                $mockup = $mockups->first();
            } elseif ($mockups->count() > 1) {
                return new ResponseFactory(Response::error(sprintf(
                    'No default mockup found on this %s. Available mockups: %s.',
                    $data['owner_type'],
                    $mockups->pluck('name')->map(fn (string $name): string => "[{$name}]")->implode(', ')
                )));
            }
        }

        if (! $mockup) {
            return new ResponseFactory(Response::error(
                "No mockup named [{$name}] found on this {$data['owner_type']}."
            ));
        }

        if (! $mockup->currentRevision) {
            return new ResponseFactory(Response::error("Mockup [{$mockup->id}] has no revisions yet."));
        }

        return Response::structured([
            'id' => $mockup->id,
            'owner_type' => $mockup->owner_type,
            'owner_id' => $mockup->owner_id,
            'name' => $mockup->name,
            'revision' => $mockup->currentRevision->number,
            'revision_id' => $mockup->currentRevision->id,
            'revision_created_at' => $mockup->currentRevision->created_at?->toIso8601String(),
            'resources' => [
                'mockup_uri' => "growth://mockups/{$mockup->id}",
                'revision_uri' => "growth://mockups/{$mockup->id}/{$mockup->currentRevision->id}",
                'html_uri' => "growth://mockups/{$mockup->id}/{$mockup->currentRevision->id}/html",
                'preview_uri' => "growth://mockups/{$mockup->id}/{$mockup->currentRevision->id}/preview",
                'screenshot_asset' => app(MockupScreenshotAsset::class)->reference($mockup, $mockup->currentRevision),
                'guidance' => 'Read mockup_uri or revision_uri for JSON metadata. Read html_uri for raw HTML, preview_uri for theme-aware preview HTML, and screenshot_asset when visual pixels are needed.',
            ],
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->enum(['work_item', 'requirement'])->description('The spec entity that owns the mockup')->required(),
            'owner_id' => $schema->string()->description('ULID of the work item or requirement that owns the mockup')->required(),
            'name' => $schema->string()->description('Optional mockup label. Omit to fetch the owner\'s default mockup.'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'owner_type' => $schema->string()->required(),
            'owner_id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'revision' => $schema->integer()->description('Number of the current revision')->required(),
            'revision_id' => $schema->string()->description('ULID of the current revision')->required(),
            'revision_created_at' => $schema->string()->description('ISO 8601 timestamp the current revision was written')->required(),
            'resources' => $schema->object()->description('Resource URIs for mockup metadata, revision metadata, raw HTML, preview HTML, and an inspectable preview screenshot asset')->required(),
        ];
    }
}
