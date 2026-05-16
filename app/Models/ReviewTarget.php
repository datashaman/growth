<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsReviewChanges;
use App\Models\Concerns\BroadcastsViaProjectRelation;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReviewTarget extends Model
{
    use BroadcastsReviewChanges;
    use BroadcastsViaProjectRelation;
    use HasUlids;
    use ScopedByOwner;

    public function projectIdForBroadcast(): ?string
    {
        return $this->review?->project_id;
    }

    public const OWNER_SCOPE_RELATION = 'review.project';

    protected $fillable = [
        'review_id', 'reviewable_type', 'reviewable_id', 'context',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}
