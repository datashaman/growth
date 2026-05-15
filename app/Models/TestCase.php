<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsViaProjectRelation;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TestCase extends Model
{
    use BroadcastsViaProjectRelation;
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'plan';

    public function projectIdForBroadcast(): ?string
    {
        return $this->plan?->project_id;
    }

    protected $fillable = [
        'test_plan_id', 'name', 'objective', 'preconditions',
        'inputs', 'expected_results', 'environment',
    ];

    protected $casts = [
        'preconditions' => 'array',
        'inputs' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TestPlan::class, 'test_plan_id');
    }

    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'requirement_test_case');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(TestRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(TestRun::class)->latestOfMany('run_at');
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }
}
