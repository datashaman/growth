<?php

namespace App\Models;

use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPlanBaseline extends Model
{
    use HasUlids;
    use ScopedByOwner;

    public const OWNER_SCOPE_RELATION = 'projectPlan';

    protected $fillable = [
        'project_plan_id', 'version', 'snapshot', 'baselined_at',
        'baselined_by_user_id', 'baselined_by_agent_id', 'note',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'baselined_at' => 'datetime',
    ];

    public function projectPlan(): BelongsTo
    {
        return $this->belongsTo(ProjectPlan::class);
    }

    public function baselinedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'baselined_by_user_id');
    }

    public function baselinedByAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'baselined_by_agent_id');
    }
}
