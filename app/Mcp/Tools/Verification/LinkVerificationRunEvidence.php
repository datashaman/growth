<?php

namespace App\Mcp\Tools\Verification;

use App\Mcp\Tools\Verification\Concerns\GuardsEvidenceAssetProject;
use App\Models\TestRun;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Cite one or more evidence assets (screenshots already uploaded to Growth) as visual evidence on an existing verification run. Idempotent — pre-existing citations are kept, new ones are added. Use this when a run was logged before its screenshots existed; otherwise pass evidence_asset_ids to log-verification-run directly.')]
class LinkVerificationRunEvidence extends Tool
{
    use GuardsEvidenceAssetProject;

    public function handle(Request $request): ResponseFactory
    {
        $data = $request->validate([
            'test_run_id' => 'required|string|owned_test_run',
            'evidence_asset_ids' => 'required|array|min:1',
            'evidence_asset_ids.*' => 'required|string|owned_evidence_asset',
        ]);

        $run = TestRun::with('case.plan')->findOrFail($data['test_run_id']);
        $this->assertEvidenceAssetsBelongToProject($run->case?->plan?->project_id, $data['evidence_asset_ids']);

        $result = $run->evidenceAssets()->syncWithoutDetaching($data['evidence_asset_ids']);

        return Response::structured([
            'test_run_id' => $run->id,
            'attached' => count($result['attached']),
            'unchanged' => count($data['evidence_asset_ids']) - count($result['attached']),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'test_run_id' => $schema->string()
                ->description('Verification run ULID')
                ->required(),
            'evidence_asset_ids' => $schema->array()
                ->description('Evidence asset ULIDs to cite on the run')
                ->items($schema->string())
                ->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'test_run_id' => $schema->string()->required(),
            'attached' => $schema->integer()->required(),
            'unchanged' => $schema->integer()->required(),
        ];
    }
}
