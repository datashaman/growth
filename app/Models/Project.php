<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasUlids;

    public const STATUSES = ['draft', 'active', 'archived', 'closed'];

    protected $fillable = ['user_id', 'name', 'description', 'rigor_level', 'status'];

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

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $query): void {
            if (auth()->check()) {
                $query->where('projects.user_id', auth()->id());
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
