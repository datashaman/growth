<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsReviewChanges;
use App\Models\Concerns\BroadcastsViaProjectRelation;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewParticipant extends Model
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

    public const RESPONSIBILITIES = ['moderator', 'author', 'reviewer', 'recorder', 'auditor', 'observer', 'approver'];

    public const ATTENDANCE_STATUSES = ['invited', 'attended', 'absent', 'excused'];

    protected $fillable = [
        'review_id', 'role_id', 'responsibility', 'attendance_status',
        'signed_off_at', 'notes',
    ];

    protected $casts = [
        'signed_off_at' => 'datetime',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
