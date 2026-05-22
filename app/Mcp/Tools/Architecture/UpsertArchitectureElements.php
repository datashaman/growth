<?php

namespace App\Mcp\Tools\Architecture;

use App\Models\DesignElement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Throwable;

#[IsDestructive(false)]
#[Description('Create or update up to 100 architecture elements in one call. Before generating element artifacts, inspect the parent view, addressed concerns, related requirements, existing elements, and source citations so each element preserves useful design context. Each item is committed independently — per-item validation or runtime failures are reported alongside successes without aborting the batch and without rolling back already-applied items.')]
class UpsertArchitectureElements extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $payload = $request->validate([
            'items' => 'required|array|min:1|max:100',
        ], [
            'items.max' => 'Batches are capped at 100 items per call. Split into smaller batches.',
        ]);

        $results = [];
        foreach ($payload['items'] as $index => $item) {
            $results[] = $this->upsertItem((int) $index, is_array($item) ? $item : []);
        }

        return Response::structured(['items' => $results]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function upsertItem(int $index, array $item): array
    {
        try {
            $data = Validator::make($item, $this->itemRules())->validate();
        } catch (ValidationException $e) {
            return [
                'index' => $index,
                'ok' => false,
                'errors' => $e->errors(),
            ];
        }

        try {
            $id = $data['id'] ?? null;
            unset($data['id']);

            $element = $id
                ? tap(DesignElement::findOrFail($id))->update($data)
                : DesignElement::create($data);

            return [
                'index' => $index,
                'ok' => true,
                'id' => $element->id,
                'design_view_id' => $element->design_view_id,
                'kind' => $element->kind,
                'name' => $element->name,
                'created' => $element->wasRecentlyCreated,
            ];
        } catch (Throwable $e) {
            return [
                'index' => $index,
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, string>
     */
    private function itemRules(): array
    {
        return [
            'id' => 'nullable|string|owned_design_element',
            'design_view_id' => 'required|string|owned_design_view',
            'kind' => 'required|in:entity,relationship,attribute,constraint',
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'purpose' => 'nullable|string',
            'properties' => 'nullable|array',
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object(fn (JsonSchema $s) => [
                    'id' => $s->string()->description('Existing architecture element ULID. Omit to create.'),
                    'design_view_id' => $s->string()->description('Parent architecture view ULID')->required(),
                    'kind' => $s->string()->description('Element kind')->enum(['entity', 'relationship', 'attribute', 'constraint'])->required(),
                    'name' => $s->string()->description('Element name')->required(),
                    'type' => $s->string()->description('Element type within the viewpoint vocabulary'),
                    'purpose' => $s->string()->description('Why this element exists, grounded in the relevant view, concern, requirement, or source context'),
                    'properties' => $s->object()->description('Kind-specific structured data that helps future agents preserve design intent'),
                ]))
                ->min(1)
                ->max(100)
                ->description('Up to 100 architecture elements to create or update. Items are committed independently; per-item failures are reported in the response without aborting the batch.')
                ->required(),
        ];
    }
}
