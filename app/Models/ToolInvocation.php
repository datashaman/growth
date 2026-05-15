<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ToolInvocation extends Model
{
    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'agent_id',
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
}
