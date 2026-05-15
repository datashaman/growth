<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsReviewChanges;
use App\Models\Concerns\BroadcastsViaProjectRelation;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReviewFinding extends Model
{
    use BroadcastsReviewChanges;
    use BroadcastsViaProjectRelation;
    use HasUlids;
    use ScopedByOwner;

    public function projectIdForBroadcast(): ?string
    {
        return $this->getAttribute('project_id');
    }

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['open', 'dispositioned', 'resolved', 'accepted', 'closed'];

    protected $fillable = [
        'project_id', 'review_id', 'owner_role_id', 'reviewable_type',
        'reviewable_id', 'title', 'description', 'severity', 'status',
        'due_at', 'disposition',
    ];

    protected $casts = [
        'due_at' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function ownerRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'owner_role_id');
    }

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }
}
