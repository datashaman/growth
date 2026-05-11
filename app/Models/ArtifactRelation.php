<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ArtifactRelation extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const RELATIONS = ['supersedes', 'replaces', 'duplicates', 'relates_to'];

    protected $fillable = [
        'project_id', 'source_artifact_type', 'source_artifact_id',
        'relation', 'target_artifact_type', 'target_artifact_id', 'rationale',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceArtifact(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_artifact_type', 'source_artifact_id');
    }

    public function targetArtifact(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'target_artifact_type', 'target_artifact_id');
    }
}
