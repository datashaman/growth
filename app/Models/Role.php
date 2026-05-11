<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Role extends Model
{
    use HasUlids;
    use ScopedByOwner;

    protected $fillable = [
        'project_id', 'name', 'responsibilities',
        'weekly_capacity_hours', 'hourly_rate_amount', 'rate_currency',
    ];

    protected $casts = [
        'weekly_capacity_hours' => 'decimal:2',
        'hourly_rate_amount' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class, 'responsible_role_id');
    }

    public function users(): MorphToMany
    {
        return $this->morphedByMany(User::class, 'assignable');
    }

    public function agents(): MorphToMany
    {
        return $this->morphedByMany(Agent::class, 'assignable');
    }

    public function raciWorkItems(): BelongsToMany
    {
        return $this->belongsToMany(WorkItem::class, 'raci_assignments')
            ->withPivot('raci')
            ->withTimestamps();
    }

    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'owner_role_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'owner_role_id');
    }

    public function reviewFindings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class, 'owner_role_id');
    }

    public function reviewParticipants(): HasMany
    {
        return $this->hasMany(ReviewParticipant::class);
    }

    public function requestedChanges(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'requester_role_id');
    }
}
