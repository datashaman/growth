<?php

namespace App\Mcp\Tools\Verification;

use App\Models\TestRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Record a verification run for a verification case.')]
class LogVerificationRun extends Tool
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
            'test_case_id' => $schema->string()->description('Verification case ULID')->required(),
            'status' => $schema->string()->description('Run outcome')->enum(TestRun::STATUSES)->required(),
            'run_at' => $schema->string()->description('Timestamp; defaults to now when omitted'),
            'notes' => $schema->string()->description('Execution notes'),
            'environment_snapshot' => $schema->object()->description('Captured environment state at execution time'),
        ];
    }
}
