<?php

namespace App\Mcp\Tools\Test;

use App\Models\Anomaly;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Report an anomaly / defect (verification evidence rules Anomaly Report). Can be linked to the test run that surfaced it and to requirements it affects. Default status is open.')]
class ReportAnomaly extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'project_id' => 'required|string|owned_project',
            'test_run_id' => 'nullable|string|owned_test_run',
            'severity' => 'required|string|in:'.implode(',', Anomaly::SEVERITIES),
            'status' => 'nullable|string|in:'.implode(',', Anomaly::STATUSES),
            'summary' => 'required|string|max:255',
            'description' => 'required|string',
            'environment' => 'nullable|string',
            'affects_requirement_ids' => 'nullable|array',
            'affects_requirement_ids.*' => 'string|owned_requirement',
        ]);

        $affectedIds = $data['affects_requirement_ids'] ?? [];
        unset($data['affects_requirement_ids']);

        $anomaly = DB::transaction(function () use ($data, $affectedIds) {
            $a = Anomaly::create($data);
            if ($affectedIds !== []) {
                $a->affectedRequirements()->attach($affectedIds);
            }

            return $a;
        });

        return Response::structured([
            'id' => $anomaly->id,
            'severity' => $anomaly->severity,
            'status' => $anomaly->status,
            'requirements_linked' => count($affectedIds),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()
                ->description('Project ULID')
                ->required(),
            'test_run_id' => $schema->string()
                ->description('Optional test run that surfaced this anomaly'),
            'severity' => $schema->string()
                ->description('Anomaly severity')
                ->enum(Anomaly::SEVERITIES)
                ->required(),
            'status' => $schema->string()
                ->description('Lifecycle status (defaults to open)')
                ->enum(Anomaly::STATUSES),
            'summary' => $schema->string()
                ->description('Short one-line summary')
                ->required(),
            'description' => $schema->string()
                ->description('Full description: inputs, expected, actual, anomaly ')
                ->required(),
            'environment' => $schema->string()
                ->description('Where it was observed (versions, hardware, configs)'),
            'affects_requirement_ids' => $schema->array()
                ->description('Requirement ULIDs whose verification this anomaly impacts'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'severity' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'requirements_linked' => $schema->integer()->required(),
        ];
    }
}
