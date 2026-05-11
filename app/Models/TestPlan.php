<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestPlan extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const LEVELS = ['master', 'unit', 'integration', 'system', 'acceptance'];

    protected $fillable = ['project_id', 'level', 'name', 'scope', 'approach', 'pass_fail_criteria'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(TestCase::class);
    }
}
