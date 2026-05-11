<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stakeholder extends Model
{
    use HasUlids;
    use ScopedByOwner;

    protected $fillable = ['project_id', 'name', 'role', 'kind', 'description'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class, 'raised_by_stakeholder_id');
    }
}
