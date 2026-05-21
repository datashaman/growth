<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleCapability extends Model
{
    use HasUlids;

    protected $fillable = ['role_id', 'capability'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
