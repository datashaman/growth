<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Mockup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the mockups on a work item, requirement, or project — the default mockup and any named alternatives held against it. The HTML is omitted; fetch a single mockup to see it.')]
class ListMockups extends Tool
{
    use ResolvesMockupOwner;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'owner_type' => 'required|string|in:project,work_item,requirement',
            'owner_id' => ['required', 'string', $this->ownerExistsRule($request->get('owner_type'))],
        ]);

        $mockups = Mockup::where('owner_type', $data['owner_type'])
            ->where('owner_id', $data['owner_id'])
            ->orderBy('name')
            ->get();

        return Response::structured([
            'owner_type' => $data['owner_type'],
            'owner_id' => $data['owner_id'],
            'total' => $mockups->count(),
            'results' => $mockups->map(fn (Mockup $mockup): array => [
                'id' => $mockup->id,
                'name' => $mockup->name,
                'updated_at' => $mockup->updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->enum(['project', 'work_item', 'requirement'])->description('The owner type whose mockups to list')->required(),
            'owner_id' => $schema->string()->description('ULID of the owner')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->required(),
            'owner_id' => $schema->string()->required(),
            'total' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
