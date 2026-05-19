<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StatusTransition extends Model
{
    use HasUlids;

    protected $fillable = [
        'from_status', 'to_status', 'reason',
        'transitioned_by_user_id', 'acting_surface',
        'acting_role_id', 'acting_role_name', 'transitioned_at',
    ];

    protected $casts = [
        'transitioned_at' => 'datetime',
    ];

    public function transitionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transitionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transitioned_by_user_id');
    }

    public function actingRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'acting_role_id');
    }
}
