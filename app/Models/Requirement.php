<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Requirement extends Model
{
    use HasUlids;
    use ScopedByOwner;

    protected $fillable = [
        'project_id', 'parent_id', 'doc', 'type', 'text',
        'rationale', 'acceptance_criteria', 'source', 'priority', 'tags',
    ];

    protected $casts = [
        'acceptance_criteria' => 'array',
        'tags' => 'array',
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

    public function testCases(): BelongsToMany
    {
        return $this->belongsToMany(TestCase::class, 'requirement_test_case');
    }

    public function anomalies(): BelongsToMany
    {
        return $this->belongsToMany(Anomaly::class, 'anomaly_requirement');
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }

    public function workItems(): BelongsToMany
    {
        return $this->belongsToMany(WorkItem::class, 'requirement_work_item');
    }

    public function reviewTargets(): MorphMany
    {
        return $this->morphMany(ReviewTarget::class, 'reviewable');
    }

    public function changeImpacts(): MorphMany
    {
        return $this->morphMany(ChangeImpact::class, 'impactable');
    }
}
