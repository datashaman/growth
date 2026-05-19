<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsWorkspaceChanges;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolInvocation extends Model
{
    use BroadcastsWorkspaceChanges, HasUlids, MassPrunable;

    /**
     * Rows older than this are mass-pruned. Any metric derived from
     * tool_invocations is therefore a trailing window of this many days,
     * never a lifetime total.
     */
    public const PRUNE_AFTER_DAYS = 90;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'agent_id',
        'acting_role',
        'tool_name',
        'transport',
        'success',
        'error_class',
        'error_message',
        'duration_ms',
        'args_shape',
        'return_shape',
        'args_full',
        'return_full',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'duration_ms' => 'integer',
        'args_shape' => 'array',
        'return_shape' => 'array',
        'args_full' => 'array',
        'return_full' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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

    public function prunable(): Builder
    {
        return static::where('started_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
