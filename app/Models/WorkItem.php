<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkItem extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const KINDS = ['deliverable', 'work_package', 'task'];

    public const STATUSES = ['todo', 'in_progress', 'blocked', 'done', 'cancelled'];

    public const DEPENDENCY_KINDS = ['finish_to_start', 'start_to_start', 'finish_to_finish', 'start_to_finish'];

    public const RACI = ['r', 'a', 'c', 'i'];

    protected $fillable = [
        'project_id', 'parent_id', 'responsible_role_id', 'kind',
        'name', 'description', 'status', 'planned_start_date', 'due_date',
        'effort_estimate', 'effort_actual', 'effort_estimate_hours', 'effort_actual_hours', 'cost_estimate',
        'cost_actual', 'cost_estimate_amount', 'cost_actual_amount', 'cost_currency',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'due_date' => 'date',
        'effort_estimate_hours' => 'decimal:2',
        'effort_actual_hours' => 'decimal:2',
        'cost_estimate_amount' => 'decimal:2',
        'cost_actual_amount' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function responsibleRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'responsible_role_id');
    }

    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'requirement_work_item');
    }

    public function milestones(): BelongsToMany
    {
        return $this->belongsToMany(Milestone::class, 'milestone_work_item');
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'work_item_dependencies', 'work_item_id', 'depends_on_id')
            ->withPivot('kind')
            ->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'work_item_dependencies', 'depends_on_id', 'work_item_id')
            ->withPivot('kind')
            ->withTimestamps();
    }

    public function raciRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'raci_assignments')
            ->withPivot('raci')
            ->withTimestamps();
    }

    public function reviewTargets(): MorphMany
    {
        return $this->morphMany(ReviewTarget::class, 'reviewable');
    }

    public function changeImpacts(): MorphMany
    {
        return $this->morphMany(ChangeImpact::class, 'impactable');
    }

    public function deliveryLinks(): HasMany
    {
        return $this->hasMany(WorkItemDeliveryLink::class);
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'release_work_item')
            ->withTimestamps();
    }
}
