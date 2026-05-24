<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Mockup;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete all spec mockups for one work item or requirement owner in a single explicit operation. This is owner-scoped cleanup for regenerating a mockup set; it never crosses owner or project boundaries.')]
class DeleteOwnerMockups extends Tool
{
    use ResolvesMockupOwner;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'owner_type' => 'required|string|in:work_item,requirement',
            'owner_id' => ['required', 'string', $this->ownerExistsRule($request->get('owner_type'))],
        ]);

        $mockups = Mockup::where('owner_type', $data['owner_type'])
            ->where('owner_id', $data['owner_id'])
            ->withCount('revisions')
            ->get();

        $mockupCount = $mockups->count();
        $revisionCount = (int) $mockups->sum('revisions_count');

        foreach ($mockups as $mockup) {
            $mockup->delete();
        }

        return Response::structured([
            'owner_type' => $data['owner_type'],
            'owner_id' => $data['owner_id'],
            'deleted_mockups' => $mockupCount,
            'deleted_revisions' => $revisionCount,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->enum(['work_item', 'requirement'])->description('The spec entity whose complete mockup set should be deleted')->required(),
            'owner_id' => $schema->string()->description('ULID of the work item or requirement owner')->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'owner_type' => $schema->string()->required(),
            'owner_id' => $schema->string()->required(),
            'deleted_mockups' => $schema->integer()->required(),
            'deleted_revisions' => $schema->integer()->required(),
        ];
    }
}
