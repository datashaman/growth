<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProjectPlan extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const STATUSES = ['draft', 'baselined', 'active', 'closed'];

    protected $fillable = [
        'project_id', 'status', 'scope_summary', 'objectives',
        'deliverables_summary', 'approach', 'organization_summary',
        'assumptions', 'constraints', 'budget_summary',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }

    public function baselines(): HasMany
    {
        return $this->hasMany(ProjectPlanBaseline::class);
    }

    public function statusTransitions(): MorphMany
    {
        return $this->morphMany(StatusTransition::class, 'transitionable');
    }
}
