<?php

namespace App\Mcp\Tools\Test;

use App\Models\TestRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Record a test run (verification evidence rules Level Test Log entry): status, when it ran, and any notes. Defaults run_at to now if omitted.')]
class LogTestRun extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'test_case_id' => 'required|string|owned_test_case',
            'status' => 'required|string|in:'.implode(',', TestRun::STATUSES),
            'run_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'environment_snapshot' => 'nullable|array',
        ]);

        $data['run_at'] ??= now();

        $run = TestRun::create($data);

        return Response::structured([
            'id' => $run->id,
            'test_case_id' => $run->test_case_id,
            'status' => $run->status,
            'run_at' => $run->run_at->toIso8601String(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'test_case_id' => $schema->string()
                ->description('Test case ULID')
                ->required(),
            'status' => $schema->string()
                ->description('Run outcome')
                ->enum(TestRun::STATUSES)
                ->required(),
            'run_at' => $schema->string()
                ->description('ISO 8601 timestamp; defaults to now if omitted'),
            'notes' => $schema->string()
                ->description('Free-form observations from execution'),
            'environment_snapshot' => $schema->object()
                ->description('Captured environment state at execution time (versions, configs)'),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required(),
            'test_case_id' => $schema->string()->required(),
            'status' => $schema->string()->required(),
            'run_at' => $schema->string()->required(),
        ];
    }
}
