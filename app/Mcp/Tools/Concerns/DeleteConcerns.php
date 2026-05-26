<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\Concern;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete stakeholder concerns by filter. Currently supports id=[...] for up to 100 concern ULIDs. Pivot rows linking deleted concerns to design views are removed, but no design views are deleted.')]
class DeleteConcerns extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|array|min:1|max:100',
            'id.*' => 'required|string|distinct|owned_concern',
        ], [
            'id.max' => 'Batches are capped at 100 ids per call. Split into smaller batches.',
        ]);

        $concerns = Concern::whereIn('id', $data['id'])->get()->keyBy('id');

        $deleted = [];
        foreach ($data['id'] as $id) {
            /** @var Concern $concern */
            $concern = $concerns->get($id);
            $unlinkedViews = $concern->designViews()->count();
            $concern->delete();

            $deleted[] = [
                'id' => $id,
                'deleted' => true,
                'views_unlinked' => $unlinkedViews,
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
                ->description('Concern ULIDs to delete. This is the first supported delete filter: id=[...].')
                ->required(),
        ];
    }
}
