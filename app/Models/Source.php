<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    use HasUlids;
    use ScopedByOwner;

    protected $fillable = [
        'project_id', 'kind', 'title', 'body', 'uri', 'external_ref',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function citations(): HasMany
    {
        return $this->hasMany(Citation::class);
    }
}
