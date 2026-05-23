<?php

namespace App\Models;

use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Project extends Model
{
    use HasUlids;

    public const STATUSES = ['draft', 'active', 'archived', 'closed'];

    protected $fillable = ['workspace_id', 'created_by_user_id', 'name', 'description', 'github_repo', 'rigor_level', 'status', 'adopted_at'];

    protected $casts = [
        'rigor_level' => 'integer',
        'adopted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    public function isMutable(): bool
    {
        return in_array($this->status, ['draft', 'active'], true);
    }

    public function move(Workspace|string $destination, User $user): void
    {
        $destinationId = $destination instanceof Workspace ? $destination->id : $destination;

        abort_if($destinationId === $this->workspace_id, 422, 'Destination must differ from source.');

        $mutatorRoles = [WorkspaceMembership::ROLE_OWNER, WorkspaceMembership::ROLE_ADMIN];

        $sourceRole = WorkspaceMembership::query()
            ->where('workspace_id', $this->workspace_id)
            ->where('user_id', $user->id)
            ->value('role');

        abort_unless(in_array($sourceRole, $mutatorRoles, true), 403);

        $destinationRole = WorkspaceMembership::query()
            ->where('workspace_id', $destinationId)
            ->where('user_id', $user->id)
            ->value('role');

        abort_unless(in_array($destinationRole, $mutatorRoles, true), 403);

        DB::transaction(function () use ($destinationId): void {
            $this->forceFill(['workspace_id' => $destinationId])->save();
        });
    }

    protected static function booted(): void
    {
        static::addGlobalScope('workspace', function (Builder $query): void {
            $workspaceId = app(WorkspaceContext::class)->id();

            if ($workspaceId !== null) {
                $query->where('projects.workspace_id', $workspaceId);
            }
        });

        // Evidence assets sit three FK hops below a project (project → work
        // item → delivery link → asset). The cascade drops their rows without
        // firing model events, so their S3 objects would be orphaned. Delete
        // the assets through the model layer here, streamed so the cleanup
        // stays memory-bounded however many screenshots have accumulated.
        static::deleting(function (Project $project): void {
            $assets = EvidenceAsset::whereHas('deliveryLink.workItem', function (Builder $query) use ($project): void {
                $query->where('project_id', $project->getKey());
            });

            foreach ($assets->cursor() as $asset) {
                $asset->delete();
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function stakeholders(): HasMany
    {
        return $this->hasMany(Stakeholder::class);
    }

    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class);
    }

    public function designViews(): HasMany
    {
        return $this->hasMany(DesignView::class);
    }

    public function customViewpoints(): HasMany
    {
        return $this->hasMany(CustomViewpoint::class);
    }

    public function testPlans(): HasMany
    {
        return $this->hasMany(TestPlan::class);
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomaly::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    public function projectPlan(): HasOne
    {
        return $this->hasOne(ProjectPlan::class);
    }

    public function themes(): HasMany
    {
        return $this->hasMany(Theme::class);
    }

    public function themeAssignments(): HasMany
    {
        return $this->hasMany(ThemeAssignment::class);
    }

    public function defaultTheme(): HasOne
    {
        return $this->hasOne(Theme::class)->where('is_default', true);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reviewPlans(): HasMany
    {
        return $this->hasMany(ReviewPlan::class);
    }

    public function reviewFindings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function statusTransitions(): MorphMany
    {
        return $this->morphMany(StatusTransition::class, 'transitionable');
    }
}
