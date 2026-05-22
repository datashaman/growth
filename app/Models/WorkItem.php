<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RuntimeException;

class WorkItem extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;
    use ScopedByOwner;

    public const KINDS = ['deliverable', 'work_package', 'task'];

    public const STATUSES = ['todo', 'in_progress', 'blocked', 'done', 'cancelled'];

    public const RACI = ['r', 'a', 'c', 'i'];

    protected $fillable = [
        'project_id', 'parent_id', 'responsible_role_id', 'kind',
        'name', 'description', 'status', 'needs_mockups',
    ];

    protected $casts = [
        'number' => 'integer',
        'needs_mockups' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (WorkItem $workItem): void {
            if ($workItem->number === null) {
                $workItem->number = $workItem->nextNumberForProject();
            }
        });

        // The per-project number is assigned once on create; a work item's
        // parent, requirement links, and milestones are all project-scoped,
        // so moving it between projects is not a real operation. Block it
        // rather than leave a stale WI-NNN reference or a unique collision.
        static::updating(function (WorkItem $workItem): void {
            if ($workItem->isDirty('project_id')) {
                throw new RuntimeException('A work item cannot be moved to another project.');
            }
        });

        // Delivery links FK-cascade when the work item is deleted, but a DB
        // cascade fires no model events — so the evidence assets hanging off
        // those links would lose their S3 objects. Delete the assets through
        // the model layer here so their `deleting` cleanup runs, streamed so
        // the cleanup stays memory-bounded.
        static::deleting(function (WorkItem $workItem): void {
            $assets = EvidenceAsset::whereHas('deliveryLink', function ($query) use ($workItem): void {
                $query->where('work_item_id', $workItem->getKey());
            });

            foreach ($assets->cursor() as $asset) {
                $asset->delete();
            }
        });
    }

    /**
     * Human-readable per-project reference, e.g. "WI-009".
     */
    public function reference(): string
    {
        return 'WI-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Allocate the next sequential number within the project. Callers that
     * may run concurrently should wrap the create in a transaction so the
     * project row lock held here spans the insert.
     */
    protected function nextNumberForProject(): int
    {
        Project::whereKey($this->project_id)->lockForUpdate()->first();

        return (int) static::where('project_id', $this->project_id)->max('number') + 1;
    }

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
            ->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'work_item_dependencies', 'depends_on_id', 'work_item_id')
            ->withTimestamps();
    }

    public function raciRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'raci_assignments')
            ->withPivot('raci')
            ->withTimestamps();
    }

    public function consultedRoles(): BelongsToMany
    {
        return $this->raciRoles()->wherePivot('raci', 'c');
    }

    public function reviewTargets(): MorphMany
    {
        return $this->morphMany(ReviewTarget::class, 'reviewable');
    }

    public function statusTransitions(): MorphMany
    {
        return $this->morphMany(StatusTransition::class, 'transitionable');
    }

    public function changeImpacts(): MorphMany
    {
        return $this->morphMany(ChangeImpact::class, 'impactable');
    }

    public function deliveryLinks(): HasMany
    {
        return $this->hasMany(WorkItemDeliveryLink::class);
    }

    public function mockups(): MorphMany
    {
        return $this->morphMany(SpecMockup::class, 'owner');
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class, 'release_work_item')
            ->withTimestamps();
    }
}
