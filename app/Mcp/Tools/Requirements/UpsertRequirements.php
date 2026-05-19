<?php

namespace App\Mcp\Tools\Requirements;

use App\Growth\Alignment\AlignmentText;
use App\Growth\Lint\RequirementLinter;
use App\Models\Requirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Create or update up to 100 requirements in one call. Each item is committed independently — per-item validation or runtime failures are reported alongside successes without aborting the batch and without rolling back already-applied items.')]
class UpsertRequirements extends Tool
{
    public function __construct(private readonly RequirementLinter $linter) {}

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

            $payload = [
                'project_id' => $data['project_id'],
                'parent_id' => $data['parent_id'] ?? null,
                'doc' => AlignmentText::layerToDoc($data['layer']),
                'type' => $data['type'],
                'text' => $data['text'],
                'rationale' => $data['rationale'] ?? null,
                'acceptance_criteria' => $data['acceptance_checks'] ?? null,
                'source' => $data['source'] ?? null,
                'priority' => $data['priority'] ?? 'medium',
                'tags' => $data['tags'] ?? null,
            ];

            // Only write renders_ui when supplied — an omitted flag must not
            // silently clear it on update (the column defaults to false).
            if (array_key_exists('renders_ui', $data)) {
                $payload['renders_ui'] = $data['renders_ui'];
            }

            $requirement = $id
                ? tap(Requirement::findOrFail($id))->update($payload)
                : Requirement::create($payload);

            return [
                'index' => $index,
                'ok' => true,
                'id' => $requirement->id,
                'created' => $requirement->wasRecentlyCreated,
                'layer' => AlignmentText::docToLayer($requirement->doc),
                'findings' => AlignmentText::sanitizeArray($this->linter->check($requirement->fresh())),
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
            'id' => 'nullable|string|owned_requirement',
            'project_id' => 'required|string|owned_project',
            'parent_id' => 'nullable|string|owned_requirement',
            'layer' => 'required|in:stakeholder,system,software',
            'type' => 'required|in:functional,performance,usability,interface,design_constraint,process,non_functional',
            'text' => 'required|string|min:5',
            'rationale' => 'nullable|string',
            'acceptance_checks' => 'nullable|array',
            'acceptance_checks.*' => 'string|min:3',
            'source' => 'nullable|string|max:255',
            'priority' => 'nullable|in:high,medium,low',
            'tags' => 'nullable|array',
            'renders_ui' => 'sometimes|boolean',
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object(fn (JsonSchema $s) => [
                    'id' => $s->string()->description('Existing requirement ULID. Omit to create new.'),
                    'project_id' => $s->string()->description('Project ULID')->required(),
                    'parent_id' => $s->string()->description('Parent requirement ULID for derived requirements'),
                    'layer' => $s->string()->description('Requirement layer')->enum(['stakeholder', 'system', 'software'])->required(),
                    'type' => $s->string()->description('Requirement type')->enum(['functional', 'performance', 'usability', 'interface', 'design_constraint', 'process', 'non_functional'])->required(),
                    'text' => $s->string()->description('Requirement statement')->required(),
                    'rationale' => $s->string()->description('Why this requirement matters'),
                    'acceptance_checks' => $s->array()->description('Concrete pass/fail checks for acceptance'),
                    'source' => $s->string()->description('Originating stakeholder, source, or decision'),
                    'priority' => $s->string()->description('Delivery priority')->enum(['high', 'medium', 'low']),
                    'tags' => $s->array()->description('Free-form tags'),
                    'renders_ui' => $s->boolean()->description('Whether this requirement renders UI — when true, a rigor-3+ readiness check warns until a passing verification run carries visual evidence. Defaults to false.'),
                ]))
                ->min(1)
                ->max(100)
                ->description('Up to 100 requirements to create or update. Items are committed independently; per-item failures are reported in the response without aborting the batch.')
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items($schema->object(fn (JsonSchema $s) => [
                    'index' => $s->integer()->required(),
                    'ok' => $s->boolean()->required(),
                    'id' => $s->string()->description('Present on success'),
                    'created' => $s->boolean()->description('Present on success'),
                    'layer' => $s->string()->description('Present on success'),
                    'findings' => $s->array()->description('Requirement quality findings; present on success, empty array means clean'),
                    'error' => $s->string()->description('Present on unexpected failure'),
                    'errors' => $s->object()->description('Per-field validation errors; present on validation failure'),
                ]))
                ->required(),
        ];
    }
}
