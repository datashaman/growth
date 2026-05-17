<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsWorkspaceChanges;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
