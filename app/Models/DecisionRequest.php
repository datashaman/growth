<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Database\Factories\DecisionRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A durable, role-routed clarifying question: a requester asks a project
 * {@see Role} to choose among options. See
 * docs/architecture/decision-request-primitive.md.
 */
class DecisionRequest extends Model
{
    use BroadcastsProjectChanges;

    /** @use HasFactory<DecisionRequestFactory> */
    use HasFactory;

    use HasUlids;
    use ScopedByOwner;

    public const STATUSES = ['open', 'answered', 'expired', 'cancelled'];

    protected $fillable = [
        'project_id', 'requester_user_id', 'target_role_id', 'question',
        'status', 'deadline', 'subjectable_type', 'subjectable_id',
        'chosen_option_id', 'answer_rationale', 'answered_by_user_id', 'answered_at',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'answered_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function targetRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'target_role_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by_user_id');
    }

    /**
     * The artifact the decision is about, if any.
     */
    public function subjectable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<DecisionRequestOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(DecisionRequestOption::class)->orderBy('position');
    }

    public function chosenOption(): BelongsTo
    {
        return $this->belongsTo(DecisionRequestOption::class, 'chosen_option_id');
    }

    public function statusTransitions(): MorphMany
    {
        return $this->morphMany(StatusTransition::class, 'transitionable');
    }
}
