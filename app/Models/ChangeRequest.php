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
        'number' => 'integer',
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChangeRequest $changeRequest): void {
            if ($changeRequest->number === null) {
                $changeRequest->number = $changeRequest->nextNumberForProject();
            }
        });
    }

    /**
     * Human-readable per-project reference, e.g. "CR-009".
     */
    public function reference(): string
    {
        return 'CR-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Allocate the next sequential number within the project. Callers that
     * may run concurrently should wrap the create in a transaction so the
     * project row lock held here spans the insert.
     */
    protected function nextNumberForProject(): int
    {
        Project::whereKey($this->project_id)->lockForUpdate()->first();

        return (int) static::where('project_id', $this->project_id)->max('number') + 1;
    }

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
