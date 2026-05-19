<?php

namespace App\Mcp\Tools\Verification;

use App\Mcp\Tools\Verification\Concerns\GuardsEvidenceAssetProject;
use App\Models\TestCase;
use App\Models\TestRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Record a verification run for a verification case. Optionally attach visual evidence: pass evidence_asset_ids to cite screenshots (already uploaded to Growth) on the run — required for a UI-bearing requirement to pass the rigor-3+ visual-evidence readiness check.')]
class LogVerificationRun extends Tool
{
    use GuardsEvidenceAssetProject;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'test_case_id' => 'required|string|owned_test_case',
            'status' => 'required|string|in:'.implode(',', TestRun::STATUSES),
            'run_at' => 'nullable|date',
            'notes' => 'nullable|string',
            'environment_snapshot' => 'nullable|array',
            'evidence_asset_ids' => 'nullable|array',
            'evidence_asset_ids.*' => 'string|owned_evidence_asset',
        ]);

        $data['run_at'] ??= now();
        $evidenceAssetIds = $data['evidence_asset_ids'] ?? [];
        unset($data['evidence_asset_ids']);

        $this->assertEvidenceAssetsBelongToProject(
            TestCase::with('plan')->find($data['test_case_id'])?->plan?->project_id,
            $evidenceAssetIds,
        );

        $run = TestRun::create($data);

        if ($evidenceAssetIds !== []) {
            $run->evidenceAssets()->sync($evidenceAssetIds);
        }

        return Response::structured([
            'id' => $run->id,
            'test_case_id' => $run->test_case_id,
            'status' => $run->status,
            'run_at' => $run->run_at->toIso8601String(),
            'evidence_asset_count' => count($evidenceAssetIds),
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
            'evidence_asset_ids' => $schema->array()
                ->description('Evidence asset ULIDs to cite as visual evidence on this run')
                ->items($schema->string()),
        ];
    }
}
