<?php

namespace App\Mcp\Tools\Requirements;

use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete requirements by filter. Currently supports id=[...] for up to 100 requirement ULIDs. Child requirements are detached from each deleted parent.')]
class DeleteRequirements extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|array|min:1|max:100',
            'id.*' => 'required|string|distinct|owned_requirement',
        ], [
            'id.max' => 'Batches are capped at 100 ids per call. Split into smaller batches.',
        ]);

        $requirements = Requirement::whereIn('id', $data['id'])->get()->keyBy('id');

        $deleted = [];
        foreach ($data['id'] as $id) {
            /** @var Requirement $requirement */
            $requirement = $requirements->get($id);
            $children = $requirement->children()->count();
            $requirement->delete();

            $deleted[] = [
                'id' => $id,
                'deleted' => true,
                'children_detached' => $children,
            ];
        }

        return Response::structured([
            'filters' => ['id' => $data['id']],
            'deleted_count' => count($deleted),
            'deleted' => $deleted,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->array()
                ->items($schema->string())
                ->min(1)
                ->max(100)
                ->description('Requirement ULIDs to delete. This is the first supported delete filter: id=[...].')
                ->required(),
        ];
    }
}
