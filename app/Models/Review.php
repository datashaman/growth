<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Review extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;
    use ScopedByOwner;

    public const TYPES = ['management_review', 'technical_review', 'inspection', 'walkthrough', 'audit'];

    public const STATUSES = ['planned', 'in_progress', 'held', 'closed', 'cancelled'];

    public const DECISIONS = ['accepted', 'accepted_with_actions', 'rework_required', 'rejected', 'deferred'];

    protected $fillable = [
        'project_id', 'review_plan_id', 'owner_role_id', 'type', 'title',
        'objective', 'status', 'planned_at', 'held_at', 'entry_criteria',
        'exit_criteria', 'decision', 'summary',
    ];

    protected $casts = [
        'planned_at' => 'datetime',
        'held_at' => 'datetime',
        'entry_criteria' => 'array',
        'exit_criteria' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function ownerRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'owner_role_id');
    }

    public function reviewPlan(): BelongsTo
    {
        return $this->belongsTo(ReviewPlan::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(ReviewTarget::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ReviewParticipant::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class);
    }

    public function decisionEvents(): HasMany
    {
        return $this->hasMany(ReviewDecisionEvent::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }
}
