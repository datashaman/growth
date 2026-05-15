<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMembership extends Model
{
    public const ROLES = ['owner', 'admin', 'member', 'viewer'];

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    public const ROLE_VIEWER = 'viewer';

    protected $fillable = ['workspace_id', 'user_id', 'role', 'last_accessed_at'];

    protected function casts(): array
    {
        return [
            'last_accessed_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canMutate(): bool
    {
        return in_array($this->role, [self::ROLE_OWNER, self::ROLE_ADMIN, self::ROLE_MEMBER], true);
    }
}
