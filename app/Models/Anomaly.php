<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Anomaly extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;
    use ScopedByOwner;

    public const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    public const STATUSES = ['open', 'investigating', 'resolved', 'closed'];

    protected $fillable = [
        'project_id', 'test_run_id', 'severity', 'status',
        'summary', 'description', 'environment',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function testRun(): BelongsTo
    {
        return $this->belongsTo(TestRun::class);
    }

    public function affectedRequirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'anomaly_requirement');
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }
}
