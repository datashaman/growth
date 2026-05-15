<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ChangeRequest extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;
    use ScopedByOwner;

    public const CATEGORIES = ['scope', 'requirements', 'design', 'test', 'plan', 'risk', 'defect', 'compliance', 'other'];

    public const STATUSES = ['proposed', 'under_review', 'approved', 'rejected', 'deferred', 'implemented', 'cancelled'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const DECISIONS = ['approved', 'rejected', 'deferred'];

    protected $fillable = [
        'project_id', 'requester_role_id', 'review_id', 'title', 'description',
        'rationale', 'category', 'status', 'priority', 'decision',
        'decision_rationale', 'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requesterRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'requester_role_id');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function impacts(): HasMany
    {
        return $this->hasMany(ChangeImpact::class);
    }

    public function approvalEvents(): HasMany
    {
        return $this->hasMany(ChangeApprovalEvent::class);
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }
}
