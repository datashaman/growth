<?php

namespace App\Mcp\Tools\Plan;

use App\Models\SpecMockup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add or refine a named spec mockup on a work item or a requirement — a self-contained HTML page expressing a UI idea. A new name creates the mockup; an existing name appends a revision, keeping the earlier rounds.')]
class UpsertMockup extends Tool
{
    use ResolvesMockupOwner;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'owner_type' => 'required|string|in:work_item,requirement',
            'owner_id' => ['required', 'string', $this->ownerExistsRule($request->get('owner_type'))],
            'name' => 'required|string|max:255',
            'html' => 'required|string',
        ]);

        $mockup = SpecMockup::firstOrCreate([
            'owner_type' => $data['owner_type'],
            'owner_id' => $data['owner_id'],
            'name' => $data['name'],
        ]);
        $created = $mockup->wasRecentlyCreated;

        $revision = $mockup->appendRevision($data['html']);

        return Response::structured([
            'id' => $mockup->id,
            'owner_type' => $mockup->owner_type,
            'owner_id' => $mockup->owner_id,
            'name' => $mockup->name,
            'revision' => $revision->number,
            'created' => $created,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->enum(['work_item', 'requirement'])->description('The spec entity the mockup belongs to')->required(),
            'owner_id' => $schema->string()->description('ULID of the work item or requirement that owns the mockup')->required(),
            'name' => $schema->string()->description('Short label for the mockup')->required(),
            'html' => $schema->string()->description('A self-contained HTML document — the mockup. Inline the styles and scripts (CDN links are fine); it renders sandboxed, isolated from the Growth app.')->required(),
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
        ];
    }
}
