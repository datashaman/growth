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

    /**
     * The immutable snapshot stored on a baseline: the plan's narrative fields
     * plus its project's work-breakdown state, in a stable order.
     *
     * @return array<string, mixed>
     */
    public function baselineSnapshot(): array
    {
        return [
            'project_plan' => [
                'id' => $this->id,
                'project_id' => $this->project_id,
                'status' => $this->status,
                'scope_summary' => $this->scope_summary,
                'objectives' => $this->objectives,
                'deliverables_summary' => $this->deliverables_summary,
                'approach' => $this->approach,
                'organization_summary' => $this->organization_summary,
                'assumptions' => $this->assumptions,
                'constraints' => $this->constraints,
                'budget_summary' => $this->budget_summary,
            ],
            'work_items' => $this->project->workItems()
                ->inWbsOrder()
                ->orderBy('id')
                ->get(['id', 'parent_id', 'responsible_role_id', 'kind', 'name', 'sort_order', 'status'])
                ->map(fn ($w) => [
                    'id' => $w->id,
                    'parent_id' => $w->parent_id,
                    'responsible_role_id' => $w->responsible_role_id,
                    'kind' => $w->kind,
                    'name' => $w->name,
                    'sort_order' => $w->sort_order,
                    'status' => $w->status,
                ])->all(),
        ];
    }
}
