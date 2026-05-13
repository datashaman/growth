<?php

namespace App\Mcp\Tools;

use App\Models\Concern;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Create or update one or more stakeholder concerns that architecture and delivery work should address. Items run independently; per-item failures do not abort the batch.')]
class UpsertConcerns extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $payload = $request->validate([
            'items' => 'required|array|min:1',
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

            $concern = $id
                ? tap(Concern::findOrFail($id))->update($data)
                : Concern::create($data);

            return [
                'index' => $index,
                'ok' => true,
                'id' => $concern->id,
                'created' => $concern->wasRecentlyCreated,
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
            'id' => 'nullable|string|owned_concern',
            'project_id' => 'required|string|owned_project',
            'raised_by_stakeholder_id' => 'nullable|string|owned_stakeholder',
            'text' => 'required|string|min:3',
            'suggested_viewpoints' => 'nullable|array',
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object(fn (JsonSchema $s) => [
                    'id' => $s->string()->description('Existing concern ULID. Omit to create new.'),
                    'project_id' => $s->string()->description('Project ULID')->required(),
                    'raised_by_stakeholder_id' => $s->string()->description('Stakeholder ULID that raised this concern'),
                    'text' => $s->string()->description('Concern statement')->required(),
                    'suggested_viewpoints' => $s->array()->description('Optional architecture viewpoints that may address this concern'),
                ]))
                ->min(1)
                ->description('One or more concerns to create or update. Items are processed independently; per-item failures are reported in the response without aborting the batch.')
                ->required(),
        ];
    }
}
