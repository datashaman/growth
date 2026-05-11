<?php

namespace App\Mcp\Tools\Reviews\Concerns;

use App\Growth\Artifacts\ArtifactRegistry;
use Illuminate\Database\Eloquent\Model;

trait ValidatesReviewArtifacts
{
    /**
     * @return array<string, class-string<Model>>
     */
    private function reviewableTypes(): array
    {
        return ArtifactRegistry::types();
    }

    private function validateReviewable(string $type, string $id): Model
    {
        return ArtifactRegistry::validate($type, $id, 'reviewable_type', 'reviewable_id');
    }
}
