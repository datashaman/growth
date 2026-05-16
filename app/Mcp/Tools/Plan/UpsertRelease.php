<?php

namespace App\Mcp\Tools\Plan;

use App\Models\Release;
use App\Models\WorkItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update a release record and optionally sync the work items included in that release.')]
class UpsertRelease extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_release',
            'project_id' => 'required|string|owned_project',
            'version' => 'required|string|max:120',
            'name' => 'nullable|string|max:255',
            'status' => 'nullable|in:'.implode(',', Release::STATUSES),
            'released_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'work_item_ids' => 'nullable|array',
            'work_item_ids.*' => 'string|owned_work_item',
        ]);

        $workItemIds = $data['work_item_ids'] ?? null;
        unset($data['work_item_ids']);

        if ($workItemIds !== null) {
            $this->assertWorkItemsBelongToProject($workItemIds, $data['project_id']);
        }

        $id = $data['id'] ?? null;
        unset($data['id']);

        $release = $id
            ? tap(Release::findOrFail($id))->update($data)
            : Release::updateOrCreate([
                'project_id' => $data['project_id'],
                'version' => $data['version'],
            ], $data);

        if ($workItemIds !== null) {
            $release->workItems()->sync($workItemIds);
        }

        return Response::structured([
            'id' => $release->id,
            'project_id' => $release->project_id,
            'version' => $release->version,
            'name' => $release->name,
            'status' => $release->status,
            'released_at' => $release->released_at?->toIso8601String(),
            'work_items' => $release->workItems()->count(),
            'created' => $release->wasRecentlyCreated,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing release ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'version' => $schema->string()->description('Release version, tag, or build identifier')->required(),
            'name' => $schema->string()->description('Optional release name'),
            'status' => $schema->string()->description('Release lifecycle status')->enum(Release::STATUSES),
            'released_at' => $schema->string()->description('Release timestamp'),
            'notes' => $schema->string()->description('Release notes'),
            'work_item_ids' => $schema->array()->description('Work item ULIDs included in this release'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'project_id' => $schema->string()->required(),
            'version' => $schema->string()->required(),
            'name' => $schema->string(),
            'status' => $schema->string()->required(),
            'released_at' => $schema->string(),
            'work_items' => $schema->integer()->required(),
            'created' => $schema->boolean()->required(),
        ];
    }

    /**
     * @param  list<string>  $workItemIds
     */
    private function assertWorkItemsBelongToProject(array $workItemIds, string $projectId): void
    {
        $count = WorkItem::query()
            ->where('project_id', $projectId)
            ->whereIn('id', $workItemIds)
            ->count();

        if ($count !== count(array_unique($workItemIds))) {
            throw ValidationException::withMessages([
                'work_item_ids' => 'All work items must belong to the release project.',
            ]);
        }
    }
}
