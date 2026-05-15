<?php

namespace App\Models;

use App\Models\Concerns\BroadcastsProjectChanges;
use App\Models\Concerns\ScopedByOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Risk extends Model
{
    use BroadcastsProjectChanges;
    use HasUlids;
    use ScopedByOwner;

    public const CATEGORIES = ['technical', 'schedule', 'cost', 'compliance', 'operational', 'external', 'other'];

    public const EXPOSURES = ['low', 'medium', 'high'];

    public const STATUSES = ['identified', 'assessed', 'mitigating', 'mitigated', 'accepted', 'realized', 'closed'];

    protected $fillable = [
        'project_id', 'owner_role_id', 'title', 'description', 'category',
        'probability', 'impact', 'status', 'mitigation_plan',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function ownerRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'owner_role_id');
    }

    public function citations(): MorphMany
    {
        return $this->morphMany(Citation::class, 'citable');
    }
}
