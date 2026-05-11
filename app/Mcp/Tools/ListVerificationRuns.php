<?php

namespace App\Mcp\Tools;

use App\Models\TestRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List verification runs for one verification case.')]
class ListVerificationRuns extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'test_case_id' => 'required|string|owned_test_case',
            'status' => 'nullable|string|in:'.implode(',', TestRun::STATUSES),
        ]);

        $query = TestRun::query()->where('test_case_id', $data['test_case_id']);
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return Response::structured([
            'results' => $query->orderByDesc('run_at')->get()->map(fn ($run) => [
                'id' => $run->id,
                'status' => $run->status,
                'run_at' => $run->run_at?->toIso8601String(),
                'notes' => $run->notes,
                'environment_snapshot' => $run->environment_snapshot,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'test_case_id' => $schema->string()->description('Verification case ULID')->required(),
            'status' => $schema->string()->description('Filter by run outcome')->enum(TestRun::STATUSES),
        ];
    }
}
