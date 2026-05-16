<?php

namespace App\Mcp\Tools\Verification;

use App\Models\Anomaly;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create or update an anomaly found during verification or delivery. New anomalies start as `open`; status is not set here — it moves only through the start-anomaly-investigation, resolve-anomaly, close-anomaly, and reopen-anomaly transitions.')]
class UpsertAnomaly extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'id' => 'nullable|string|owned_anomaly',
            'project_id' => 'required|string|owned_project',
            'test_run_id' => 'nullable|string|owned_test_run',
            'severity' => 'required|string|in:'.implode(',', Anomaly::SEVERITIES),
            'status' => 'prohibited',
            'summary' => 'required|string|max:255',
            'description' => 'required|string',
            'environment' => 'nullable|string',
            'affects_requirement_ids' => 'nullable|array',
            'affects_requirement_ids.*' => 'string|owned_requirement',
        ], [
            'status.prohibited' => 'Anomaly status is not set here. Use the start-anomaly-investigation, resolve-anomaly, close-anomaly, and reopen-anomaly tools to move status through validated transitions.',
        ]);

        $id = $data['id'] ?? null;
        $affectedIds = $data['affects_requirement_ids'] ?? null;
        unset($data['id'], $data['affects_requirement_ids']);

        $anomaly = DB::transaction(function () use ($id, $data, $affectedIds) {
            $anomaly = $id
                ? tap(Anomaly::findOrFail($id))->update($data)
                : Anomaly::create($data + ['status' => 'open']);
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
            'requirements_linked' => $anomaly->affectedRequirements()->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Existing anomaly ULID. Omit to create.'),
            'project_id' => $schema->string()->description('Project ULID')->required(),
            'test_run_id' => $schema->string()->description('Verification run ULID that surfaced this anomaly'),
            'severity' => $schema->string()->description('Anomaly severity')->enum(Anomaly::SEVERITIES)->required(),
            'summary' => $schema->string()->description('Short summary')->required(),
            'description' => $schema->string()->description('Full description')->required(),
            'environment' => $schema->string()->description('Where it was observed'),
            'affects_requirement_ids' => $schema->array()->description('Requirement ULIDs affected by this anomaly'),
        ];
    }
}
