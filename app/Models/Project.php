<?php

namespace App\Models;

use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Project extends Model
{
    use HasUlids;

    public const STATUSES = ['draft', 'active', 'archived', 'closed'];

    protected $fillable = ['workspace_id', 'created_by_user_id', 'name', 'description', 'rigor_level', 'status'];

    protected $casts = [
        'rigor_level' => 'integer',
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
}
