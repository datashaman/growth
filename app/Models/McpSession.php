<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Server-side MCP session state (#314, ADR-0002). One row per (transport
 * session id, authenticated user) pair, holding the project Role the session
 * has adopted. Written lazily on the first `adopt-role`; rows idle past
 * {@see self::PRUNE_AFTER_DAYS} are mass-pruned.
 */
class McpSession extends Model
{
    use HasFactory;
    use HasUlids;
    use MassPrunable;

    /**
     * Rows whose last activity is older than this are mass-pruned. A session
     * id is ephemeral; a stale binding should not outlive the connection.
     */
    public const PRUNE_AFTER_DAYS = 7;

    protected $fillable = [
        'mcp_session_id', 'user_id', 'role_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function prunable(): Builder
    {
        return static::where('updated_at', '<', now()->subDays(self::PRUNE_AFTER_DAYS));
    }
}
