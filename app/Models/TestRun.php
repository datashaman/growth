<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsViaProjectRelation;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestRun extends Model
{
    use BroadcastsViaProjectRelation;
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'case';

    public function projectIdForBroadcast(): ?string
    {
        return $this->case?->plan?->project_id;
    }

    public const STATUSES = ['pass', 'fail', 'blocked', 'skipped'];

    protected $fillable = ['test_case_id', 'status', 'run_at', 'notes', 'environment_snapshot'];

    protected $casts = [
        'run_at' => 'datetime',
        'environment_snapshot' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(TestCase::class, 'test_case_id');
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(Anomaly::class);
    }
}
