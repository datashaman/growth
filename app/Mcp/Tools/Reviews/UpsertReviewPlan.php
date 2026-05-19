<?php

namespace App\Mcp\Tools\Reviews;

use App\Models\Review;
use App\Models\ReviewParticipant;
use App\Models\ReviewPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive(false)]
#[Description('Create or update a reusable review review plan with objective, procedure, entry/exit criteria, expected responsibilities, and checklist.')]
class UpsertReviewPlan extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_review_plan',
            'project_id' => 'required|string|owned_project',
            'type' => 'required|in:'.implode(',', Review::TYPES),
            'name' => 'required|string|max:255',
            'objective' => 'nullable|string',
            'procedure' => 'nullable|string',
            'entry_criteria' => 'nullable|array',
            'entry_criteria.*' => 'string',
            'exit_criteria' => 'nullable|array',
            'exit_criteria.*' => 'string',
            'expected_responsibilities' => 'nullable|array',
            'expected_responsibilities.*' => 'in:'.implode(',', ReviewParticipant::RESPONSIBILITIES),
            'checklist' => 'nullable|array',
            'checklist.*' => 'string',
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $plan = $id
            ? tap(ReviewPlan::findOrFail($id))->update($data)
            : ReviewPlan::create($data);

        return Response::structured([
            'id' => $plan->id,
            'project_id' => $plan->project_id,
            'type' => $plan->type,
            'name' => $plan->name,
            'entry_criteria' => count($plan->entry_criteria ?? []),
            'exit_criteria' => count($plan->exit_criteria ?? []),
            'expected_responsibilities' => count($plan->expected_responsibilities ?? []),
            'checklist' => count($plan->checklist ?? []),
            'created' => $plan->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing review plan ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'type' => $schema->string()->description('Review type')->enum(Review::TYPES)->required(),
            'name' => $schema->string()->description('Plan name')->required(),
            'objective' => $schema->string()->description('Review objective/scope'),
            'procedure' => $schema->string()->description('Review procedure'),
            'entry_criteria' => $schema->array()->description('Default entry criteria checklist'),
            'exit_criteria' => $schema->array()->description('Default exit criteria checklist'),
            'expected_responsibilities' => $schema->array()->description('Expected participant responsibilities'),
            'checklist' => $schema->array()->description('Review checklist items'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'type' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'entry_criteria' => $schema->integer()->required(),
            'exit_criteria' => $schema->integer()->required(),
            'expected_responsibilities' => $schema->integer()->required(),
            'checklist' => $schema->integer()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
