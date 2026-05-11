<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Milestone extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const STATUSES = ['pending', 'hit', 'missed', 'deferred'];

    protected $fillable = [
        'project_id', 'name', 'target_date', 'exit_criteria', 'status',
    ];

    protected $casts = [
        'target_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }

    public function workItems(): BelongsToMany
    {
        return $this->belongsToMany(WorkItem::class, 'milestone_work_item');
    }
}
