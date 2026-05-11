<?php

namespace App\Mcp\Tools;

use App\Models\Anomaly;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update an anomaly found during verification or delivery.')]
class UpsertAnomaly extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_anomaly',
            'project_id' => 'required|string|owned_project',
            'test_run_id' => 'nullable|string|owned_test_run',
            'severity' => 'required|string|in:'.implode(',', Anomaly::SEVERITIES),
            'status' => 'nullable|string|in:'.implode(',', Anomaly::STATUSES),
            'summary' => 'required|string|max:255',
            'description' => 'required|string',
            'environment' => 'nullable|string',
            'affects_capability_ids' => 'nullable|array',
            'affects_capability_ids.*' => 'string|owned_requirement',
        ]);

        $id = $data['id'] ?? null;
        $affectedIds = $data['affects_capability_ids'] ?? null;
        unset($data['id'], $data['affects_capability_ids']);

        $anomaly = DB::transaction(function () use ($id, $data, $affectedIds) {
            $anomaly = $id ? tap(Anomaly::findOrFail($id))->update($data) : Anomaly::create($data);
            if (is_array($affectedIds)) {
                $anomaly->affectedRequirements()->sync($affectedIds);
            }

            return $anomaly;
        });

        return Response::structured([
            'id' => $anomaly->id,
            'severity' => $anomaly->severity,
            'status' => $anomaly->status,
            'created' => $anomaly->wasRecentlyCreated,
            'capabilities_linked' => $anomaly->affectedRequirements()->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing anomaly ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'test_run_id' => $schema->string()->description('Verification run ULID that surfaced this anomaly'),
            'severity' => $schema->string()->description('Anomaly severity')->enum(Anomaly::SEVERITIES)->required(),
            'status' => $schema->string()->description('Lifecycle status')->enum(Anomaly::STATUSES),
            'summary' => $schema->string()->description('Short summary')->required(),
            'description' => $schema->string()->description('Full description')->required(),
            'environment' => $schema->string()->description('Where it was observed'),
            'affects_capability_ids' => $schema->array()->description('Capability ULIDs affected by this anomaly'),
        ];
    }
}
