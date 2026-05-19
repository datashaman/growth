<?php

namespace App\Mcp\Tools\Reviews;

use App\Models\Review;
use App\Models\ReviewPlan;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List reusable review plans for a project. Filterable by review type and substring.')]
class ListReviewPlans extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'type' => 'nullable|in:'.implode(',', Review::TYPES),
            'q' => 'nullable|string|max:255',
        ]);

        $query = ReviewPlan::query()
            ->where('project_id', $data['project_id'])
            ->withCount('reviews');

        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (isset($data['q'])) {
            $query->where('name', 'like', '%'.$data['q'].'%');
        }

        $rows = $query
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return Response::structured([
            'total' => $rows->count(),
            'results' => $rows->map(fn (ReviewPlan $plan) => [
                'id' => $plan->id,
                'type' => $plan->type,
                'name' => $plan->name,
                'objective' => $plan->objective,
                'entry_criteria' => $plan->entry_criteria ?? [],
                'exit_criteria' => $plan->exit_criteria ?? [],
                'expected_responsibilities' => $plan->expected_responsibilities ?? [],
                'checklist' => $plan->checklist ?? [],
                'reviews' => $plan->reviews_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'type' => $schema->string()->description('Filter by review type')->enum(Review::TYPES),
            'q' => $schema->string()->description('Substring match on plan name'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'total' => $schema->integer()->required(),
            'results' => $schema->array()->required(),
        ];
    }
}
