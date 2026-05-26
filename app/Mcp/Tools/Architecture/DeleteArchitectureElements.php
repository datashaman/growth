<?php

namespace App\Mcp\Tools\Architecture;

use App\Models\DesignElement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Delete architecture elements by filter. Currently supports id=[...] for up to 100 architecture element ULIDs.')]
class DeleteArchitectureElements extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'required|array|min:1|max:100',
            'id.*' => 'required|string|distinct|owned_design_element',
        ], [
            'id.max' => 'Batches are capped at 100 ids per call. Split into smaller batches.',
        ]);

        $elements = DesignElement::whereIn('id', $data['id'])->get()->keyBy('id');

        $deleted = [];
        foreach ($data['id'] as $id) {
            /** @var DesignElement $element */
            $element = $elements->get($id);
            $element->delete();

            $deleted[] = [
                'id' => $id,
                'deleted' => true,
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
                ->description('Architecture element ULIDs to delete. This is the first supported delete filter: id=[...].')
                ->required(),
        ];
    }
}
