<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ReviewPlan extends Model
{
    use HasUlids;
    use ScopedByOwner;

    protected $fillable = [
        'project_id', 'type', 'name', 'objective', 'procedure',
        'entry_criteria', 'exit_criteria', 'expected_responsibilities',
        'checklist',
    ];

    protected $casts = [
        'entry_criteria' => 'array',
        'exit_criteria' => 'array',
        'expected_responsibilities' => 'array',
        'checklist' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }
}
