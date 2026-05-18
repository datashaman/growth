<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsWorkspaceChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ToolFeedback extends Model
{
    use BroadcastsWorkspaceChanges;
    use HasUlids;

    public const CATEGORIES = ['difficulty', 'suggestion', 'bug', 'missing_capability'];

    public const STATUSES = ['new', 'triaged', 'resolved'];

    protected $table = 'tool_feedback';

    protected $fillable = [
        'workspace_id', 'user_id', 'agent_id', 'project_id',
        'category', 'status', 'tool_name', 'summary', 'body',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function statusTransitions(): MorphMany
    {
        return $this->morphMany(StatusTransition::class, 'transitionable');
    }

    /**
     * @return HasMany<FeedbackComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackComment::class);
    }

    /**
     * The users on this feedback's thread: the filer and everyone who has
     * commented. Agent-attributed participants have no user account and are
     * absent — they cannot be reached on the notification rail.
     *
     * Call this after persisting a new comment: it reads the comments table
     * live, so a freshly-saved author is only in the set once the row exists.
     * The set always includes the latest commenter — a caller notifying a
     * thread must filter the acting author out itself.
     *
     * @return Collection<int, User>
     */
    public function commentParticipants(): Collection
    {
        $userIds = $this->comments()->whereNotNull('user_id')->pluck('user_id');

        if ($this->user_id !== null) {
            $userIds->push($this->user_id);
        }

        return User::whereIn('id', $userIds->unique())->get();
    }
}
