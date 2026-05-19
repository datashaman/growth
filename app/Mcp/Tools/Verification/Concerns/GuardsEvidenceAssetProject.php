<?php

namespace App\Mcp\Tools\Verification\Concerns;

use App\Models\EvidenceAsset;
use Illuminate\Validation\ValidationException;

trait GuardsEvidenceAssetProject
{
    /**
     * Reject evidence assets that belong to a different project than the
     * verification run they are being cited on.
     *
     * `owned_evidence_asset` only proves workspace ownership, so without this
     * a screenshot from a sibling project in the same workspace could be
     * attached to a run and spuriously satisfy that project's rigor-3+
     * visual-evidence readiness check.
     *
     * @param  list<string>  $evidenceAssetIds
     */
    protected function assertEvidenceAssetsBelongToProject(?string $projectId, array $evidenceAssetIds): void
    {
        if ($evidenceAssetIds === []) {
            return;
        }

        $foreign = EvidenceAsset::query()
            ->whereKey($evidenceAssetIds)
            ->with('deliveryLink.workItem')
            ->get()
            ->reject(fn (EvidenceAsset $asset): bool => $asset->deliveryLink?->workItem?->project_id === $projectId)
            ->pluck('id')
            ->all();

        if ($foreign !== []) {
            throw ValidationException::withMessages([
                'evidence_asset_ids' => 'Evidence assets must belong to the same project as the verification run: '.implode(', ', $foreign),
            ]);
        }
    }
}
