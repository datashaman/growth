<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Milestone;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a milestone — a dated checkpoint with exit criteria. Work items can be linked to milestones via link-work-item-to-milestone.')]
class UpsertMilestone extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_milestone',
            'project_id' => 'required|string|owned_project',
            'name' => 'required|string|max:255',
            'target_date' => 'nullable|date_format:Y-m-d',
            'exit_criteria' => 'nullable|string',
            'status' => 'nullable|in:'.implode(',', Milestone::STATUSES),
        ]);

        $id = $data['id'] ?? null;
        unset($data['id']);

        $milestone = $id
            ? tap(Milestone::findOrFail($id))->update($data)
            : Milestone::create($data);

        return Response::structured([
            'id' => $milestone->id,
            'name' => $milestone->name,
            'target_date' => $milestone->target_date?->toDateString(),
            'status' => $milestone->status,
            'created' => $milestone->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('Existing milestone ULID. Omit to create.'),
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'name' => $schema->string()
                ->description('Milestone label — "Beta release", "Code freeze"')
                ->required(),
            'target_date' => $schema->string()
                ->description('Target date in YYYY-MM-DD format'),
            'exit_criteria' => $schema->string()
                ->description('What "hit" means for this milestone'),
            'status' => $schema->string()
                ->description('Tracking status')
                ->enum(Milestone::STATUSES),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'name' => $schema->string()->required(),
            'target_date' => $schema->string(),
            'status' => $schema->string()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }
}
